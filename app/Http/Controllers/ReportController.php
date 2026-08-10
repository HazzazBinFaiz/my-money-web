<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Services\ReportSummary;
use App\Support\DateRange;
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
