@props([
    'transaction' => null,
    'action',
    'submit',
    'ownAccounts',
    'contactAccounts',
    'incomeCategories',
    'expenseCategories',
])

@php
    use App\Enums\TransactionType;

    $accountGroups = [
        [
            'label' => __('Accounts'),
            'options' => $ownAccounts->map(fn ($account) => [
                'value' => $account->id,
                'label' => $account->name,
                'icon' => $account->icon?->url,
            ])->all(),
        ],
        [
            'label' => __('Contacts'),
            'options' => $contactAccounts->map(fn ($account) => [
                'value' => $account->id,
                'label' => $account->name,
                'icon' => $account->icon?->url,
            ])->all(),
        ],
    ];

    $categoryGroup = fn ($categories, $label) => [[
        'label' => $label,
        'options' => $categories->map(fn ($category) => [
            'value' => $category->id,
            'label' => $category->name,
            'icon' => $category->icon?->url,
        ])->all(),
    ]];
@endphp

            <div class="mx-auto max-w-3xl"
                 x-data="transactionForm(@js([
                    'type' => (int) old('type', $transaction?->type->value ?? TransactionType::Expense->value),
                    'amount' => old('amount', $transaction ? \App\Lib\Util::toMajorUnits($transaction->amount) : ''),
                    'charge' => old('charge', $transaction ? \App\Lib\Util::toMajorUnits($transaction->charge) : ''),
                 ]))">

                <x-ui.card>
                    <form method="POST" action="{{ $action }}" class="p-4 sm:p-6">
                        @csrf
                        @if ($transaction)
                            @method('PUT')
                        @endif
                        <input type="hidden" name="type" :value="type">

                        <!-- Type tabs -->
                        <div class="grid grid-cols-3 gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-900">
                            @foreach (TransactionType::cases() as $transactionType)
                                <button type="button" @click="type = {{ $transactionType->value }}"
                                        :class="type === {{ $transactionType->value }}
                                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                            : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                                        class="rounded-md px-3 py-2 text-sm font-medium transition">
                                    {{ $transactionType->label() }}
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Account -->
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium leading-none text-gray-700 dark:text-gray-300"
                                       x-text="type === {{ TransactionType::Transfer->value }} ? '{{ __('From account') }}' : '{{ __('Account') }}'"></label>

                                <x-ui.option-picker name="account_id" :groups="$accountGroups" :value="old('account_id', $transaction?->primaryAccount()?->id)"
                                                    :placeholder="__('Select account')" />

                                <x-input-error :messages="$errors->get('account_id')" class="!text-xs" />
                            </div>

                            <!-- Category (income / expense) or destination account (transfer) -->
                            <div>
                                <template x-if="type === {{ TransactionType::Income->value }}">
                                    <x-ui.field :label="__('Category')" :error="$errors->get('category_id')">
                                        <x-ui.option-picker name="category_id"
                                                            :groups="$categoryGroup($incomeCategories, __('Income categories'))"
                                                            :value="old('category_id', $transaction?->category_id)"
                                                            :placeholder="__('Select category')" />
                                    </x-ui.field>
                                </template>

                                <template x-if="type === {{ TransactionType::Expense->value }}">
                                    <x-ui.field :label="__('Category')" :error="$errors->get('category_id')">
                                        <x-ui.option-picker name="category_id"
                                                            :groups="$categoryGroup($expenseCategories, __('Expense categories'))"
                                                            :value="old('category_id', $transaction?->category_id)"
                                                            :placeholder="__('Select category')" />
                                    </x-ui.field>
                                </template>

                                <template x-if="type === {{ TransactionType::Transfer->value }}">
                                    <x-ui.field :label="__('To account')" :error="$errors->get('to_account_id')">
                                        <x-ui.option-picker name="to_account_id" :groups="$accountGroups" :value="old('to_account_id', $transaction?->to_account_id)"
                                                            :placeholder="__('Select account')" />
                                    </x-ui.field>
                                </template>
                            </div>

                            <!-- Amount -->
                            <x-ui.field :label="__('Amount')" for="amount" :error="$errors->get('amount')"
                                        :hint="__('Math works too: 10 * 2 or (4 * 4)+54')">
                                <x-ui.input id="amount" name="amount" type="text" inputmode="decimal"
                                            x-model="amount" @blur="resolve('amount')"
                                            placeholder="0.00" autocomplete="off" required />
                            </x-ui.field>

                            <!-- Charge -->
                            <x-ui.field :label="__('Charge')" for="charge" :error="$errors->get('charge')"
                                        :hint="__('Optional fee taken from the source account.')">
                                <x-ui.input id="charge" name="charge" type="text" inputmode="decimal"
                                            x-model="charge" @blur="resolve('charge')"
                                            placeholder="0.00" autocomplete="off" />
                            </x-ui.field>

                            <!-- Note -->
                            <div class="sm:col-span-2">
                                <x-ui.field :label="__('Note')" for="note" :error="$errors->get('note')">
                                    <textarea id="note" name="note" rows="2"
                                              class="flex w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm
                                                     transition placeholder:text-gray-400 focus:border-gray-400 focus:outline-none focus:ring-2
                                                     focus:ring-gray-900/10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100
                                                     dark:focus:ring-white/10"
                                              placeholder="{{ __('Optional') }}">{{ old('note', $transaction?->note) }}</textarea>
                                </x-ui.field>
                            </div>

                            <!-- Date and time -->
                            <x-ui.field :label="__('Date')" for="date" :error="$errors->get('date')">
                                <x-ui.input id="date" name="date" type="date"
                                            :value="old('date', ($transaction?->created_at ?? now())->format('Y-m-d'))" required />
                            </x-ui.field>

                            <x-ui.field :label="__('Time')" for="time" :error="$errors->get('time')">
                                <x-ui.input id="time" name="time" type="time"
                                            :value="old('time', ($transaction?->created_at ?? now())->format('H:i'))" required />
                            </x-ui.field>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <a href="{{ route('transactions.index') }}"
                               class="inline-flex h-10 items-center justify-center rounded-md border border-gray-200 px-4 text-sm font-medium
                                      text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                                {{ __('Cancel') }}
                            </a>
                            <x-ui.button class="w-full sm:w-auto">{{ $submit }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>
