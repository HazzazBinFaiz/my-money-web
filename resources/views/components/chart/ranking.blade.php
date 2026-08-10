@props([
    'rows' => null,
    'title' => 'Where it went',
    'empty' => 'No spending in this range yet.',
])

@php
    use App\Lib\Util;

    // One series, so one hue for every bar: darker-where-bigger would encode
    // the length twice and say nothing new.
    $rows = collect($rows);
    $peak = max(1, (int) ($rows->max('total') ?? 0));
@endphp

<x-ui.card {{ $attributes->merge(['class' => 'viz']) }}>
    <div class="border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Expenses by category, charges included.') }}</p>
    </div>

    @if ($rows->isEmpty())
        <p class="px-4 py-12 text-center text-sm text-gray-500 sm:px-6">{{ $empty }}</p>
    @else
        <ul class="space-y-3 p-4 sm:p-6">
            @foreach ($rows as $row)
                <li>
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="truncate text-gray-700 dark:text-gray-300">{{ $row['name'] }}</span>

                        <!-- Direct labels: the value is readable without hovering -->
                        <span class="shrink-0 tabular-nums font-medium text-gray-900 dark:text-gray-100">
                            {{ Util::displayAmount($row['total']) }}
                            <span class="ms-1 text-xs font-normal text-gray-500">{{ round($row['share'] * 100) }}%</span>
                        </span>
                    </div>

                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full" style="width: {{ max(2, $row['total'] / $peak * 100) }}%; background: var(--viz-bar)"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-ui.card>
