@props([
    'label',
    'value',
    'sub' => null,
    'delta' => null,
    'goodWhenUp' => true,
])

@php
    // The delta is a share, e.g. 0.12 for twelve percent up.
    $direction = $delta === null ? null : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'));
    $good = $direction === null || $direction === 'flat'
        ? null
        : (($direction === 'up') === $goodWhenUp);
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-gray-700 dark:bg-gray-800']) }}>
    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>

    <p class="mt-2 text-2xl font-bold tabular-nums text-gray-900 sm:text-3xl dark:text-gray-100">{{ $value }}</p>

    <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
        @if ($direction && $direction !== 'flat')
            <span class="inline-flex items-center gap-1 font-medium {{ $good
                ? 'text-emerald-600 dark:text-emerald-400'
                : 'text-rose-600 dark:text-rose-400' }}">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="{{ $direction === 'up' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                </svg>
                {{ abs(round($delta * 100)) }}%
            </span>
        @endif

        @if ($sub)
            <span class="text-gray-500 dark:text-gray-400">{{ $sub }}</span>
        @endif
    </div>
</div>
