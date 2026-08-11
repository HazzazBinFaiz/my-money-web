@php
    use App\Enums\CategoryType;
    use App\Lib\Util;

    $isIncome = $type === CategoryType::Income;
    $rows = $overview['rows'];

    $money = $isIncome
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-rose-600 dark:text-rose-400';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ $isIncome ? __('Income Overview') : __('Expense Overview') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-8" x-data="reportDetail()">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <x-range-filter :range="$range" :route="$route" />

            {{-- Account only: filtering the pie to one category would leave one slice. --}}
            <x-report.filters :route="$route" :range="$range" :filter="$filter"
                              :accounts="$filterAccounts" :income="$isIncome" />

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Showing') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ $range->label() }}</span>
                · {{ trans_choice(':count category|:count categories', $overview['count'], ['count' => $overview['count']]) }}
                · <span class="font-semibold {{ $money }}">{{ Util::displayAmount($overview['total']) }}</span>
                {{ $isIncome ? __('in total') : __('spent') }}
            </p>

            <x-chart.pie :rows="$rows"
                         :total="$overview['total']"
                         :income="$isIncome"
                         :title="$isIncome ? __('Where the income came from') : __('Where the money went')"
                         :subtitle="$isIncome
                            ? __('Each slice is one category\'s share of income, net of charges.')
                            : __('Each slice is one category\'s share of spending, charges included.')"
                         :empty="$isIncome ? __('No income in this range.') : __('No spending in this range.')" />

            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Category') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">
                                    {{ $isIncome ? __('Income') : __('Expense') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Share') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($rows as $row)
                                @php
                                    // The transfer-charge row has no category behind it, so there is
                                    // nothing to drill into.
                                    $url = $row['category']
                                        ? route('reports.overview.detail', $row['category']) . '?' . http_build_query(request()->query())
                                        : null;
                                @endphp

                                <tr class="{{ $url ? 'cursor-pointer transition hover:bg-gray-50/60 dark:hover:bg-gray-900/30' : '' }}"
                                    @if ($url)
                                        tabindex="0"
                                        @click="open(@js($url))"
                                        @keydown.enter="open(@js($url))"
                                    @endif>
                                    <td class="px-4 py-3 sm:px-6">
                                        <div class="flex items-center gap-3">
                                            @if ($row['category']?->icon)
                                                <img src="{{ $row['category']->icon->url }}" alt="" class="avatar h-9 w-9">
                                            @else
                                                <span class="avatar h-9 w-9 bg-gray-100 dark:bg-gray-700"></span>
                                            @endif

                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] }}</span>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold tabular-nums sm:px-6 {{ $money }}">
                                        {{ Util::displayAmount($row['total']) }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm tabular-nums text-gray-500 sm:px-6 dark:text-gray-400">
                                        {{ number_format($row['share'] * 100, 1) }}%
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">
                                        {{ $isIncome ? __('No income in this range.') : __('No spending in this range.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>

        <x-report.modal />
    </div>
</x-app-layout>
