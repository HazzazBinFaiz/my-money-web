@php
    use App\Enums\CategoryType;
    use App\Lib\Util;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Category Analysis') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-8" x-data="reportDetail()">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <x-range-filter :range="$range" route="reports.categories" />

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Showing') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ $range->label() }}</span>
                · {{ trans_choice(':count category|:count categories', $rows->count(), ['count' => $rows->count()]) }}
            </p>

            <x-chart.grouped :title="__('Income and expense per category')"
                             :subtitle="__('A category only ever carries one of the two, so each bar stands alone.')"
                             :empty="__('No category activity in this range.')"
                             :rows="$rows->map(fn ($row) => [
                                'label' => $row['category']->name,
                                'income' => $row['income'],
                                'expense' => $row['expense'],
                             ])" />

            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Category') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Income') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Expense') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($rows as $row)
                                <tr class="cursor-pointer transition hover:bg-gray-50/60 dark:hover:bg-gray-900/30"
                                    tabindex="0"
                                    @click="open(@js(route('reports.categories.detail', $row['category']) . '?' . http_build_query(request()->query())))"
                                    @keydown.enter="open(@js(route('reports.categories.detail', $row['category']) . '?' . http_build_query(request()->query())))">
                                    <td class="px-4 py-3 sm:px-6">
                                        <div class="flex items-center gap-3">
                                            @if ($row['category']->icon)
                                                <img src="{{ $row['category']->icon->url }}" alt="" class="avatar h-9 w-9">
                                            @else
                                                <span class="avatar h-9 w-9 bg-gray-100 dark:bg-gray-700"></span>
                                            @endif

                                            <span>
                                                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['category']->name }}</span>
                                                <span class="block text-[11px] text-gray-500">{{ $row['category']->type->label() }}</span>
                                            </span>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold tabular-nums text-emerald-600 sm:px-6 dark:text-emerald-400">
                                        {{ Util::displayAmount($row['income']) }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold tabular-nums text-rose-600 sm:px-6 dark:text-rose-400">
                                        {{ Util::displayAmount($row['expense']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">
                                        {{ __('No category activity in this range.') }}
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
