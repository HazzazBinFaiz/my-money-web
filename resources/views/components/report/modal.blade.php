{{-- Shell the report pages drop their detail fragment into. --}}
<div x-show="showing" x-cloak
     @keydown.escape.window="close()"
     class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4">

    <div class="absolute inset-0 bg-black/50 backdrop-blur-[1px]" @click="close()"></div>

    <div class="relative flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl border border-gray-200
                bg-white shadow-xl sm:rounded-xl dark:border-gray-700 dark:bg-gray-900">

        <button type="button" @click="close()"
                class="absolute end-3 top-3 z-10 rounded-md bg-white/80 p-1.5 text-gray-400 transition hover:bg-gray-100
                       hover:text-gray-600 dark:bg-gray-900/80 dark:hover:bg-gray-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        <p x-show="loading" class="px-6 py-16 text-center text-sm text-gray-500">{{ __('Loading...') }}</p>
        <p x-show="error" x-cloak class="px-6 py-16 text-center text-sm text-red-600" x-text="error"></p>

        <div x-show="! loading && ! error" class="min-h-0 flex-1 overflow-y-auto" x-html="body"></div>
    </div>
</div>
