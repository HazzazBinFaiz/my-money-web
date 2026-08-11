@props([
    'series' => null,
    'title' => 'Money in and out',
])

@php
    use App\Lib\Util;

    $series = collect($series);
    $peak = max(1, (int) max($series->max('income') ?? 0, $series->max('expense') ?? 0));

    // Grouped columns: two series, so a legend is required and the hovered
    // column reports both values at once.
    $points = $series->map(fn (array $row) => $row + [
        'incomeShare' => $row['income'] / $peak,
        'expenseShare' => $row['expense'] / $peak,
        'incomeLabel' => Util::displayAmount($row['income']),
        'expenseLabel' => Util::displayAmount($row['expense']),
    ])->values();

    // Only every nth tick gets a label once the buckets get crowded.
    $step = (int) ceil(max(1, $points->count()) / 12);
@endphp

<x-ui.card {{ $attributes->merge(['class' => 'viz']) }}>
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Transfers are left out; charges count as money leaving.') }}
            </p>
        </div>

        <!-- Legend: two series, so identity is never colour alone -->
        <ul class="flex items-center gap-4 text-xs text-gray-600 dark:text-gray-300">
            <li class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm" style="background: var(--viz-income)"></span>{{ __('In') }}
            </li>
            <li class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm" style="background: var(--viz-expense)"></span>{{ __('Out') }}
            </li>
        </ul>
    </div>

    @if ($points->isEmpty() || $peak <= 1)
        <p class="px-4 py-12 text-center text-sm text-gray-500 sm:px-6">{{ __('Nothing recorded in this range yet.') }}</p>
    @else
        <div class="p-4 sm:p-6" x-data="{
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
            <div class="relative">
                <!-- Tooltip -->
                <div x-show="hovered" x-cloak
                     class="pointer-events-none fixed z-50 -translate-x-1/2 -translate-y-full rounded-lg border border-gray-200
                            bg-white px-3 py-2 text-xs shadow-lg dark:border-gray-700 dark:bg-gray-800"
                     :style="`left: ${at.left}px; top: ${at.top}px`">
                    <p class="font-medium text-gray-500 dark:text-gray-400" x-text="hovered?.full"></p>
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

                <!-- Plot -->
                <div class="flex h-56 items-end gap-[2px] border-b border-gray-200 dark:border-gray-700">
                    @foreach ($points as $index => $point)
                        <div class="group relative flex h-full flex-1 items-end justify-center gap-[2px]"
                             tabindex="0"
                             @mouseenter="show(@js($point), $el)"
                             @focus="show(@js($point), $el)"
                             @mouseleave="hide()"
                             @blur="hide()">
                            <div class="w-full rounded-t transition-opacity group-hover:opacity-80 group-focus:opacity-80"
                                 style="height: {{ max($point['incomeShare'] * 100, $point['income'] > 0 ? 1.5 : 0) }}%; background: var(--viz-income)"></div>
                            <div class="w-full rounded-t transition-opacity group-hover:opacity-80 group-focus:opacity-80"
                                 style="height: {{ max($point['expenseShare'] * 100, $point['expense'] > 0 ? 1.5 : 0) }}%; background: var(--viz-expense)"></div>
                        </div>
                    @endforeach
                </div>

                <!-- Axis -->
                <div class="mt-2 flex gap-[2px] text-[10px] text-gray-500 dark:text-gray-400">
                    @foreach ($points as $index => $point)
                        <span class="flex-1 text-center">{{ $index % $step === 0 ? $point['label'] : '' }}</span>
                    @endforeach
                </div>
            </div>

            <!-- The values without hovering, since a tooltip must never be the only route -->
            <details class="mt-4">
                <summary class="cursor-pointer text-xs text-gray-500 hover:underline dark:text-gray-400">
                    {{ __('Show as a table') }}
                </summary>

                <div class="mt-3 max-h-56 overflow-y-auto">
                    <table class="min-w-full text-xs">
                        <thead class="text-left text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="py-1 pe-3 font-medium">{{ __('Period') }}</th>
                                <th class="py-1 pe-3 text-right font-medium">{{ __('In') }}</th>
                                <th class="py-1 text-right font-medium">{{ __('Out') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 dark:text-gray-300">
                            @foreach ($points as $point)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-1 pe-3">{{ $point['full'] }}</td>
                                    <td class="py-1 pe-3 text-right">{{ $point['incomeLabel'] }}</td>
                                    <td class="py-1 text-right">{{ $point['expenseLabel'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    @endif
</x-ui.card>
