<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\DateRange;
use App\Support\ReportFilter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Figures behind the report pages, scoped to the active book.
 *
 * Same money rules as the dashboard: an expense costs amount + charge, income
 * arrives net of its charge, and a transfer is an internal move whose charge
 * still leaves the book.
 */
class ReportSummary
{
    /**
     * Income and expense per account over the range.
     *
     * @return Collection<int, array{account: Account, income: int, expense: int}>
     */
    public function perAccount(DateRange $range): Collection
    {
        $rows = $this->scoped($range)->get(['type', 'amount', 'charge', 'from_account_id', 'to_account_id']);

        $totals = [];

        foreach ($rows as $row) {
            $type = $this->typeOf($row);

            if ($type === TransactionType::Income && $row->to_account_id) {
                $totals[$row->to_account_id]['income'] = ($totals[$row->to_account_id]['income'] ?? 0)
                    + $row->amount - $row->charge;
            }

            if ($type === TransactionType::Expense && $row->from_account_id) {
                $totals[$row->from_account_id]['expense'] = ($totals[$row->from_account_id]['expense'] ?? 0)
                    + $row->amount + $row->charge;
            }

            // A transfer is not spending, but its charge is money the source lost.
            if ($type === TransactionType::Transfer && $row->from_account_id && $row->charge > 0) {
                $totals[$row->from_account_id]['expense'] = ($totals[$row->from_account_id]['expense'] ?? 0)
                    + $row->charge;
            }
        }

        return Account::with('icon')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => [
                'account' => $account,
                'income' => (int) ($totals[$account->id]['income'] ?? 0),
                'expense' => (int) ($totals[$account->id]['expense'] ?? 0),
            ])
            ->reject(fn (array $row) => $row['income'] === 0 && $row['expense'] === 0)
            ->values();
    }

    /**
     * Income and expense per category over the range.
     *
     * @return Collection<int, array{category: Category, income: int, expense: int}>
     */
    public function perCategory(DateRange $range): Collection
    {
        $rows = $this->scoped($range)
            ->whereNotNull('category_id')
            ->get(['type', 'amount', 'charge', 'category_id']);

        $totals = [];

        foreach ($rows as $row) {
            $type = $this->typeOf($row);

            if ($type === TransactionType::Income) {
                $totals[$row->category_id]['income'] = ($totals[$row->category_id]['income'] ?? 0)
                    + $row->amount - $row->charge;
            } elseif ($type === TransactionType::Expense) {
                $totals[$row->category_id]['expense'] = ($totals[$row->category_id]['expense'] ?? 0)
                    + $row->amount + $row->charge;
            }
        }

        return Category::with('icon')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'category' => $category,
                'income' => (int) ($totals[$category->id]['income'] ?? 0),
                'expense' => (int) ($totals[$category->id]['expense'] ?? 0),
            ])
            ->reject(fn (array $row) => $row['income'] === 0 && $row['expense'] === 0)
            ->values();
    }

    /**
     * One side of the ledger split into its categories, biggest share first.
     *
     * Transfer charges have no category but are still money out, so on the
     * expense side they ride along as their own row rather than quietly
     * shrinking the total the pie adds up to.
     *
     * @return array{type: CategoryType, total: int, count: int, rows: Collection<int, array<string, mixed>>}
     */
    public function overview(CategoryType $type, DateRange $range, ?ReportFilter $filter = null): array
    {
        $isIncome = $type === CategoryType::Income;

        $movements = $this->sideOf($type, $range, $filter)
            ->get(['type', 'amount', 'charge', 'category_id']);

        $totals = [];
        $charges = 0;

        foreach ($movements as $row) {
            if ($this->typeOf($row) === TransactionType::Transfer) {
                $charges += $row->charge;

                continue;
            }

            if ($row->category_id) {
                $totals[$row->category_id] = ($totals[$row->category_id] ?? 0)
                    + $this->flowValue($row, $isIncome);
            }
        }

        $rows = Category::with('icon')
            ->whereIn('id', array_keys($totals))
            ->get()
            ->map(fn (Category $category) => [
                'key' => (string) $category->id,
                'category' => $category,
                'name' => $category->name,
                'total' => (int) $totals[$category->id],
            ])
            ->reject(fn (array $row) => $row['total'] <= 0)
            ->values();

        if ($charges > 0) {
            $rows->push([
                'key' => 'charges',
                'category' => null,
                'name' => __('Transfer charges'),
                'total' => (int) $charges,
            ]);
        }

        $total = (int) $rows->sum('total');

        $rows = $rows
            ->sortByDesc('total')
            ->values()
            ->map(fn (array $row) => $row + [
                'share' => $total > 0 ? $row['total'] / $total : 0.0,
            ]);

        return [
            'type' => $type,
            'total' => $total,
            'count' => $rows->count(),
            'rows' => $rows,
        ];
    }

    /**
     * One side of the ledger totalled per calendar day, for the flow calendar.
     *
     * Same arithmetic as the overview, so a month of cells adds up to what the
     * overview reports for the same range — transfer charges included on the
     * expense side.
     *
     * @return array{total: int, max: int, days: array<string, int>, busiest: ?string}
     */
    public function dailyFlow(CategoryType $type, DateRange $range, ?ReportFilter $filter = null): array
    {
        $isIncome = $type === CategoryType::Income;

        $rows = $this->sideOf($type, $range, $filter)->get(['type', 'amount', 'charge', 'created_at']);

        $days = [];

        foreach ($rows as $row) {
            $value = $this->flowValue($row, $isIncome);

            if ($value > 0) {
                $day = $row->created_at->format('Y-m-d');
                $days[$day] = ($days[$day] ?? 0) + $value;
            }
        }

        ksort($days);

        return [
            'total' => (int) array_sum($days),
            'max' => $days === [] ? 0 : (int) max($days),
            'days' => $days,
            'busiest' => $days === [] ? null : (string) array_search(max($days), $days, true),
        ];
    }

    /**
     * What one calendar day was made of, for the flow modal.
     *
     * @return array{date: CarbonImmutable, total: int, transactions: Collection<int, Transaction>}
     */
    public function flowDay(CategoryType $type, CarbonImmutable $date, ?ReportFilter $filter = null): array
    {
        $isIncome = $type === CategoryType::Income;

        $day = new DateRange('custom', $date->startOfDay(), $date->endOfDay());

        $rows = $this->sideOf($type, $day, $filter)
            ->orderBy('created_at')
            ->orderBy('id')
            ->with(['category', 'fromAccount', 'toAccount'])
            ->get();

        return [
            'date' => $date,
            'total' => (int) $rows->sum(fn (Transaction $row) => $this->flowValue($row, $isIncome)),
            'transactions' => $rows,
        ];
    }

    /**
     * What a single transaction contributes to one side of the flow.
     */
    public function flowValue(Transaction $transaction, bool $isIncome): int
    {
        $type = $this->typeOf($transaction);

        if ($isIncome) {
            return $type === TransactionType::Income
                ? $transaction->amount - $transaction->charge
                : 0;
        }

        return match ($type) {
            TransactionType::Expense => $transaction->amount + $transaction->charge,
            TransactionType::Transfer => $transaction->charge,
            default => 0,
        };
    }

    /**
     * Everything on one side of the ledger over a range: income on its own, or
     * expenses plus the transfer charges that behave like them.
     */
    private function sideOf(CategoryType $type, DateRange $range, ?ReportFilter $filter = null): Builder
    {
        $query = $this->scoped($range)
            ->when($type === CategoryType::Income,
                fn (Builder $inner) => $inner->where('type', TransactionType::Income),
                // A transfer belongs on the expense side for its charge alone.
                fn (Builder $inner) => $inner->where(fn (Builder $sides) => $sides
                    ->where('type', TransactionType::Expense)
                    ->orWhere(fn (Builder $charged) => $charged
                        ->where('type', TransactionType::Transfer)
                        ->where('charge', '>', 0))),
            );

        return $filter ? $filter->apply($query, $type) : $query;
    }

    /**
     * What one category was worth against its whole side of the ledger, for the
     * overview modal.
     *
     * @return array<string, mixed>
     */
    public function overviewDetail(Category $category, DateRange $range, ?ReportFilter $filter = null): array
    {
        $detail = $this->categoryDetail($category, $range, $filter);
        $overall = $this->overview($category->type, $range, $filter);

        return $detail + [
            'overall' => $overall['total'],
            'share' => $overall['total'] > 0 ? $detail['total'] / $overall['total'] : 0.0,
        ];
    }

    /**
     * Everything the account modal shows: the balance it opened the range with,
     * what moved during it, and where that leaves it.
     *
     * @return array<string, mixed>
     */
    public function accountDetail(Account $account, DateRange $range): array
    {
        $opening = $this->balanceBefore($account, $range);

        $rows = $this->scoped($range)
            ->where(fn ($query) => $query->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id))
            ->orderBy('created_at')
            ->orderBy('id')
            ->with(['category', 'fromAccount', 'toAccount'])
            ->get();

        $income = 0;
        $expense = 0;
        $transferIn = 0;
        $transferOut = 0;
        $transferCharge = 0;

        foreach ($rows as $row) {
            if ($row->type === TransactionType::Income && $row->to_account_id === $account->id) {
                $income += $row->amount - $row->charge;
            }

            if ($row->type === TransactionType::Expense && $row->from_account_id === $account->id) {
                $expense += $row->amount + $row->charge;
            }

            if ($row->type === TransactionType::Transfer) {
                if ($row->from_account_id === $account->id) {
                    $transferOut += $row->amount;
                    $transferCharge += $row->charge;
                }

                if ($row->to_account_id === $account->id) {
                    $transferIn += $row->amount;
                }
            }
        }

        return [
            'subject' => $account,
            'opening' => $opening,
            'income' => $income,
            'expense' => $expense,
            'transfer_in' => $transferIn,
            'transfer_out' => $transferOut,
            'transfer_charge' => $transferCharge,
            'closing' => $opening + $income - $expense + $transferIn - $transferOut - $transferCharge,
            'transactions' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function categoryDetail(Category $category, DateRange $range, ?ReportFilter $filter = null): array
    {
        $rows = $this->scoped($range)
            ->when($filter?->accountId, fn (Builder $query, int $id) => $query->where(
                $category->type === CategoryType::Income ? 'to_account_id' : 'from_account_id', $id
            ))
            ->where('category_id', $category->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->with(['category', 'fromAccount', 'toAccount'])
            ->get();

        $isIncome = $category->type === CategoryType::Income;

        $total = $rows->sum(fn (Transaction $row) => $isIncome
            ? $row->amount - $row->charge
            : $row->amount + $row->charge);

        // Which accounts this category touched, biggest first.
        $accounts = $rows
            ->groupBy(fn (Transaction $row) => $isIncome ? $row->to_account_id : $row->from_account_id)
            ->map(fn (Collection $group) => [
                'account' => $isIncome ? $group->first()->toAccount : $group->first()->fromAccount,
                'total' => (int) $group->sum(fn (Transaction $row) => $isIncome
                    ? $row->amount - $row->charge
                    : $row->amount + $row->charge),
            ])
            ->filter(fn (array $row) => $row['account'] !== null)
            ->sortByDesc('total')
            ->values();

        return [
            'subject' => $category,
            'total' => (int) $total,
            'count' => $rows->count(),
            'average' => $rows->count() > 0 ? (int) round($total / $rows->count()) : 0,
            'charge' => (int) $rows->sum('charge'),
            'accounts' => $accounts,
            'transactions' => $rows,
        ];
    }

    /**
     * Where the account stood the moment the range opened.
     */
    private function balanceBefore(Account $account, DateRange $range): int
    {
        [$start] = $range->bounds();

        if (! $start) {
            return $account->initial_amount;
        }

        $rows = Transaction::query()
            ->where('created_at', '<', $start)
            ->where(fn ($query) => $query->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id))
            ->get(['type', 'amount', 'charge', 'from_account_id', 'to_account_id']);

        $balance = $account->initial_amount;

        foreach ($rows as $row) {
            if ($row->from_account_id === $account->id) {
                $balance -= $row->amount + $row->charge;
            }

            if ($row->to_account_id === $account->id) {
                $balance += $this->typeOf($row) === TransactionType::Income
                    ? $row->amount - $row->charge
                    : $row->amount;
            }
        }

        return (int) $balance;
    }

    /**
     * Accounts of both kinds, for the filter on the account report.
     *
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        return Account::orderBy('type')->orderBy('name')->get();
    }

    public function isContact(Account $account): bool
    {
        return $account->type === AccountType::Contact;
    }

    private function scoped(DateRange $range): Builder
    {
        [$start, $end] = $range->bounds();

        return Transaction::query()
            ->when($start, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end, fn ($query) => $query->where('created_at', '<=', $end));
    }

    /**
     * Rows fetched with a column list still carry the model cast.
     */
    private function typeOf(Transaction $row): TransactionType
    {
        return $row->type instanceof TransactionType
            ? $row->type
            : TransactionType::from((int) $row->type);
    }
}
