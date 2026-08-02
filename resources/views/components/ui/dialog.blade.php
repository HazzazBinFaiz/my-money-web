@props([
    'show' => 'open',
    'title' => null,
    'size' => 'max-w-lg',
])

{{-- $show is an Alpine expression owned by the surrounding x-data scope. --}}
<div x-show="{{ $show }}" x-cloak
     @keydown.escape.window="{{ $show }} = false"
     class="fixed inset-0 z-50 flex items-end justify-center sm:items-center p-0 sm:p-4">

    <div class="absolute inset-0 bg-black/50 backdrop-blur-[1px]" @click="{{ $show }} = false"></div>

    <div class="relative w-full {{ $size }} max-h-[92vh] overflow-y-auto rounded-t-2xl sm:rounded-xl
                border border-gray-200 bg-white p-5 text-left shadow-xl
                dark:border-gray-700 dark:bg-gray-800">
        @if ($title)
            <div class="mb-4 flex items-start justify-between gap-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                <button type="button" @click="{{ $show }} = false"
                        class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
