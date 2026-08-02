@props([
    'variant' => 'primary',
    'type' => 'submit',
])

@php
    $base = 'inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-md px-4 text-sm font-medium transition
             focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
             disabled:pointer-events-none disabled:opacity-50';

    $variants = [
        'primary' => 'bg-gray-900 text-white shadow-sm hover:bg-gray-800 focus-visible:ring-gray-900
                      dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200 dark:focus-visible:ring-gray-100',
        'outline' => 'border border-gray-200 bg-white text-gray-900 shadow-sm hover:bg-gray-50 focus-visible:ring-gray-400
                      dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800',
        'ghost' => 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-gray-400
                    dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-500 focus-visible:ring-red-500',
    ];
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $base.' '.($variants[$variant] ?? $variants['primary'])]) }}>
    {{ $slot }}
</button>
