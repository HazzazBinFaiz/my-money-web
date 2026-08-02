@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge([
    'class' => 'rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800',
]) }}>
    @if ($title || $description)
        <div class="border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
            @if ($title)
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
            @endif
            @if ($description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
