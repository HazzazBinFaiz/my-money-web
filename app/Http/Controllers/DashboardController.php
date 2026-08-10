<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummary;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSummary $summary): View
    {
        $filters = $request->validate([
            'range' => ['nullable', 'string', 'in:'.implode(',', array_keys(DateRange::PRESETS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $range = DateRange::fromRequest(
            $filters['range'] ?? null,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
        );

        $totals = $summary->totals($range);
        $previous = $range->previous();

        return view('dashboard', [
            'range' => $range,
            'totals' => $totals,
            // Nothing to compare against when the range is open ended.
            'previousTotals' => $previous ? $summary->totals($previous) : null,
            'balances' => $summary->balances(),
            'series' => $summary->series($range),
            'categories' => $summary->topCategories($range),
            'recent' => $summary->recent(),
        ]);
    }
}
