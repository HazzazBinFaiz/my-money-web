<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Lib\CryptoEncryptor;
use App\Lib\CryptoKeyDeriver;
use App\Models\Account;
use App\Models\Category;
use App\Models\Image;
use App\Models\Transaction;

/**
 * Writes the active book back out in the mobile app's .mbak shape.
 *
 * The format has no charge field, so every charge is exported as an extra
 * expense record under a "Transfer Charge" category. Inactive accounts get a
 * leading dot on their name, which is how the app marks them.
 *
 * Icons come from the image's export_icon_id, the id the app knows that icon
 * by. Anything without one falls back to the app's default icon.
 */
class MbakExporter
{
    public const CHARGE_CATEGORY = 'Transfer Charge';

    /** Backup record types. */
    private const TYPE_INCOME = 1;

    private const TYPE_EXPENSE = 2;

    private const TYPE_TRANSFER = 3;

    /** Used when an image carries no app icon id of its own. */
    private const DEFAULT_ICON = 1;

    /** @var array<int, array> keyed by account id */
    private array $accounts = [];

    /** @var array<int, array> keyed by category id */
    private array $categories = [];

    /** Synthetic id for the charge category, which has no row of its own. */
    private ?array $chargeCategory = null;

    /**
     * @return array{accounts: array, budgets: array, categories: array, records: array}
     */
    public function payload(): array
    {
        $this->accounts = Account::with('icon')->orderBy('id')->get()
            ->mapWithKeys(fn (Account $account) => [$account->id => [
                'amount' => $this->major($account->amount),
                'icon' => $this->icon($account->icon),
                'id' => $account->id,
                'initial' => $this->major($account->initial_amount),
                'name' => $account->status === AccountStatus::Inactive
                    ? '.'.$account->name
                    : $account->name,
            ]])
            ->all();

        $this->categories = Category::with('icon')->orderBy('id')->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => [
                'icon' => $this->icon($category->icon),
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type === CategoryType::Income ? self::TYPE_INCOME : self::TYPE_EXPENSE,
            ]])
            ->all();

        $records = [];

        $transactions = Transaction::orderBy('created_at')->orderBy('id')->get();

        foreach ($transactions as $transaction) {
            $time = $transaction->created_at->getTimestampMs();

            $records[] = $this->record($transaction, $time);

            if ($transaction->charge > 0) {
                // One millisecond later so the charge keeps its own id.
                $records[] = $this->chargeRecord($transaction, $time + 1);
            }
        }

        $categories = array_values($this->categories);

        if ($this->chargeCategory) {
            $categories[] = $this->chargeCategory;
        }

        return [
            'accounts' => array_values($this->accounts),
            'budgets' => [],
            'categories' => $categories,
            'records' => $records,
        ];
    }

    public function encrypted(): string
    {
        return CryptoEncryptor::encrypt(
            json_encode($this->payload()),
            CryptoKeyDeriver::deriveKeyAndHmac(),
        );
    }

    private function record(Transaction $transaction, int $time): array
    {
        if ($transaction->type === TransactionType::Transfer) {
            return [
                'amount' => $this->major($transaction->amount),
                'id' => $time,
                'note' => (string) $transaction->note,
                'time' => $time,
                'transferFrom' => $this->accounts[$transaction->from_account_id] ?? null,
                'transferTo' => $this->accounts[$transaction->to_account_id] ?? null,
                'type' => self::TYPE_TRANSFER,
            ];
        }

        $isIncome = $transaction->type === TransactionType::Income;

        return [
            'account' => $this->accounts[$isIncome ? $transaction->to_account_id : $transaction->from_account_id] ?? null,
            'amount' => $this->major($transaction->amount),
            'category' => $this->categories[$transaction->category_id] ?? null,
            'id' => $time,
            'note' => (string) $transaction->note,
            'time' => $time,
            'type' => $isIncome ? self::TYPE_INCOME : self::TYPE_EXPENSE,
        ];
    }

    /**
     * The charge leaves whichever account paid it: the source for expenses and
     * transfers, the receiving account for income.
     */
    private function chargeRecord(Transaction $transaction, int $time): array
    {
        $accountId = $transaction->type === TransactionType::Income
            ? $transaction->to_account_id
            : $transaction->from_account_id;

        return [
            'account' => $this->accounts[$accountId] ?? null,
            'amount' => $this->major($transaction->charge),
            'category' => $this->chargeCategory(),
            'id' => $time,
            'note' => (string) $transaction->note,
            'time' => $time,
            'type' => self::TYPE_EXPENSE,
        ];
    }

    private function chargeCategory(): array
    {
        if ($this->chargeCategory) {
            return $this->chargeCategory;
        }

        // Reuse a real category of that name when the book already has one.
        foreach ($this->categories as $category) {
            if (mb_strtolower($category['name']) === mb_strtolower(self::CHARGE_CATEGORY)
                && $category['type'] === self::TYPE_EXPENSE) {
                return $this->chargeCategory = $category;
            }
        }

        return $this->chargeCategory = [
            'icon' => self::DEFAULT_ICON,
            'id' => (max(array_keys($this->categories) ?: [0]) + 1),
            'name' => self::CHARGE_CATEGORY,
            'type' => self::TYPE_EXPENSE,
        ];
    }

    /**
     * The icon id the mobile app knows this image by.
     */
    private function icon(?Image $image): int|string
    {
        $exportId = trim((string) $image?->export_icon_id);

        if ($exportId === '') {
            return self::DEFAULT_ICON;
        }

        return is_numeric($exportId) ? (int) $exportId : $exportId;
    }

    /**
     * Backups hold major units, we store minor ones.
     */
    private function major(int $minor): float
    {
        return round($minor / 100, 2);
    }
}
