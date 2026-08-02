@props(['transaction'])

<div x-data="{ open: false }" @click.outside="open = false" class="relative shrink-0">
    <button type="button" @click="open = ! open"
            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700
                   dark:hover:bg-gray-700 dark:hover:text-gray-200"
            title="{{ __('Actions') }}">
        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute end-0 z-30 mt-1 w-36 overflow-hidden rounded-md border border-gray-200 bg-white py-1 text-left
                shadow-lg dark:border-gray-700 dark:bg-gray-800">
        <a href="{{ route('transactions.edit', $transaction) }}"
           class="block px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
            {{ __('Edit') }}
        </a>

        <form method="POST" action="{{ route('transactions.destroy', $transaction) }}"
              onsubmit="return confirm('{{ __('Delete this transaction? Balances will be recalculated.') }}')">
            @csrf
            @method('DELETE')
            <button class="block w-full px-3 py-2 text-left text-sm text-red-600 transition hover:bg-red-50 dark:hover:bg-red-950/40">
                {{ __('Delete') }}
            </button>
        </form>
    </div>
</div>
