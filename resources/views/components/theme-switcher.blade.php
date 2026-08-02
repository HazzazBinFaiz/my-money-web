@props(['class' => ''])

@php
    $options = [
        ['value' => 'light', 'label' => __('Light'), 'icon' => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z'],
        ['value' => 'dark', 'label' => __('Dark'), 'icon' => 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z'],
        ['value' => 'system', 'label' => __('System'), 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
    ];
@endphp

<div x-data="{ theme: localStorage.getItem('theme') || 'system' }"
     class="{{ $class }}">
    <p class="px-4 pt-2 pb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Theme') }}</p>

    <div class="mx-3 mb-2 grid grid-cols-3 gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-900">
        @foreach ($options as $option)
            <button type="button"
                    @click.stop="theme = '{{ $option['value'] }}'; window.setTheme(theme)"
                    :class="theme === '{{ $option['value'] }}'
                        ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                        : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="flex flex-col items-center gap-1 rounded-md px-2 py-1.5 text-[11px] font-medium transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $option['icon'] }}"/>
                </svg>
                {{ $option['label'] }}
            </button>
        @endforeach
    </div>
</div>
