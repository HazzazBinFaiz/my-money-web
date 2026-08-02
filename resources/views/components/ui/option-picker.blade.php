@props([
    'name',
    'groups' => [],
    'value' => null,
    'placeholder' => 'Select...',
])

@php
    // $groups: [['label' => 'Accounts', 'options' => [['value' => 1, 'label' => 'Cash', 'icon' => url|null]]]]
    $options = collect($groups)->flatMap(fn ($group) => $group['options'])->values()->all();
    $index = collect($options)->mapWithKeys(fn ($option, $key) => [$option['value'] => $key]);
@endphp

<div x-data="optionPicker(@js(['options' => $options, 'value' => $value]))"
     @click.outside="open = false"
     class="relative">

    <input type="hidden" name="{{ $name }}" :value="selected ?? ''">

    <button type="button" @click="open = ! open"
            class="flex h-10 w-full items-center justify-between gap-2 rounded-md border border-gray-200 bg-white px-3
                   text-sm text-gray-900 shadow-sm transition focus:outline-none focus:ring-2 focus:ring-gray-900/10
                   dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:focus:ring-white/10">
        <span class="flex min-w-0 items-center gap-2">
            <template x-if="current() && current().icon">
                <img :src="current().icon" alt="" class="h-6 w-6 shrink-0 rounded-full object-cover">
            </template>
            <template x-if="current() && ! current().icon">
                <span class="h-6 w-6 shrink-0 rounded-full bg-gray-100 dark:bg-gray-700"></span>
            </template>
            <span class="truncate"
                  :class="current() ? '' : 'text-gray-400'"
                  x-text="current() ? current().label : @js($placeholder)"></span>
        </span>

        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute z-40 mt-1 max-h-72 w-full overflow-y-auto rounded-md border border-gray-200 bg-white p-1 shadow-lg
                dark:border-gray-700 dark:bg-gray-800">
        @forelse ($groups as $group)
            @if (count($group['options']))
                <p class="px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $group['label'] }}</p>

                @foreach ($group['options'] as $option)
                    <button type="button"
                            @click="choose(options[{{ $index[$option['value']] }}])"
                            :class="isSelected(options[{{ $index[$option['value']] }}]) ? 'bg-gray-100 dark:bg-gray-700' : ''"
                            class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition hover:bg-gray-100 dark:hover:bg-gray-700">
                        @if ($option['icon'])
                            <img src="{{ $option['icon'] }}" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover">
                        @else
                            <span class="h-7 w-7 shrink-0 rounded-full bg-gray-100 dark:bg-gray-600"></span>
                        @endif
                        <span class="truncate text-gray-900 dark:text-gray-100">{{ $option['label'] }}</span>
                    </button>
                @endforeach
            @endif
        @empty
            <p class="px-2 py-3 text-sm text-gray-500">{{ __('Nothing to pick.') }}</p>
        @endforelse
    </div>
</div>
