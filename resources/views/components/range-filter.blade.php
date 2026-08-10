@props([
    'range',
    'route',
])

{{-- One row, above everything it scopes. --}}
<div class="flex flex-wrap items-center gap-2">
    @foreach (\App\Support\DateRange::PRESETS as $key => $label)
        @continue($key === 'custom')

        <a href="{{ route($route, ['range' => $key]) }}"
           class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-medium transition {{ $range->preset === $key
               ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
               : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}">
            {{ $label }}
        </a>
    @endforeach

    <form method="GET" action="{{ route($route) }}" class="ms-auto flex flex-wrap items-center gap-2">
        <x-ui.input name="from" type="date" class="!h-9 !w-auto"
                    :value="request('from', $range->isBounded() ? $range->start->format('Y-m-d') : null)" />
        <span class="text-sm text-gray-400">–</span>
        <x-ui.input name="to" type="date" class="!h-9 !w-auto"
                    :value="request('to', $range->isBounded() ? $range->end->format('Y-m-d') : null)" />
        <x-ui.button variant="outline" class="!h-9 !text-xs">{{ __('Apply') }}</x-ui.button>
    </form>
</div>
