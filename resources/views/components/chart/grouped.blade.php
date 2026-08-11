@props([
    'rows' => null,
    'title' => 'Income and expense',
    'subtitle' => null,
    'empty' => 'Nothing recorded in this range.',
])

@php
    use App\Lib\Util;

    // Two series, so a legend is required and each bar reports itself on hover.
    $rows = collect($rows);
    $peak = max(1, (int) max($rows->max('income') ?? 0, $rows->max('expense') ?? 0));

    $points = $rows->map(fn (array $row) => [
        'label' => $row['label'],
        'income' => $row['income'],
        'expense' => $row['expense'],
        'incomeLabel' => Util::displayAmount($row['income']),
        'expenseLabel' => Util::displayAmount($row['expense']),
        'incomeShare' => $row['income'] / $peak,
        'expenseShare' => $row['expense'] / $peak,
    ])->values();
@endphp

<x-ui.card {{ $attributes->merge(['class' => 'viz']) }}>
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>

        <ul class="flex items-center gap-4 text-xs text-gray-600 dark:text-gray-300">
            <li class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm" style="background: var(--viz-income)"></span>{{ __('Income') }}
            </li>
            <li class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm" style="background: var(--viz-expense)"></span>{{ __('Expense') }}
            </li>
        </ul>
    </div>

    @if ($points->isEmpty())
        <p class="px-4 py-12 text-center text-sm text-gray-500 sm:px-6">{{ $empty }}</p>
    @else
        <div class="overflow-x-auto p-4 sm:p-6" x-data="{
            hovered: null,
            at: { left: 0, top: 0 },

            // The tooltip is positioned in viewport coordinates and lives outside
            // the scrolling plot area, so it cannot be clipped by it.
            show(point, el) {
                const box = el.getBoundingClientRect();

                // Anchor to the taller of the two bars, not to the group: the
                // group fills the plot, so its top is the top of the chart.
                const bars = [...el.children].map((bar) => bar.getBoundingClientRect().top);

                this.at = {
                    left: Math.min(Math.max(box.left + box.width / 2, 96), window.innerWidth - 96),
                    top: Math.min(...bars, box.bottom) - 10,
                };

                this.hovered = point;
            },

            hide() {
                this.hovered = null;
            },
        }">
            <div class="relative min-w-full" style="min-width: {{ max(320, $points->count() * 72) }}px">
                <!-- Tooltip -->
                <div x-show="hovered" x-cloak
                     class="pointer-events-none fixed z-50 -translate-x-1/2 -translate-y-full rounded-lg border border-gray-200
                            bg-white px-3 py-2 text-xs shadow-lg dark:border-gray-700 dark:bg-gray-800"
                     :style="`left: ${at.left}px; top: ${at.top}px`">
                    <p class="font-medium text-gray-500 dark:text-gray-400" x-text="hovered?.label"></p>
                    <p class="mt-1 flex items-center gap-2">
                        <span class="h-0.5 w-3 rounded" style="background: var(--viz-income)"></span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100" x-text="hovered?.incomeLabel"></span>
                        <span class="text-gray-500">{{ __('in') }}</span>
                    </p>
                    <p class="mt-0.5 flex items-center gap-2">
                        <span class="h-0.5 w-3 rounded" style="background: var(--viz-expense)"></span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100" x-text="hovered?.expenseLabel"></span>
                        <span class="text-gray-500">{{ __('out') }}</span>
                    </p>
                </div>

                <div class="flex h-64 items-end gap-3 border-b border-gray-200 dark:border-gray-700">
                    @foreach ($points as $index => $point)
                        <div class="group flex h-full flex-1 items-end justify-center gap-1"
                             tabindex="0"
                             @mouseenter="show(@js($point), $el)"
                             @focus="show(@js($point), $el)"
                             @mouseleave="hide()"
                             @blur="hide()">
                            <div class="w-full max-w-[26px] rounded-t transition-opacity group-hover:opacity-80 group-focus:opacity-80"
                                 style="height: {{ max($point['incomeShare'] * 100, $point['income'] > 0 ? 1.5 : 0) }}%; background: var(--viz-income)"></div>
                            <div class="w-full max-w-[26px] rounded-t transition-opacity group-hover:opacity-80 group-focus:opacity-80"
                                 style="height: {{ max($point['expenseShare'] * 100, $point['expense'] > 0 ? 1.5 : 0) }}%; background: var(--viz-expense)"></div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 flex gap-3 text-[10px] text-gray-500 dark:text-gray-400">
                    @foreach ($points as $point)
                        <span class="flex-1 truncate text-center" title="{{ $point['label'] }}">{{ $point['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-ui.card>
