<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\DateRange;
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
    public function categoryDetail(Category $category, DateRange $range): array
    {
        $rows = $this->scoped($range)
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

    private function scoped(DateRange $range)
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
