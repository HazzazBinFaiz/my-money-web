<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Add Transaction') }}
            </h2>
            <a href="{{ route('transactions.index') }}"
               class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-300">{{ __('Back to list') }}</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'transaction-created')
                <div class="mx-auto mb-4 max-w-3xl rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800
                            dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                    {{ trans_choice(
                        'Saved. :count transaction added so far — the next one is ready.|Saved. :count transactions added so far — the next one is ready.',
                        session('created_streak', 1),
                        ['count' => session('created_streak', 1)],
                    ) }}
                </div>
            @endif

            <x-transaction-form
                add-more
                :action="route('transactions.store')"
                :submit="__('Create transaction')"
                :own-accounts="$ownAccounts"
                :contact-accounts="$contactAccounts"
                :income-categories="$incomeCategories"
                :expense-categories="$expenseCategories" />
        </div>
    </div>
</x-app-layout>
