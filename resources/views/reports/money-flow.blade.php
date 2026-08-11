@php
    use App\Lib\Util;

    $unspent = $flow['total_in'] - $flow['total_out'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Money Flow') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <x-range-filter :range="$range" route="reports.money-flow" />

            <x-report.filters route="reports.money-flow" :range="$range" :filter="$filter"
                              :accounts="$filterAccounts" :categories="$filterCategories" both />

            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ __('Showing') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ $range->label() }}</span></span>
                <span>· {{ __('In') }} <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ Util::displayAmount($flow['total_in']) }}</span></span>
                <span>· {{ __('Out') }} <span class="font-semibold text-rose-600 dark:text-rose-400">{{ Util::displayAmount($flow['total_out']) }}</span></span>

                @if ($unspent !== 0)
                    <span>· {{ $unspent > 0 ? __('Kept') : __('Drawn from balances') }}
                        <span class="font-semibold text-gray-700 dark:text-gray-300">{{ Util::displayAmount(abs($unspent)) }}</span></span>
                @endif
            </div>

            <x-chart.sankey :flow="$flow"
                            :title="__('Where the money came from, and where it went')"
                            :subtitle="__('Income categories on the left, the accounts they landed in down the middle, spending on the right. A transfer loops from one account to another: out of the bottom of the sending side, into the top of the receiving one.')"
                            :empty="__('Nothing moved in this range.')" />

            {{-- The same numbers without a hover, and the only readable form on a phone --}}
            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('From') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('To') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Amount') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @php
                                $names = collect([$flow['income'], $flow['accounts'], $flow['expense']])
                                    ->flatten(1)
                                    ->pluck('name', 'id');
                            @endphp

                            @forelse ($flow['links'] as $link)
                                <tr>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 sm:px-6 dark:text-gray-300">{{ $names[$link['source']] ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 sm:px-6 dark:text-gray-300">{{ $names[$link['target']] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right text-sm font-semibold tabular-nums sm:px-6 {{ match ($link['side']) {
                                        'income' => 'text-emerald-600 dark:text-emerald-400',
                                        // A transfer is neither, so it is neither colour.
                                        'transfer' => 'text-gray-600 dark:text-gray-300',
                                        default => 'text-rose-600 dark:text-rose-400',
                                    } }}">
                                        {{ Util::displayAmount($link['value']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">
                                        {{ __('Nothing moved in this range.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
