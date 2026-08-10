<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The figures behind the dashboard, all scoped to the active book.
 *
 * Money that leaves an account carries its charge with it, so an expense costs
 * amount + charge and income arrives net of it. Transfers move money inside the
 * book and are never counted as income or expense, but the charge on one is a
 * real cost and is counted with the outgoings.
 */
class DashboardSummary
{
    /**
     * A contact account holds what stands between you and that person: in
     * credit means they still owe you, in debit means you owe them.
     *
     * @return array{balance: int, lent: int, owed: int, worth: int, accounts: Collection, lenders: Collection, borrowers: Collection}
     */
    public function balances(): array
    {
        $accounts = Account::with('icon')->orderByDesc('amount')->get();

        $own = $accounts->where('type', AccountType::Account);
        $contacts = $accounts->where('type', AccountType::Contact);

        $borrowers = $contacts->where('amount', '>', 0)->sortByDesc('amount')->values();
        $lenders = $contacts->where('amount', '<', 0)->sortBy('amount')->values();

        $balance = (int) $own->sum('amount');
        $lent = (int) $borrowers->sum('amount');
        $owed = (int) abs($lenders->sum('amount'));

        return [
            'balance' => $balance,
            // Out with other people, coming back to you one day.
            'lent' => $lent,
            // Sitting with you, belonging to someone else.
            'owed' => $owed,
            'worth' => $balance + $lent - $owed,
            'accounts' => $own->where('status', AccountStatus::Active)->values(),
            'borrowers' => $borrowers,
            'lenders' => $lenders,
        ];
    }

    /**
     * @return array{income: int, expense: int, net: int, fees: int, count: int}
     */
    public function totals(DateRange $range): array
    {
        $rows = $this->scoped($range)
            ->selectRaw('type, sum(amount) as amount, sum(charge) as charge, count(*) as rows')
            ->groupBy('type')
            ->get()
            ->keyBy(fn ($row) => $this->typeValue($row->type));

        $income = $rows->get(TransactionType::Income->value);
        $expense = $rows->get(TransactionType::Expense->value);
        $transfer = $rows->get(TransactionType::Transfer->value);

        $incomeTotal = (int) (($income->amount ?? 0) - ($income->charge ?? 0));
        $expenseTotal = (int) (($expense->amount ?? 0) + ($expense->charge ?? 0) + ($transfer->charge ?? 0));

        return [
            'income' => $incomeTotal,
            'expense' => $expenseTotal,
            'net' => $incomeTotal - $expenseTotal,
            'fees' => (int) $rows->sum('charge'),
            'count' => (int) $rows->sum('rows'),
        ];
    }

    /**
     * Income and expense per bucket, ready to plot.
     *
     * @return Collection<int, array{label: string, full: string, income: int, expense: int}>
     */
    public function series(DateRange $range): Collection
    {
        $byMonth = $range->grouping() === 'month';
        $format = $byMonth ? '%Y-%m' : '%Y-%m-%d';

        $rows = $this->scoped($range)
            ->selectRaw("strftime('{$format}', created_at) as bucket, type, sum(amount) as amount, sum(charge) as charge")
            ->groupBy('bucket', 'type')
            ->get();

        $buckets = $this->buckets($range, $byMonth);

        foreach ($rows as $row) {
            if (! isset($buckets[$row->bucket])) {
                continue;
            }

            $type = $this->typeValue($row->type);

            if ($type === TransactionType::Income->value) {
                $buckets[$row->bucket]['income'] += (int) $row->amount - (int) $row->charge;
            } elseif ($type === TransactionType::Expense->value) {
                $buckets[$row->bucket]['expense'] += (int) $row->amount + (int) $row->charge;
            } else {
                $buckets[$row->bucket]['expense'] += (int) $row->charge;
            }
        }

        return collect(array_values($buckets));
    }

    /**
     * Where the money went, biggest first, with the tail folded into one row.
     *
     * @return Collection<int, array{name: string, total: int, share: float}>
     */
    public function topCategories(DateRange $range, int $limit = 6): Collection
    {
        $rows = $this->scoped($range)
            ->where('type', TransactionType::Expense)
            ->selectRaw('category_id, sum(amount + charge) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $names = Category::whereIn('id', $rows->pluck('category_id')->filter())->pluck('name', 'id');
        $grand = (int) $rows->sum('total');

        if ($grand === 0) {
            return collect();
        }

        $top = $rows->take($limit)->map(fn ($row) => [
            'name' => $names[$row->category_id] ?? __('Uncategorised'),
            'total' => (int) $row->total,
        ]);

        $tail = (int) $rows->skip($limit)->sum('total');

        if ($tail > 0) {
            $top->push(['name' => __('Other'), 'total' => $tail]);
        }

        return $top->map(fn (array $row) => $row + ['share' => $row['total'] / $grand])->values();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function recent(int $limit = 8): Collection
    {
        return Transaction::with(['category', 'fromAccount', 'toAccount'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Aggregate rows still run through the model's casts, so the type comes
     * back as an enum rather than the raw column value.
     */
    private function typeValue(mixed $type): int
    {
        return $type instanceof TransactionType ? $type->value : (int) $type;
    }

    private function scoped(DateRange $range)
    {
        [$start, $end] = $range->bounds();

        return Transaction::query()
            ->when($start, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end, fn ($query) => $query->where('created_at', '<=', $end));
    }

    /**
     * Empty buckets so quiet days still take up space on the chart.
     */
    private function buckets(DateRange $range, bool $byMonth): array
    {
        [$start, $end] = $range->bounds();

        if (! $start || ! $end) {
            $first = Transaction::min('created_at');

            $start = $first ? CarbonImmutable::parse($first) : CarbonImmutable::today();
            $end = CarbonImmutable::today();
        }

        $buckets = [];
        $cursor = $byMonth ? $start->startOfMonth() : $start->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format($byMonth ? 'Y-m' : 'Y-m-d');

            $buckets[$key] = [
                'label' => $cursor->isoFormat($byMonth ? 'MMM' : 'D'),
                'full' => $cursor->isoFormat($byMonth ? 'MMMM YYYY' : 'ddd, D MMM YYYY'),
                'income' => 0,
                'expense' => 0,
            ];

            $cursor = $byMonth ? $cursor->addMonth() : $cursor->addDay();
        }

        return $buckets;
    }
}
