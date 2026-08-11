@php
    use App\Enums\CategoryType;
    use App\Enums\TransactionType;
    use App\Lib\Util;

    $isIncome = $type === CategoryType::Income;
    $summary = app(App\Services\ReportSummary::class);

    $money = $isIncome
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-rose-600 dark:text-rose-400';
@endphp

<div class="p-4 sm:p-6">
    <div class="flex items-baseline justify-between gap-3 border-b border-gray-100 pb-3 pe-8 dark:border-gray-800">
        <div>
            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $day['date']->isoFormat('dddd, D MMMM YYYY') }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ trans_choice(':count transaction|:count transactions', $day['transactions']->count(), ['count' => $day['transactions']->count()]) }}
            </p>
        </div>

        <span class="shrink-0 text-lg font-bold tabular-nums {{ $money }}">{{ Util::displayAmount($day['total']) }}</span>
    </div>

    <div class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
        @forelse ($day['transactions'] as $transaction)
            @php
                $isCharge = $transaction->type === TransactionType::Transfer;
                $value = $summary->flowValue($transaction, $isIncome);
            @endphp

            <a href="{{ route('transactions.edit', $transaction) }}"
               class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-gray-50 dark:hover:bg-gray-800/60">
                @if ($transaction->category?->icon)
                    <img src="{{ $transaction->category->icon->url }}" alt="" class="avatar h-8 w-8">
                @else
                    <span class="avatar h-8 w-8 bg-gray-100 dark:bg-gray-700"></span>
                @endif

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $isCharge ? __('Transfer charge') : ($transaction->category?->name ?? $transaction->label()) }}
                    </p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $transaction->created_at->isoFormat('h:mm A') }}
                        · {{ ($isIncome ? $transaction->toAccount?->name : $transaction->fromAccount?->name) ?? '—' }}
                        @if ($transaction->note)
                            · {{ $transaction->note }}
                        @endif
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    <p class="text-sm font-semibold tabular-nums {{ $money }}">
                        {{ $isIncome ? '+' : '−' }}{{ Util::displayAmount($value) }}
                    </p>

                    @if (! $isCharge && $transaction->charge > 0)
                        <p class="text-[11px] text-gray-500">{{ __('Charge') }}: {{ Util::displayAmount($transaction->charge) }}</p>
                    @endif
                </div>
            </a>
        @empty
            <p class="px-4 py-8 text-center text-sm text-gray-500">{{ __('Nothing recorded on this day.') }}</p>
        @endforelse
    </div>
</div>
