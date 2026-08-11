@props([
    'range',
    'days' => [],
    'max' => 0,
    'income' => false,
    // Route name for the day fragment; called with [type, date].
    'type' => 'expense',
    // Extra query the day fragment needs, so a filtered page opens filtered days.
    'query' => [],
    'months' => 12,
])

@php
    use App\Lib\Util;
    use Carbon\CarbonImmutable;

    $first = $range->start->startOfMonth();
    $last = $range->end->startOfMonth();

    // Whole months always render, even when the range clips them: a half drawn
    // February is harder to read than a greyed one.
    $grids = [];
    $cursor = $first;

    while ($cursor->lessThanOrEqualTo($last) && count($grids) < $months) {
        $grids[] = $cursor;
        $cursor = $cursor->addMonth();
    }

    $truncated = $cursor->lessThanOrEqualTo($last);

    // One month gets the room to spell its amounts out; a year tiles.
    $columns = match (true) {
        count($grids) === 1 => 'max-w-2xl',
        count($grids) === 2 => 'md:grid-cols-2',
        default => 'md:grid-cols-2 xl:grid-cols-3',
    };

    $tint = $income ? 'var(--viz-income)' : 'var(--viz-expense)';
    $ink = $income ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300';

    // Sunday first, matching the date pickers elsewhere in the app.
    $weekdays = collect(range(0, 6))->map(fn (int $day) => CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY)->addDays($day)->isoFormat('dd'));
@endphp

<div {{ $attributes->merge(['class' => 'viz space-y-4']) }}>
    <div class="grid gap-4 {{ $columns }}">
        @foreach ($grids as $month)
            @php
                $start = $month->startOfWeek(CarbonImmutable::SUNDAY);
                $end = $month->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);
                $monthTotal = 0;

                for ($day = $month; $day->lessThanOrEqualTo($month->endOfMonth()); $day = $day->addDay()) {
                    $monthTotal += $days[$day->format('Y-m-d')] ?? 0;
                }
            @endphp

            <x-ui.card class="overflow-hidden">
                <div class="flex items-baseline justify-between border-b border-gray-100 px-3 py-2.5 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $month->isoFormat('MMMM YYYY') }}</h3>
                    <span class="text-sm font-semibold tabular-nums {{ $ink }}">{{ Util::displayAmount($monthTotal) }}</span>
                </div>

                <div class="grid grid-cols-7 border-b border-gray-100 dark:border-gray-700">
                    @foreach ($weekdays as $weekday)
                        <span class="py-1.5 text-center text-[10px] font-medium uppercase tracking-wide text-gray-400">{{ $weekday }}</span>
                    @endforeach
                </div>

                <div class="grid grid-cols-7">
                    @for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay())
                        @php
                            $key = $day->format('Y-m-d');
                            $inMonth = $day->month === $month->month;
                            $inRange = $inMonth
                                && $day->betweenIncluded($range->start->startOfDay(), $range->end->startOfDay());
                            $amount = $inRange ? ($days[$key] ?? 0) : 0;

                            // Weight is by share of the busiest day, floored so a
                            // small day is still visible against an outlier.
                            $weight = $max > 0 && $amount > 0
                                ? max(12, round($amount / $max * 100))
                                : 0;

                            $url = $amount > 0
                                ? route('reports.flow.day', ['type' => $type, 'date' => $key] + $query)
                                : null;
                        @endphp

                        <div class="relative flex aspect-square flex-col overflow-hidden border-b border-e border-gray-100 p-1 last:border-e-0 dark:border-gray-800
                                    {{ $inRange ? '' : 'bg-gray-50 dark:bg-gray-900/40' }}
                                    {{ $url ? 'cursor-pointer transition hover:ring-2 hover:ring-inset hover:ring-gray-300 dark:hover:ring-gray-600' : '' }}"
                             @if ($url)
                                 role="button"
                                 tabindex="0"
                                 data-day="{{ $key }}"
                                 aria-label="{{ $day->isoFormat('D MMMM YYYY') }}: {{ Util::displayAmount($amount) }}"
                                 @click="open(@js($url))"
                                 @keydown.enter="open(@js($url))"
                             @endif
                             @if ($weight > 0)
                                 style="background: color-mix(in srgb, {{ $tint }} {{ $weight }}%, transparent)"
                             @endif>

                            <span class="text-[10px] leading-none {{ match (true) {
                                $weight >= 55 => 'text-white/80',
                                $inRange => 'text-gray-500 dark:text-gray-400',
                                default => 'text-gray-300 dark:text-gray-600',
                            } }}">
                                {{ $day->day }}
                            </span>

                            @if ($amount > 0)
                                <span class="flex flex-1 items-center justify-center overflow-hidden">
                                    <span class="w-full truncate text-center text-[11px] font-semibold leading-tight tabular-nums
                                                 {{ $weight >= 55 ? 'text-white' : $ink }}"
                                          title="{{ Util::displayAmount($amount) }}">
                                        {{ Util::displayAmount($amount) }}
                                    </span>
                                </span>
                            @endif
                        </div>
                    @endfor
                </div>
            </x-ui.card>
        @endforeach
    </div>

    @if ($truncated)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Showing the first :count months of this range. Narrow the dates to see the rest.', ['count' => $months]) }}
        </p>
    @endif
</div>
