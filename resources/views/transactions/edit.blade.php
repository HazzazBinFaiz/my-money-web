<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Edit Transaction') }}
            </h2>
            <a href="{{ route('transactions.index') }}"
               class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-300">{{ __('Back to list') }}</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-transaction-form
                :transaction="$transaction"
                :action="route('transactions.update', $transaction)"
                :submit="__('Save changes')"
                :own-accounts="$ownAccounts"
                :contact-accounts="$contactAccounts"
                :income-categories="$incomeCategories"
                :expense-categories="$expenseCategories" />
        </div>
    </div>
</x-app-layout>
