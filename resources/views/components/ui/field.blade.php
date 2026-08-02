@props([
    'label' => null,
    'for' => null,
    'error' => null,
    'hint' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif
               class="block text-sm font-medium leading-none text-gray-700 dark:text-gray-300">
            {{ $label }}
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif

    <x-input-error :messages="$error" class="!text-xs" />
</div>
