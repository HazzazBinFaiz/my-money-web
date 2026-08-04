<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\ImageType;
use App\Enums\TransactionType;
use App\Lib\Util;
use App\Models\Account;
use App\Models\Category;
use App\Models\Image;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Loads a decrypted .mbak payload into the active book.
 *
 * The backup nests whole account and category objects inside each record, and
 * those are not always present in the top level lists, so both places are
 * scanned before anything is created. Matching is by name, case insensitively.
 *
 * Icons are the app's own resource ids. They are matched against the shared
 * icon library through export_icon_id, within the matching image type, so a
 * category icon can never land on an account. Anything unmatched stays blank.
 *
 * A leading dot on an account name marks it inactive in the backup; the dot is
 * stripped for matching and the account is created inactive.
 */
class MbakImporter
{
    /** Backup record types. */
    private const TYPE_INCOME = 1;

    private const TYPE_EXPENSE = 2;

    private const TYPE_TRANSFER = 3;

    /** @var array<string, Account> keyed by lowercase name */
    private array $accounts = [];

    /** @var array<string, Category> keyed by "lowercase name|type" */
    private array $categories = [];

    /** @var array<string, array<string, int>> export icon id => image id, per image type */
    private array $icons = [];

    public function __construct(private LedgerService $ledger) {}

    /**
     * @param  array{accounts: array, categories: array, records: array}  $data
     * @return array{accounts_created: int, categories_created: int, transactions_created: int, skipped: int}
     */
    public function import(array $data): array
    {
        $summary = [
            'accounts_created' => 0,
            'categories_created' => 0,
            'transactions_created' => 0,
            'skipped' => 0,
        ];

        DB::transaction(function () use ($data, &$summary) {
            $this->loadExisting();

            $records = collect($data['records'] ?? [])
                ->sortBy(fn (array $record) => $record['time'] ?? $record['id'] ?? 0)
                ->values();

            // Top level lists first so their opening balances win over the
            // copies embedded in records.
            foreach ($data['accounts'] ?? [] as $account) {
                $summary['accounts_created'] += $this->resolveAccount($account) ? 1 : 0;
            }

            foreach ($data['categories'] ?? [] as $category) {
                $summary['categories_created'] += $this->resolveCategory($category) ? 1 : 0;
            }

            foreach ($records as $record) {
                foreach (['account', 'transferFrom', 'transferTo'] as $key) {
                    if (isset($record[$key])) {
                        $summary['accounts_created'] += $this->resolveAccount($record[$key]) ? 1 : 0;
                    }
                }

                if (isset($record['category'])) {
                    $summary['categories_created'] += $this->resolveCategory($record['category']) ? 1 : 0;
                }
            }

            foreach ($records as $record) {
                if ($this->createTransaction($record)) {
                    $summary['transactions_created']++;
                } else {
                    $summary['skipped']++;
                }
            }

            // Balances are derived, so replay every account the import touched.
            $this->ledger->recalculate(collect($this->accounts)->map->id->all());
        });

        return $summary;
    }

    private function loadExisting(): void
    {
        foreach ([ImageType::Account, ImageType::Category] as $type) {
            $this->icons[$type->name] = Image::withoutGlobalScopes()
                ->whereNull('user_id')
                ->where('type', $type)
                ->whereNotNull('export_icon_id')
                ->pluck('id', 'export_icon_id')
                ->all();
        }

        foreach (Account::all() as $account) {
            $this->accounts[mb_strtolower($account->name)] ??= $account;
        }

        foreach (Category::all() as $category) {
            $this->categories[$this->categoryKey($category->name, $category->type)] ??= $category;
        }
    }

    /**
     * @return bool whether a new account was created
     */
    private function resolveAccount(array $source): bool
    {
        [$name, $status] = $this->accountNameAndStatus($source['name'] ?? '');

        if ($name === '') {
            return false;
        }

        $key = mb_strtolower($name);

        if (isset($this->accounts[$key])) {
            return false;
        }

        $initial = Util::toMinorUnits($source['initial'] ?? 0);

        $this->accounts[$key] = Account::create([
            'type' => AccountType::Account,
            'status' => $status,
            'name' => $name,
            'initial_amount' => $initial,
            'amount' => $initial,
            'icon_id' => $this->iconId($source['icon'] ?? null, ImageType::Account),
        ]);

        return true;
    }

    /**
     * ".Savings" means an inactive account called "Savings".
     *
     * @return array{0: string, 1: AccountStatus}
     */
    private function accountNameAndStatus(mixed $rawName): array
    {
        $name = trim((string) $rawName);

        if (str_starts_with($name, '.')) {
            return [trim(mb_substr($name, 1)), AccountStatus::Inactive];
        }

        return [$name, AccountStatus::Active];
    }

    /**
     * @return bool whether a new category was created
     */
    private function resolveCategory(array $source): bool
    {
        $name = trim((string) ($source['name'] ?? ''));
        $type = $this->categoryType($source['type'] ?? null);

        if ($name === '' || ! $type) {
            return false;
        }

        $key = $this->categoryKey($name, $type);

        if (isset($this->categories[$key])) {
            return false;
        }

        $this->categories[$key] = Category::create([
            'type' => $type,
            'status' => CategoryStatus::Active,
            'name' => $name,
            'icon_id' => $this->iconId($source['icon'] ?? null, ImageType::Category),
        ]);

        return true;
    }

    private function createTransaction(array $record): bool
    {
        $type = (int) ($record['type'] ?? 0);
        $amount = Util::toMinorUnits(abs((float) ($record['amount'] ?? 0)));
        $occurredAt = $this->occurredAt($record);
        $note = ($record['note'] ?? '') === '' ? null : trim((string) $record['note']);

        if ($amount <= 0) {
            return false;
        }

        if ($type === self::TYPE_TRANSFER) {
            $from = $this->account($record['transferFrom'] ?? []);
            $to = $this->account($record['transferTo'] ?? []);

            if (! $from || ! $to || $from->id === $to->id) {
                return false;
            }

            $this->store(TransactionType::Transfer, $amount, $occurredAt, $note, $from->id, $to->id, null);

            return true;
        }

        $account = $this->account($record['account'] ?? []);
        $category = $this->category($record['category'] ?? []);

        if (! $account) {
            return false;
        }

        if ($type === self::TYPE_INCOME) {
            $this->store(TransactionType::Income, $amount, $occurredAt, $note, null, $account->id, $category?->id);

            return true;
        }

        if ($type === self::TYPE_EXPENSE) {
            $this->store(TransactionType::Expense, $amount, $occurredAt, $note, $account->id, null, $category?->id);

            return true;
        }

        return false;
    }

    private function store(
        TransactionType $type,
        int $amount,
        CarbonImmutable $occurredAt,
        ?string $note,
        ?int $fromId,
        ?int $toId,
        ?int $categoryId,
    ): void {
        Transaction::create([
            'type' => $type,
            'category_id' => $categoryId,
            'amount' => $amount,
            // Backups carry no charge.
            'charge' => 0,
            'from_account_id' => $fromId,
            'to_account_id' => $toId,
            'note' => $note,
            'created_at' => $occurredAt,
        ]);
    }

    private function account(array $source): ?Account
    {
        [$name] = $this->accountNameAndStatus($source['name'] ?? '');

        return $name === '' ? null : ($this->accounts[mb_strtolower($name)] ?? null);
    }

    private function category(array $source): ?Category
    {
        $name = trim((string) ($source['name'] ?? ''));
        $type = $this->categoryType($source['type'] ?? null);

        if ($name === '' || ! $type) {
            return null;
        }

        return $this->categories[$this->categoryKey($name, $type)] ?? null;
    }

    /**
     * Looks an app icon id up in the shared library of that image type.
     */
    private function iconId(mixed $icon, ImageType $type): ?int
    {
        $key = trim((string) $icon);

        if ($key === '') {
            return null;
        }

        return $this->icons[$type->name][$key] ?? null;
    }

    /**
     * Backup category types: 1 income, 2 expense.
     */
    private function categoryType(mixed $type): ?CategoryType
    {
        return match ((int) $type) {
            self::TYPE_INCOME => CategoryType::Income,
            self::TYPE_EXPENSE => CategoryType::Expense,
            default => null,
        };
    }

    private function categoryKey(string $name, CategoryType $type): string
    {
        return mb_strtolower($name).'|'.$type->value;
    }

    /**
     * Backup timestamps are epoch milliseconds.
     */
    private function occurredAt(array $record): CarbonImmutable
    {
        $milliseconds = (int) ($record['time'] ?? $record['id'] ?? 0);

        if ($milliseconds <= 0) {
            return CarbonImmutable::now();
        }

        return CarbonImmutable::createFromTimestampMs($milliseconds)
            ->setTimezone(config('app.timezone', 'UTC'));
    }
}
