<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Services\ReportSummary;
use App\Support\DateRange;
use App\Support\ReportFilter;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function accounts(Request $request, ReportSummary $summary): View
    {
        $range = $this->range($request);

        return view('reports.accounts', [
            'range' => $range,
            'rows' => $summary->perAccount($range),
        ]);
    }

    public function categories(Request $request, ReportSummary $summary): View
    {
        $range = $this->range($request);

        return view('reports.categories', [
            'range' => $range,
            'rows' => $summary->perCategory($range),
        ]);
    }

    public function expenses(Request $request, ReportSummary $summary): View
    {
        return $this->overview(CategoryType::Expense, $request, $summary);
    }

    public function incomes(Request $request, ReportSummary $summary): View
    {
        return $this->overview(CategoryType::Income, $request, $summary);
    }

    private function overview(CategoryType $type, Request $request, ReportSummary $summary): View
    {
        $range = $this->range($request);

        // No category filter here: the pie is already the category breakdown,
        // and picking one would leave a single slice at 100%.
        $filter = new ReportFilter(ReportFilter::fromRequest($request)->accountId);

        return view('reports.overview', [
            'range' => $range,
            'type' => $type,
            'filter' => $filter,
            'overview' => $summary->overview($type, $range, $filter),
            'route' => $type === CategoryType::Income ? 'reports.incomes' : 'reports.expenses',
        ] + $this->filterOptions());
    }

    public function expenseFlow(Request $request, ReportSummary $summary): View
    {
        return $this->flow(CategoryType::Expense, $request, $summary);
    }

    public function incomeFlow(Request $request, ReportSummary $summary): View
    {
        return $this->flow(CategoryType::Income, $request, $summary);
    }

    /**
     * A calendar cannot be drawn for an open-ended range, so the flow pages
     * fall back to the current month rather than showing nothing.
     */
    private function flow(CategoryType $type, Request $request, ReportSummary $summary): View
    {
        $range = $this->range($request);

        if (! $range->isBounded()) {
            $range = DateRange::fromRequest('this_month', null, null);
        }

        $filter = ReportFilter::fromRequest($request);

        return view('reports.flow', [
            'range' => $range,
            'type' => $type,
            'filter' => $filter,
            'flow' => $summary->dailyFlow($type, $range, $filter),
            'route' => $type === CategoryType::Income ? 'reports.income-flow' : 'reports.expense-flow',
        ] + $this->filterOptions());
    }

    /**
     * One day of one side of the ledger, for the calendar modal.
     */
    public function flowDay(Request $request, string $type, string $date, ReportSummary $summary): View
    {
        $side = match ($type) {
            'income' => CategoryType::Income,
            'expense' => CategoryType::Expense,
            default => abort(404),
        };

        try {
            $day = CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (InvalidFormatException) {
            abort(404);
        }

        return view('reports.partials.flow-day', [
            'type' => $side,
            'day' => $summary->flowDay($side, $day, ReportFilter::fromRequest($request)),
        ]);
    }

    /**
     * A category's share of its side of the ledger, for the overview modal.
     */
    public function overviewDetail(Request $request, Category $category, ReportSummary $summary): View
    {
        $range = $this->range($request);

        return view('reports.partials.overview-detail', [
            'range' => $range,
            'detail' => $summary->overviewDetail($category, $range, new ReportFilter(
                ReportFilter::fromRequest($request)->accountId
            )),
        ]);
    }

    /**
     * The account breakdown behind the modal, rendered as a fragment.
     */
    public function account(Request $request, Account $account, ReportSummary $summary): View
    {
        $range = $this->range($request);

        return view('reports.partials.account-detail', [
            'range' => $range,
            'detail' => $summary->accountDetail($account, $range),
        ]);
    }

    public function category(Request $request, Category $category, ReportSummary $summary): View
    {
        $range = $this->range($request);

        return view('reports.partials.category-detail', [
            'range' => $range,
            'detail' => $summary->categoryDetail($category, $range),
        ]);
    }

    /**
     * The accounts and categories the filter selects offer.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'filterAccounts' => Account::orderBy('type')->orderBy('name')->get(),
            'filterCategories' => Category::orderBy('type')->orderBy('name')->get(),
        ];
    }

    private function range(Request $request): DateRange
    {
        $filters = $request->validate([
            'range' => ['nullable', 'string', 'in:'.implode(',', array_keys(DateRange::PRESETS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return DateRange::fromRequest(
            $filters['range'] ?? null,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
        );
    }
}
