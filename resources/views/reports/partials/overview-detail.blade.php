@php
    use App\Enums\CategoryType;
    use App\Lib\Util;

    $category = $detail['subject'];
    $isIncome = $category->type === CategoryType::Income;

    $money = $isIncome
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-rose-600 dark:text-rose-400';
@endphp

<div class="p-4 sm:p-6">
    <!-- Period first: every figure below is only true inside it -->
    <p class="text-center text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $range->label() }}</p>

    <div class="mt-3 flex items-center justify-center gap-3">
        @if ($category->icon)
            <img src="{{ $category->icon->url }}" alt="" class="avatar h-8 w-8">
        @endif
        <span class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $category->name }}</span>
    </div>

    <p class="mt-2 text-center text-5xl font-bold tabular-nums {{ $money }}">
        {{ number_format($detail['share'] * 100, 1) }}%
    </p>

    <p class="mt-1 text-center text-xs text-gray-500 dark:text-gray-400">
        {{ $isIncome ? __('of income in this period') : __('of spending in this period') }}
        · {{ Util::displayAmount($detail['overall']) }}
    </p>

    <div class="mt-5 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="flex items-baseline justify-between bg-gray-50 px-4 py-3 dark:bg-gray-800/60">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $isIncome ? __('Total income') : __('Total expense') }}
            </span>
            <span class="text-lg font-bold tabular-nums {{ $money }}">{{ Util::displayAmount($detail['total']) }}</span>
        </div>

        <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-gray-800">
            <div class="px-4 py-2.5">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Entries') }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $detail['count'] }}</p>
            </div>

            <div class="px-4 py-2.5 text-right">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Average') }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                    {{ Util::displayAmount($detail['average']) }}
                </p>
            </div>
        </div>
    </div>

    <h4 class="mb-2 mt-6 text-sm font-semibold text-gray-700 dark:text-gray-300">
        {{ trans_choice(':count transaction|:count transactions', $detail['transactions']->count(), ['count' => $detail['transactions']->count()]) }}
    </h4>

    <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
        @forelse ($detail['transactions'] as $transaction)
            <a href="{{ route('transactions.edit', $transaction) }}"
               class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-gray-50 dark:hover:bg-gray-800/60">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ ($isIncome ? $transaction->toAccount?->name : $transaction->fromAccount?->name) ?? $transaction->label() }}
                    </p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $transaction->created_at->isoFormat('D MMM YYYY, h:mm A') }}
                        @if ($transaction->note)
                            · {{ $transaction->note }}
                        @endif
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    <p class="text-sm font-semibold tabular-nums {{ $money }}">
                        {{ $isIncome ? '+' : '−' }}{{ Util::displayAmount($transaction->amount) }}
                    </p>

                    @if ($transaction->charge > 0)
                        <p class="text-[11px] text-gray-500">{{ __('Charge') }}: {{ Util::displayAmount($transaction->charge) }}</p>
                    @endif
                </div>
            </a>
        @empty
            <p class="px-4 py-8 text-center text-sm text-gray-500">{{ __('Nothing recorded for this category in the range.') }}</p>
        @endforelse
    </div>
</div>
