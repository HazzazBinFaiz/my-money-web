@php
    use App\Enums\TransactionType;
    use App\Lib\Util;
    use App\Support\DateRange;

    // A share of change against the same length of time before this one.
    $delta = function (?array $previous, string $key) use ($totals) {
        if (! $previous || ($previous[$key] ?? 0) === 0) {
            return null;
        }

        return ($totals[$key] - $previous[$key]) / abs($previous[$key]);
    };

    $comparedTo = $previousTotals ? __('vs previous period') : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Dashboard') }}
            </h2>

            <a href="{{ route('transactions.create') }}"
               class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-gray-900 px-3 text-sm font-medium text-white
                      shadow-sm transition hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Add Transaction') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <!-- Filters: one row, above everything they scope -->
            <div class="flex flex-wrap items-center gap-2">
                @foreach (DateRange::PRESETS as $key => $label)
                    @continue($key === 'custom')

                    <a href="{{ route('dashboard', ['range' => $key]) }}"
                       class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-medium transition {{ $range->preset === $key
                           ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                           : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                        {{ $label }}
                    </a>
                @endforeach

                <form method="GET" action="{{ route('dashboard') }}" class="ms-auto flex flex-wrap items-center gap-2">
                    <x-ui.input name="from" type="date" class="!h-9 !w-auto"
                                :value="request('from', $range->isBounded() ? $range->start->format('Y-m-d') : null)" />
                    <span class="text-sm text-gray-400">–</span>
                    <x-ui.input name="to" type="date" class="!h-9 !w-auto"
                                :value="request('to', $range->isBounded() ? $range->end->format('Y-m-d') : null)" />
                    <x-ui.button variant="outline" class="!h-9 !text-xs">{{ __('Apply') }}</x-ui.button>
                </form>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Showing') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ $range->label() }}</span>
                · {{ trans_choice(':count transaction|:count transactions', $totals['count'], ['count' => $totals['count']]) }}
            </p>

            <!-- Headline figures -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat :label="__('Balance')"
                           :value="Util::displayAmount($balances['balance'])"
                           :sub="$balances['lent'] || $balances['owed']
                                ? __(':worth once lending is settled', ['worth' => Util::displayAmount($balances['worth'])])
                                : __('across your own accounts')" />

                <x-ui.stat :label="__('Money in')"
                           :value="Util::displayAmount($totals['income'])"
                           :delta="$delta($previousTotals, 'income')"
                           :sub="$comparedTo" />

                <x-ui.stat :label="__('Money out')"
                           :value="Util::displayAmount($totals['expense'])"
                           :delta="$delta($previousTotals, 'expense')"
                           :good-when-up="false"
                           :sub="$comparedTo" />

                <x-ui.stat :label="__('Net')"
                           :value="Util::displayAmount($totals['net'])"
                           :sub="$totals['fees'] > 0
                                ? __('includes :fees in charges', ['fees' => Util::displayAmount($totals['fees'])])
                                : __('no charges in this range')" />
            </div>

            <!-- Charts -->
            <div class="grid gap-6 lg:grid-cols-5">
                <x-chart.flow :series="$series" class="lg:col-span-3" />
                <x-chart.ranking :rows="$categories" class="lg:col-span-2" />
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                <!-- Accounts -->
                <x-ui.card :title="__('Accounts')" class="lg:col-span-2">
                    @forelse ($balances['accounts'] as $account)
                        <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 first:border-t-0 sm:px-6 dark:border-gray-700">
                            @if ($account->icon)
                                <img src="{{ $account->icon->url }}" alt="" class="avatar h-8 w-8">
                            @else
                                <span class="avatar h-8 w-8 bg-gray-100 dark:bg-gray-700"></span>
                            @endif

                            <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-300">{{ $account->name }}</span>

                            <span class="shrink-0 text-sm font-semibold tabular-nums {{ $account->amount < 0
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-gray-900 dark:text-gray-100' }}">
                                {{ Util::displayAmount($account->amount) }}
                            </span>
                        </div>
                    @empty
                        <p class="px-4 py-10 text-center text-sm text-gray-500 sm:px-6">
                            {{ __('No accounts yet.') }}
                            <a href="{{ route('accounts.index') }}" class="font-medium underline">{{ __('Add one') }}</a>.
                        </p>
                    @endforelse

                </x-ui.card>

                <!-- Lending -->
                <x-ui.card class="lg:col-span-3">
                    <div class="border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('People') }}</h3>
                            <a href="{{ route('contacts.index') }}" class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-400">
                                {{ __('Contacts') }}
                            </a>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-emerald-50 px-3 py-2 dark:bg-emerald-950/30">
                                <p class="text-xs font-medium text-emerald-700 dark:text-emerald-400">{{ __('They owe you') }}</p>
                                <p class="mt-0.5 text-lg font-bold tabular-nums text-emerald-700 dark:text-emerald-300">
                                    {{ Util::displayAmount($balances['lent']) }}
                                </p>
                            </div>

                            <div class="rounded-lg bg-rose-50 px-3 py-2 dark:bg-rose-950/30">
                                <p class="text-xs font-medium text-rose-700 dark:text-rose-400">{{ __('You owe them') }}</p>
                                <p class="mt-0.5 text-lg font-bold tabular-nums text-rose-700 dark:text-rose-300">
                                    {{ Util::displayAmount($balances['owed']) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @php($people = $balances['borrowers']->concat($balances['lenders']))

                    @forelse ($people as $person)
                        @php($isOwedToYou = $person->amount > 0)

                        <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-2.5 first:border-t-0 sm:px-6 dark:border-gray-700">
                            @if ($person->icon)
                                <img src="{{ $person->icon->url }}" alt="" class="avatar h-8 w-8">
                            @else
                                <span class="avatar h-8 w-8 bg-gray-100 dark:bg-gray-700"></span>
                            @endif

                            <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-300">{{ $person->name }}</span>

                            <span class="shrink-0 text-right">
                                <span class="block text-sm font-semibold tabular-nums {{ $isOwedToYou
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ Util::displayAmount(abs($person->amount)) }}
                                </span>
                                <span class="block text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ $isOwedToYou ? __('owes you') : __('you owe') }}
                                </span>
                            </span>
                        </div>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-gray-500 sm:px-6">
                            {{ __('Nothing lent or borrowed.') }}
                            <a href="{{ route('contacts.index') }}" class="font-medium underline">{{ __('Add a contact') }}</a>.
                        </p>
                    @endforelse
                </x-ui.card>
            </div>

            <div class="grid gap-6">
                <!-- Recent activity -->
                <x-ui.card>
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Latest entries') }}</h3>
                        <a href="{{ route('transactions.index') }}" class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-400">
                            {{ __('See all') }}
                        </a>
                    </div>

                    @forelse ($recent as $transaction)
                        @php($isIncome = $transaction->type === TransactionType::Income)
                        @php($isTransfer = $transaction->type === TransactionType::Transfer)

                        <a href="{{ route('transactions.edit', $transaction) }}"
                           class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 transition first:border-t-0 hover:bg-gray-50 sm:px-6 dark:border-gray-700 dark:hover:bg-gray-900/40">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $transaction->title() }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $transaction->label() }}</p>
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="text-sm font-semibold tabular-nums {{ $isIncome
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : ($isTransfer ? 'text-gray-900 dark:text-gray-100' : 'text-rose-600 dark:text-rose-400') }}">
                                    {{ $isIncome ? '+' : ($isTransfer ? '' : '−') }}{{ Util::displayAmount($transaction->amount) }}
                                </p>
                                <p class="text-[11px] text-gray-400">{{ $transaction->created_at->isoFormat('D MMM, h:mm A') }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="px-4 py-10 text-center text-sm text-gray-500 sm:px-6">
                            {{ __('Nothing recorded yet.') }}
                            <a href="{{ route('transactions.create') }}" class="font-medium underline">{{ __('Add your first transaction') }}</a>.
                        </p>
                    @endforelse
                </x-ui.card>
            </div>
        </div>
    </div>
</x-app-layout>
