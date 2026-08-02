@php
    use App\Enums\TransactionType;

    $accountOptions = [
        __('Accounts') => $ownAccounts->map(fn ($account) => ['id' => $account->id, 'name' => $account->name])->all(),
        __('Contacts') => $contactAccounts->map(fn ($account) => ['id' => $account->id, 'name' => $account->name])->all(),
    ];

    $payload = [
        'rows' => array_values(old('rows', [])),
        'errors' => collect($errors->messages())->map(fn ($messages) => $messages[0])->all(),
        'accounts' => $ownAccounts->concat($contactAccounts)
            ->map(fn ($account) => ['id' => $account->id, 'name' => $account->name, 'amount' => $account->amount])
            ->values()
            ->all(),
        'incomeCategories' => $incomeCategories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->all(),
        'expenseCategories' => $expenseCategories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->all(),
    ];

    // Flat, borderless controls so each cell reads as a spreadsheet cell.
    $cell = 'h-8 w-full border-0 bg-transparent px-1.5 text-sm text-gray-900 focus:outline-none focus:ring-2
             focus:ring-inset focus:ring-gray-900/40 disabled:cursor-not-allowed disabled:bg-transparent
             disabled:text-transparent disabled:opacity-0 dark:text-gray-100 dark:focus:ring-white/40';
    $td = 'border border-gray-200 p-0 dark:border-gray-700';
    $th = 'border border-gray-200 bg-gray-50 px-1.5 py-1.5 text-left text-[11px] font-semibold uppercase tracking-wide
           text-gray-500 dark:border-gray-700 dark:bg-gray-900/70';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Add Bulk Transaction') }}
            </h2>
            <a href="{{ route('transactions.index') }}"
               class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-300">{{ __('Back to list') }}</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" x-data="bulkTransactions(@js($payload))">

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                    {{ __('Nothing was saved. Fix the highlighted cells and submit again.') }}
                </div>
            @endif

            <!-- Import summary -->
            <div x-show="importReport" x-cloak
                 class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">
                            <span x-text="importReport?.imported"></span> {{ __('row(s) imported.') }}
                        </p>
                        <p x-show="importReport?.unmatchedAccounts.length" class="mt-1 text-amber-600 dark:text-amber-400">
                            {{ __('Accounts not matched (left empty)') }}:
                            <span x-text="importReport?.unmatchedAccounts.join(', ')"></span>
                        </p>
                        <p x-show="importReport?.unmatchedCategories.length" class="mt-1 text-amber-600 dark:text-amber-400">
                            {{ __('Categories not matched (left empty)') }}:
                            <span x-text="importReport?.unmatchedCategories.join(', ')"></span>
                        </p>
                    </div>
                    <button type="button" @click="importReport = null" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>
            </div>

            <form method="POST" action="{{ route('transactions.bulk.store') }}" @submit="clearDraft()"
                  @paste.window="paste($event)">
                @csrf

                <x-ui.card>
                    <!-- Toolbar -->
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-3 py-2 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Paste from a spreadsheet, or type. Arrows/Enter move, Ctrl+D duplicates, Ctrl+Backspace deletes. Closing balance is an estimate applied in row order.') }}
                        </p>

                        <div class="ms-auto flex items-center gap-2">
                            <x-ui.button type="button" variant="ghost" class="!h-8 !px-2 !text-xs"
                                         @click="confirm('{{ __('Clear every row?') }}') && clearAll()">
                                {{ __('Clear all') }}
                            </x-ui.button>

                            <x-ui.button type="button" variant="outline" class="!h-8 !px-3 !text-xs" @click="openCsv()">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                                </svg>
                                {{ __('CSV import') }}
                            </x-ui.button>
                        </div>
                    </div>

                    <!-- Grid -->
                    <div class="overflow-x-auto" x-ref="grid">
                        <table class="min-w-full border-collapse text-sm">
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th class="{{ $th }} w-10">{{ __('SL') }}</th>
                                    <th class="{{ $th }} w-28">{{ __('Type') }}</th>
                                    <th class="{{ $th }} w-40">{{ __('From / Account') }}</th>
                                    <th class="{{ $th }} w-40">{{ __('To account') }}</th>
                                    <th class="{{ $th }} w-40">{{ __('Category') }}</th>
                                    <th class="{{ $th }} w-24">{{ __('Amount') }}</th>
                                    <th class="{{ $th }} w-20">{{ __('Charge') }}</th>
                                    <th class="{{ $th }} w-32">{{ __('Date') }}</th>
                                    <th class="{{ $th }} w-24">{{ __('Time') }}</th>
                                    <th class="{{ $th }} w-44">{{ __('Note') }}</th>
                                    <th class="{{ $th }} w-36 text-right">{{ __('Closing balance') }}</th>
                                    <th class="{{ $th }} w-16 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                <template x-for="(row, index) in rows" :key="index">
                                    <tr class="transition-opacity hover:bg-gray-50/60 focus-within:!opacity-100 dark:hover:bg-gray-900/30"
                                        :class="{
                                            'opacity-40': isBlank(row),
                                            'bg-red-50/70 dark:bg-red-950/30': isPartial(row),
                                        }">
                                        <td class="{{ $td }} bg-gray-50 px-1.5 text-center text-[11px] text-gray-400 dark:bg-gray-900/50"
                                            x-text="index + 1"></td>

                                        <!-- Type -->
                                        <td class="{{ $td }}">
                                            <select :name="`rows[${index}][type]`" x-model.number="row.type"
                                                    :data-cell="`${index}:type`"
                                                    @change="typeChanged(row); ensureDefaults(index)"
                                                    @keydown="navigate($event, index, 'type')"
                                                    class="{{ $cell }}">
                                                @foreach (TransactionType::cases() as $type)
                                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                                @endforeach
                                            </select>
                                        </td>

                                        <!-- Source / account -->
                                        <td class="{{ $td }}" :class="error(index, 'account_id') ? 'bg-red-50 dark:bg-red-950/40' : ''">
                                            <select :name="`rows[${index}][account_id]`" x-model="row.account_id"
                                                    :data-cell="`${index}:account_id`"
                                                    @change="ensureDefaults(index)"
                                                    @keydown="navigate($event, index, 'account_id')"
                                                    class="{{ $cell }}">
                                                <option value="">—</option>
                                                @foreach ($accountOptions as $groupLabel => $options)
                                                    @if (count($options))
                                                        <optgroup label="{{ $groupLabel }}">
                                                            @foreach ($options as $option)
                                                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </td>

                                        <!-- Destination (transfers only) -->
                                        <td class="{{ $td }}" :class="{
                                                'bg-gray-100 dark:bg-gray-800': ! isTransfer(row),
                                                'bg-red-50 dark:bg-red-950/40': error(index, 'to_account_id'),
                                            }">
                                            <select :name="`rows[${index}][to_account_id]`" x-model="row.to_account_id"
                                                    :disabled="! isTransfer(row)"
                                                    :data-cell="`${index}:to_account_id`"
                                                    @change="ensureDefaults(index)"
                                                    @keydown="navigate($event, index, 'to_account_id')"
                                                    class="{{ $cell }}">
                                                <option value="">—</option>
                                                @foreach ($accountOptions as $groupLabel => $options)
                                                    @if (count($options))
                                                        <optgroup label="{{ $groupLabel }}">
                                                            @foreach ($options as $option)
                                                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </td>

                                        <!-- Category (not for transfers) -->
                                        <td class="{{ $td }}" :class="{
                                                'bg-gray-100 dark:bg-gray-800': isTransfer(row),
                                                'bg-red-50 dark:bg-red-950/40': error(index, 'category_id'),
                                            }">
                                            <select :name="`rows[${index}][category_id]`" x-model="row.category_id"
                                                    :disabled="isTransfer(row)"
                                                    :data-cell="`${index}:category_id`"
                                                    @change="ensureDefaults(index)"
                                                    @keydown="navigate($event, index, 'category_id')"
                                                    class="{{ $cell }}">
                                                <option value="">—</option>
                                                <template x-for="category in categoriesFor(row)" :key="category.id">
                                                    <option :value="category.id" x-text="category.name"></option>
                                                </template>
                                            </select>
                                        </td>

                                        <!-- Amount -->
                                        <td class="{{ $td }}" :class="error(index, 'amount') ? 'bg-red-50 dark:bg-red-950/40' : ''">
                                            <input type="text" inputmode="decimal" placeholder="0.00" autocomplete="off"
                                                   :name="`rows[${index}][amount]`" x-model="row.amount"
                                                   :data-cell="`${index}:amount`"
                                                   @blur="resolveAmount(row, 'amount')"
                                                   @input="ensureDefaults(index)"
                                                   @keydown="navigate($event, index, 'amount')"
                                                   class="{{ $cell }} text-right">
                                        </td>

                                        <!-- Charge -->
                                        <td class="{{ $td }}" :class="error(index, 'charge') ? 'bg-red-50 dark:bg-red-950/40' : ''">
                                            <input type="text" inputmode="decimal" placeholder="0.00" autocomplete="off"
                                                   :name="`rows[${index}][charge]`" x-model="row.charge"
                                                   :data-cell="`${index}:charge`"
                                                   @blur="resolveAmount(row, 'charge')"
                                                   @input="ensureDefaults(index)"
                                                   @keydown="navigate($event, index, 'charge')"
                                                   class="{{ $cell }} text-right">
                                        </td>

                                        <!-- Date -->
                                        <td class="{{ $td }}" :class="error(index, 'date') ? 'bg-red-50 dark:bg-red-950/40' : ''">
                                            <input type="date" :name="`rows[${index}][date]`" x-model="row.date"
                                                   :data-cell="`${index}:date`"
                                                   @keydown="navigate($event, index, 'date')"
                                                   class="{{ $cell }}">
                                        </td>

                                        <!-- Time -->
                                        <td class="{{ $td }}" :class="error(index, 'time') ? 'bg-red-50 dark:bg-red-950/40' : ''">
                                            <input type="time" :name="`rows[${index}][time]`" x-model="row.time"
                                                   :data-cell="`${index}:time`"
                                                   @keydown="navigate($event, index, 'time')"
                                                   class="{{ $cell }}">
                                        </td>

                                        <!-- Note -->
                                        <td class="{{ $td }}">
                                            <input type="text" :name="`rows[${index}][note]`" x-model="row.note"
                                                   :data-cell="`${index}:note`"
                                                   @input="ensureDefaults(index)"
                                                   @keydown="navigate($event, index, 'note')"
                                                   class="{{ $cell }}">
                                        </td>

                                        <!-- Estimated closing balance of the row's account -->
                                        <td class="{{ $td }} px-1.5 text-right text-xs">
                                            <template x-if="closingBalance(index) === null">
                                                <span class="text-gray-300 dark:text-gray-600">—</span>
                                            </template>

                                            <template x-if="closingBalance(index) !== null">
                                                <span class="whitespace-nowrap">
                                                    <span :class="closingBalance(index) < 0
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : 'text-gray-600 dark:text-gray-300'"
                                                          x-text="format(closingBalance(index))"></span>

                                                    <template x-if="closingBalanceTo(index) !== null">
                                                        <span>
                                                            <span class="text-gray-300 dark:text-gray-600">|</span>
                                                            <span :class="closingBalanceTo(index) < 0
                                                                ? 'text-red-600 dark:text-red-400'
                                                                : 'text-gray-600 dark:text-gray-300'"
                                                                  x-text="format(closingBalanceTo(index))"></span>
                                                        </span>
                                                    </template>
                                                </span>
                                            </template>
                                        </td>

                                        <!-- Row actions -->
                                        <td class="{{ $td }} px-1">
                                            <div class="flex items-center justify-end gap-0.5">
                                                <button type="button" @click="duplicate(index)" title="{{ __('Duplicate row (Ctrl+D)') }}"
                                                        class="rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                    </svg>
                                                </button>

                                                <button type="button" @click="remove(index)" title="{{ __('Delete row (Ctrl+Backspace)') }}"
                                                        class="rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/50">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Add rows (below the grid) -->
                    <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 px-3 py-2 dark:border-gray-700">
                        <input type="number" min="1" max="100" x-model.number="addCount"
                               @keydown.enter.prevent="addRows()"
                               class="h-8 w-16 rounded-md border border-gray-200 bg-white px-2 text-sm shadow-sm
                                      focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10
                                      dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">

                        <x-ui.button type="button" variant="outline" class="!h-8 !px-3 !text-xs" @click="addRows()">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ __('Add Row') }}
                        </x-ui.button>
                    </div>

                    <!-- Totals -->
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1 border-t border-gray-100 px-3 py-2 text-xs dark:border-gray-700">
                        <span class="text-gray-500 dark:text-gray-400">
                            <span x-text="totals().filled"></span> {{ __('row(s) to save') }}
                        </span>
                        <span class="text-emerald-600 dark:text-emerald-400">
                            {{ __('Income') }}: <span x-text="format(totals().income)"></span>
                        </span>
                        <span class="text-rose-600 dark:text-rose-400">
                            {{ __('Expense') }}: <span x-text="format(totals().expense)"></span>
                        </span>
                        <span class="text-gray-700 dark:text-gray-300">
                            {{ __('Transfer') }}: <span x-text="format(totals().transfer)"></span>
                        </span>
                        <span x-show="totals().partial" x-cloak class="text-red-600 dark:text-red-400">
                            <span x-text="totals().partial"></span> {{ __('incomplete row(s)') }}
                        </span>
                    </div>
                </x-ui.card>

                <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <a href="{{ route('transactions.index') }}"
                       class="inline-flex h-10 items-center justify-center rounded-md border border-gray-200 px-4 text-sm font-medium
                              text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                        {{ __('Cancel') }}
                    </a>
                    <x-ui.button class="w-full sm:w-auto">
                        <span x-text="`{{ __('Save') }} ${totals().filled} {{ __('transaction(s)') }}`"></span>
                    </x-ui.button>
                </div>

                <!-- CSV import dialog -->
                <x-ui.dialog show="csvOpen" :title="__('Import from CSV')">
                    <div class="space-y-4 text-sm">
                        <p class="text-gray-600 dark:text-gray-400">
                            {{ __('Columns: Type, Account, To Account, Category, Amount, Charge, Date, Time, Note. Accounts and categories are matched by their exact name; anything unmatched is left empty for you to pick.') }}
                        </p>

                        <x-ui.button type="button" variant="outline" class="!h-9 !text-xs" @click="downloadTemplate()">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                            </svg>
                            {{ __('Download template CSV') }}
                        </x-ui.button>

                        <input type="file" accept=".csv,text/csv" x-ref="csvInput" @change="csvChosen($event)"
                               class="block w-full cursor-pointer rounded-md border border-gray-200 p-2 text-sm text-gray-600
                                      file:mr-3 file:rounded-md file:border-0 file:bg-gray-900 file:px-3 file:py-1.5 file:text-sm
                                      file:font-medium file:text-white dark:border-gray-700 dark:text-gray-300
                                      dark:file:bg-white dark:file:text-gray-900">

                        <div class="flex justify-end gap-2">
                            <x-ui.button type="button" variant="ghost" @click="csvOpen = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="button" ::disabled="! csvFileName" @click="fillFromCsv()">{{ __('Fill') }}</x-ui.button>
                        </div>
                    </div>
                </x-ui.dialog>
            </form>
        </div>
    </div>
</x-app-layout>
