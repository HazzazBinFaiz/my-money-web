@php
    use App\Enums\CategoryType;
    use App\Lib\Util;

    $isIncome = $type === CategoryType::Income;
    $days = $flow['days'];

    $money = $isIncome
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-rose-600 dark:text-rose-400';

    $active = count($days);
    $average = $active > 0 ? (int) round($flow['total'] / $active) : 0;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ $isIncome ? __('Income Flow') : __('Expense Flow') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-8" x-data="reportDetail()">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- No "All time" here: a calendar needs both ends of the range. --}}
            <x-range-filter :range="$range" :route="$route"
                            :only="['this_month', 'last_month', 'last_30', 'this_year']" />

            <x-report.filters :route="$route" :range="$range" :filter="$filter"
                              :accounts="$filterAccounts" :categories="$filterCategories"
                              :income="$isIncome" />

            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ __('Showing') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ $range->label() }}</span></span>
                <span>· <span class="font-semibold {{ $money }}">{{ Util::displayAmount($flow['total']) }}</span>
                    {{ $isIncome ? __('in income') : __('in expense') }}</span>
                <span>· {{ trans_choice(':count active day|:count active days', $active, ['count' => $active]) }}</span>
                <span>· {{ __('Average') }} {{ Util::displayAmount($average) }}</span>

                @if ($flow['busiest'])
                    <span>· {{ $isIncome ? __('Best day') : __('Heaviest day') }}
                        {{ \Carbon\CarbonImmutable::parse($flow['busiest'])->isoFormat('D MMM') }}
                        ({{ Util::displayAmount($flow['max']) }})</span>
                @endif
            </div>

            @if ($flow['total'] === 0)
                <x-ui.card>
                    <p class="px-4 py-10 text-center text-sm text-gray-500">
                        {{ $isIncome ? __('No income in this range.') : __('No expense in this range.') }}
                    </p>
                </x-ui.card>
            @endif

            <x-chart.calendar :range="$range"
                              :days="$days"
                              :max="$flow['max']"
                              :income="$isIncome"
                              :type="$isIncome ? 'income' : 'expense'"
                              :query="$filter->toQuery()" />
        </div>

        <x-report.modal />
    </div>
</x-app-layout>
