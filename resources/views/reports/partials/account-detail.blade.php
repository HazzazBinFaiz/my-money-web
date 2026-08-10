@php
    use App\Enums\TransactionType;
    use App\Lib\Util;

    $account = $detail['subject'];
@endphp

<div class="p-4 sm:p-6">
    <!-- Summary card -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
            @if ($account->icon)
                <img src="{{ $account->icon->url }}" alt="" class="avatar h-10 w-10">
            @else
                <span class="avatar h-10 w-10 bg-gray-100 dark:bg-gray-700"></span>
            @endif

            <div class="min-w-0">
                <p class="truncate text-base font-semibold text-gray-900 dark:text-gray-100">{{ $account->name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $range->label() }}</p>
            </div>
        </div>

        <dl class="divide-y divide-gray-100 dark:divide-gray-800">
            <div class="flex items-baseline justify-between px-4 py-2.5">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Starting balance') }}</dt>
                <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                    {{ Util::displayAmount($detail['opening']) }}
                </dd>
            </div>

            <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-gray-800">
                <div class="px-4 py-2.5">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total expense') }}</dt>
                    <dd class="mt-0.5 text-base font-semibold tabular-nums text-rose-600 dark:text-rose-400">
                        {{ Util::displayAmount($detail['expense']) }}
                    </dd>
                </div>

                <div class="px-4 py-2.5 text-right">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total income') }}</dt>
                    <dd class="mt-0.5 text-base font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                        {{ Util::displayAmount($detail['income']) }}
                    </dd>
                </div>
            </div>

            <div class="flex items-baseline justify-between px-4 py-2.5">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Transfer out') }}</dt>
                <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                    {{ Util::displayAmount($detail['transfer_out']) }}
                </dd>
            </div>

            {{-- Money arriving by transfer, without which the closing figure would not add up. --}}
            <div class="flex items-baseline justify-between px-4 py-2.5">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Transfer in') }}</dt>
                <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                    {{ Util::displayAmount($detail['transfer_in']) }}
                </dd>
            </div>

            <div class="flex items-baseline justify-between px-4 py-2.5">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Transfer charge') }}</dt>
                <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                    {{ Util::displayAmount($detail['transfer_charge']) }}
                </dd>
            </div>

            <div class="flex items-baseline justify-between bg-gray-50 px-4 py-3 dark:bg-gray-800/60">
                <dt class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('End balance') }}</dt>
                <dd class="text-lg font-bold tabular-nums {{ $detail['closing'] < 0
                    ? 'text-rose-600 dark:text-rose-400'
                    : 'text-gray-900 dark:text-gray-100' }}">
                    {{ Util::displayAmount($detail['closing']) }}
                </dd>
            </div>
        </dl>
    </div>

    <!-- Transactions -->
    <h4 class="mb-2 mt-6 text-sm font-semibold text-gray-700 dark:text-gray-300">
        {{ trans_choice(':count transaction|:count transactions', $detail['transactions']->count(), ['count' => $detail['transactions']->count()]) }}
    </h4>

    <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
        @forelse ($detail['transactions'] as $transaction)
            @php
                $isTransfer = $transaction->type === TransactionType::Transfer;
                $isIncome = $transaction->type === TransactionType::Income;
                // Read from this account's side of the entry.
                $leaving = $transaction->from_account_id === $account->id;
            @endphp

            <a href="{{ route('transactions.edit', $transaction) }}"
               class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-gray-50 dark:hover:bg-gray-800/60">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $transaction->title() }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $transaction->created_at->isoFormat('D MMM YYYY, h:mm A') }}
                        @if ($transaction->note)
                            · {{ $transaction->note }}
                        @endif
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    <p class="text-sm font-semibold tabular-nums {{ $isTransfer
                        ? 'text-gray-900 dark:text-gray-100'
                        : ($isIncome ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400') }}">
                        {{ $leaving ? '−' : '+' }}{{ Util::displayAmount($transaction->amount) }}
                    </p>

                    @if ($transaction->charge > 0)
                        <p class="text-[11px] text-gray-500">{{ __('Charge') }}: {{ Util::displayAmount($transaction->charge) }}</p>
                    @endif
                </div>
            </a>
        @empty
            <p class="px-4 py-8 text-center text-sm text-gray-500">{{ __('Nothing recorded for this account in the range.') }}</p>
        @endforelse
    </div>
</div>
