@php
    // A stylised copy of the transaction list. Branding comes from the same
    // places the real app uses, so a new logo or name needs no new artwork.
    $rows = [
        [
            'title' => __('Groceries'),
            'icon' => 'categories/category_food.png',
            'account' => __('Visa'),
            'accountIcon' => 'accounts/account_card.png',
            'note' => __('Weekly shop'),
            'amount' => '−21.40',
            'tone' => 'expense',
            'balance' => __('Balance').': 107.70',
        ],
        [
            'title' => __('Freelance'),
            'icon' => 'categories/category_awards.png',
            'account' => __('City Bank'),
            'accountIcon' => 'accounts/account_bank.png',
            'note' => __('Logo work'),
            'amount' => '+625.00',
            'tone' => 'income',
            'balance' => __('Balance').': 15,815.50',
        ],
        [
            'title' => __('Transport'),
            'icon' => 'categories/category_transportation.png',
            'account' => __('Cash'),
            'accountIcon' => 'accounts/account_cash.png',
            'note' => __('Bus'),
            'amount' => '−3.20',
            'tone' => 'expense',
            'balance' => __('Balance').': 412.30',
        ],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl shadow-gray-900/10 dark:border-gray-800 dark:bg-gray-900 dark:shadow-black/40']) }}
     aria-hidden="true">

    <!-- App chrome -->
    <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-2">
                <x-application-logo class="h-6 w-6 rounded-lg" />
                <span class="text-sm font-semibold">{{ config('app.name') }}</span>
            </div>

            <nav class="hidden items-center gap-5 text-xs sm:flex">
                @foreach ([__('Dashboard'), __('Transactions'), __('Accounts'), __('Books')] as $index => $item)
                    <span class="{{ $index === 1
                        ? 'border-b-2 border-gray-900 pb-1 font-medium text-gray-900 dark:border-white dark:text-white'
                        : 'text-gray-500 dark:text-gray-400' }}">{{ $item }}</span>
                @endforeach
            </nav>
        </div>

        <span class="hidden text-xs text-gray-500 sm:block dark:text-gray-400">{{ __('Alex Rivera') }}</span>
    </div>

    <!-- Active book bar -->
    <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        {{ __('Personal') }}
    </div>

    <div class="bg-gray-50 p-4 sm:p-6 dark:bg-gray-950">
        <!-- Toolbar -->
        <div class="mb-4 flex items-center justify-between gap-3">
            <div class="inline-flex rounded-lg bg-gray-200/70 p-0.5 text-xs dark:bg-gray-800">
                <span class="rounded-md bg-white px-2.5 py-1 font-medium shadow-sm dark:bg-gray-700">{{ __('List') }}</span>
                <span class="px-2.5 py-1 text-gray-500 dark:text-gray-400">{{ __('Table') }}</span>
            </div>

            <div class="flex items-center gap-2">
                <span class="hidden rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white sm:inline dark:bg-white dark:text-gray-900">
                    + {{ __('Add Transaction') }}
                </span>
                <span class="hidden rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium sm:inline dark:border-gray-700 dark:bg-gray-900">
                    {{ __('Add Bulk Transaction') }}
                </span>
            </div>
        </div>

        <p class="mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Mon, 3 Aug 2026') }}</p>
        <hr class="mb-3 border-gray-200 dark:border-gray-800">

        <!-- Rows -->
        <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
            @foreach ($rows as $row)
                <div class="flex items-center gap-3 px-3 py-3 sm:gap-4 sm:px-5">
                    <img src="{{ asset('images/'.$row['icon']) }}" alt="" class="avatar h-9 w-9 sm:h-11 sm:w-11">

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $row['title'] }}</p>
                        <div class="mt-0.5 flex items-center gap-1.5">
                            <img src="{{ asset('images/'.$row['accountIcon']) }}" alt="" class="avatar h-4 w-4">
                            <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $row['account'] }}</span>
                        </div>
                        <p class="mt-0.5 truncate text-[11px] text-gray-400">{{ $row['note'] }}</p>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold {{ $row['tone'] === 'income'
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-rose-600 dark:text-rose-400' }}">{{ $row['amount'] }}</p>
                        <p class="text-[11px] text-gray-400">{{ $row['balance'] }}</p>
                    </div>
                </div>
            @endforeach

            <!-- Transfer row, showing both closing balances -->
            <div class="flex items-center gap-3 px-3 py-3 sm:gap-4 sm:px-5">
                <img src="{{ asset('images/transfer.png') }}" alt="" class="avatar h-9 w-9 sm:h-11 sm:w-11">

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ __('Transfer') }}</p>
                    <div class="mt-0.5 flex items-center gap-1.5">
                        <img src="{{ asset('images/accounts/account_bank.png') }}" alt="" class="avatar h-4 w-4">
                        <svg class="h-3 w-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <img src="{{ asset('images/accounts/account_savings.png') }}" alt="" class="avatar h-4 w-4">
                        <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ __('City Bank → Savings') }}</span>
                    </div>
                    <p class="mt-0.5 truncate text-[11px] text-gray-400">{{ __('Monthly saving') }}</p>
                </div>

                <div class="shrink-0 text-right">
                    <p class="text-sm font-semibold">1,000.00</p>
                    <p class="text-[11px] text-gray-400">{{ __('City Bank') }}: 15,190.50</p>
                    <p class="text-[11px] text-gray-400">{{ __('Savings') }}: 26,000.00</p>
                </div>
            </div>
        </div>
    </div>
</div>
