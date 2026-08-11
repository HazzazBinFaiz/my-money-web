@props([
    'rows' => null,
    'total' => 0,
    'income' => false,
    'title' => null,
    'subtitle' => null,
    'empty' => 'Nothing recorded in this range.',
])

@php
    use App\Lib\Util;

    // Eight distinct hues, then one bucket: past that, slices are thinner than
    // the gap between them and the legend stops being readable.
    $slices = collect($rows)->values();
    $shown = $slices->take(8);
    $rest = $slices->skip(8);

    if ($rest->isNotEmpty()) {
        $shown = $shown->push([
            'key' => 'rest',
            'name' => trans_choice(':count other category|:count other categories', $rest->count(), ['count' => $rest->count()]),
            'total' => (int) $rest->sum('total'),
            'share' => (float) $rest->sum('share'),
        ]);
    }

    // A donut drawn with one stroked circle per slice: dasharray is the arc,
    // dashoffset is where it starts. No canvas, no charting library.
    $radius = 60;
    $circumference = 2 * M_PI * $radius;
    $offset = 0.0;

    // Rank order, not palette order: the two blues sit at 1 and 8 so they never
    // land next to each other in the legend.
    $order = [1, 2, 3, 6, 4, 7, 8, 5];

    $arcs = $shown->values()->map(function (array $row, int $index) use (&$offset, $circumference, $order) {
        $length = $row['share'] * $circumference;
        $arc = $row + [
            'color' => $row['key'] === 'rest' ? 'var(--viz-cat-rest)' : 'var(--viz-cat-'.$order[$index % 8].')',
            'length' => $length,
            'offset' => -$offset,
        ];

        $offset += $length;

        return $arc;
    });
@endphp

<x-ui.card {{ $attributes->merge(['class' => 'viz']) }}>
    @if ($title)
        <div class="border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    @if ($arcs->isEmpty() || $total <= 0)
        <p class="px-4 py-12 text-center text-sm text-gray-500 sm:px-6">{{ $empty }}</p>
    @else
        <div class="flex flex-col items-center gap-6 p-4 sm:p-6 md:flex-row md:items-center md:gap-8">
            <div class="relative shrink-0">
                <svg viewBox="0 0 160 160" class="viz-pie h-48 w-48" role="img"
                     aria-label="{{ $income ? __('Income by category') : __('Expense by category') }}">
                    <g transform="rotate(-90 80 80)">
                        @foreach ($arcs as $arc)
                            <circle class="viz-slice" cx="80" cy="80" r="{{ $radius }}" fill="none"
                                    tabindex="0"
                                    stroke="{{ $arc['color'] }}"
                                    stroke-width="28"
                                    stroke-dasharray="{{ round($arc['length'], 3) }} {{ round($circumference, 3) }}"
                                    stroke-dashoffset="{{ round($arc['offset'], 3) }}">
                                <title>{{ $arc['name'] }}: {{ Util::displayAmount($arc['total']) }} ({{ round($arc['share'] * 100, 1) }}%)</title>
                            </circle>
                        @endforeach
                    </g>

                    <!-- Hole punched with the card colour so the ring reads as a donut -->
                    <circle cx="80" cy="80" r="{{ $radius - 14 }}" fill="var(--viz-surface)"/>
                </svg>

                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $income ? __('Income') : __('Expense') }}
                    </span>
                    <span class="text-lg font-bold tabular-nums {{ $income
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-rose-600 dark:text-rose-400' }}">
                        {{ Util::displayAmount($total) }}
                    </span>
                </div>
            </div>

            <!-- Legend carries the numbers, so nothing here needs a hover -->
            <ul class="grid w-full min-w-0 flex-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                @foreach ($arcs as $arc)
                    <li class="flex items-center gap-2 text-sm">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $arc['color'] }}"></span>
                        <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-300">{{ $arc['name'] }}</span>
                        <span class="shrink-0 tabular-nums text-xs text-gray-500 dark:text-gray-400">
                            {{ round($arc['share'] * 100) }}%
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-ui.card>
