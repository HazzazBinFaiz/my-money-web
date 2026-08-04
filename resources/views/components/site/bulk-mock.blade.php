@php
    // The bulk grid, drawn in markup. Same reason as the list mock: no artwork
    // to re-cut when the branding changes.
    $rows = [
        ['sl' => 1, 'type' => __('Expense'), 'from' => __('Cash'), 'to' => null, 'category' => __('Groceries'), 'amount' => '86.30', 'charge' => '0', 'note' => __('Weekly shop'), 'balance' => '326.00'],
        ['sl' => 2, 'type' => __('Expense'), 'from' => __('Cash'), 'to' => null, 'category' => __('Coffee'), 'amount' => '4.50', 'charge' => '0', 'note' => '', 'balance' => '321.50'],
        ['sl' => 3, 'type' => __('Income'), 'from' => __('City Bank'), 'to' => null, 'category' => __('Freelance'), 'amount' => '250 * 2.5', 'charge' => '0', 'note' => __('Logo work'), 'balance' => '16,440.50'],
        ['sl' => 4, 'type' => __('Transfer'), 'from' => __('City Bank'), 'to' => __('Savings'), 'category' => null, 'amount' => '1000', 'charge' => '1.50', 'note' => __('Monthly saving'), 'balance' => '15,439.00 | 27,000.00'],
    ];

    $cell = 'border border-gray-200 px-2 py-1.5 dark:border-gray-800';
    $head = 'border border-gray-200 bg-gray-50 px-2 py-1.5 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-900';
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-900/10 dark:border-gray-800 dark:bg-gray-950 dark:shadow-black/40']) }}
     aria-hidden="true">

    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
        <div class="flex items-center gap-2">
            <x-application-logo class="h-6 w-6 rounded-lg" />
            <span class="text-sm font-semibold">{{ __('Add Bulk Transaction') }}</span>
        </div>
        <span class="hidden text-xs text-gray-500 sm:block dark:text-gray-400">{{ __('CSV import') }}</span>
    </div>

    <div class="overflow-x-auto p-3 sm:p-4">
        <table class="min-w-full border-collapse text-xs">
            <thead>
                <tr>
                    <th class="{{ $head }} w-8">{{ __('SL') }}</th>
                    <th class="{{ $head }}">{{ __('Type') }}</th>
                    <th class="{{ $head }}">{{ __('From') }}</th>
                    <th class="{{ $head }}">{{ __('To') }}</th>
                    <th class="{{ $head }}">{{ __('Category') }}</th>
                    <th class="{{ $head }} text-right">{{ __('Amount') }}</th>
                    <th class="{{ $head }} hidden xl:table-cell">{{ __('Note') }}</th>
                    <th class="{{ $head }} whitespace-nowrap text-right">{{ __('Balance') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="{{ $cell }} bg-gray-50 text-center text-[10px] text-gray-400 dark:bg-gray-900/60">{{ $row['sl'] }}</td>
                        <td class="{{ $cell }} whitespace-nowrap">{{ $row['type'] }}</td>
                        <td class="{{ $cell }} whitespace-nowrap">{{ $row['from'] }}</td>
                        <td class="{{ $cell }} whitespace-nowrap {{ $row['to'] ? '' : 'bg-gray-100 dark:bg-gray-800' }}">{{ $row['to'] }}</td>
                        <td class="{{ $cell }} whitespace-nowrap {{ $row['category'] ? '' : 'bg-gray-100 dark:bg-gray-800' }}">{{ $row['category'] }}</td>
                        <td class="{{ $cell }} whitespace-nowrap text-right font-medium">{{ $row['amount'] }}</td>
                        <td class="{{ $cell }} hidden whitespace-nowrap text-gray-500 xl:table-cell dark:text-gray-400">{{ $row['note'] }}</td>
                        <td class="{{ $cell }} whitespace-nowrap text-right text-gray-600 dark:text-gray-300">{{ $row['balance'] }}</td>
                    </tr>
                @endforeach

                <!-- The spare row sits back until it is used -->
                <tr class="opacity-40">
                    <td class="{{ $cell }} bg-gray-50 text-center text-[10px] text-gray-400 dark:bg-gray-900/60">5</td>
                    <td class="{{ $cell }}">{{ __('Expense') }}</td>
                    <td class="{{ $cell }}">—</td>
                    <td class="{{ $cell }} bg-gray-100 dark:bg-gray-800"></td>
                    <td class="{{ $cell }}">—</td>
                    <td class="{{ $cell }} text-right text-gray-400">0.00</td>
                    <td class="{{ $cell }} hidden xl:table-cell"></td>
                    <td class="{{ $cell }} text-right text-gray-300 dark:text-gray-600">—</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-[11px]">
            <span class="text-gray-500 dark:text-gray-400">4 {{ __('row(s) to save') }}</span>
            <span class="text-emerald-600 dark:text-emerald-400">{{ __('Income') }}: 625.00</span>
            <span class="text-rose-600 dark:text-rose-400">{{ __('Expense') }}: 90.80</span>
            <span class="text-gray-700 dark:text-gray-300">{{ __('Transfer') }}: 1,000.00</span>
        </div>
    </div>
</div>
