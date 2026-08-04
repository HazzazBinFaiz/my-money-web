@php
    use App\Enums\AccountType;
    use App\Enums\CategoryType;
    use App\Enums\TransactionType;
    use App\Lib\Util;

    $hasFilters = $accountId || $categoryId;

    // Grouped once here so the filter selects stay simple markup.
    $accountGroups = [
        __('Accounts') => $filterAccounts->where('type', AccountType::Account),
        __('Contacts') => $filterAccounts->where('type', AccountType::Contact),
    ];

    $categoryGroups = [
        CategoryType::Income->label() => $filterCategories->where('type', CategoryType::Income),
        CategoryType::Expense->label() => $filterCategories->where('type', CategoryType::Expense),
    ];

    $transferIcon = asset('images/transfer.png');

    $grouped = $transactions->getCollection()->groupBy(fn ($transaction) => $transaction->created_at->toDateString());
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Transactions') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

            <!-- Toolbar -->
            <div class="flex items-center justify-between gap-3">
                <div class="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-gray-900">
                    @foreach (['cards' => __('List'), 'table' => __('Table')] as $key => $label)
                        <a href="{{ route('transactions.index', array_filter(['view' => $key, 'account' => $accountId, 'category' => $categoryId])) }}"
                           class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $view === $key
                               ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                               : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('transactions.create') }}"
                       class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-gray-900 px-4 text-sm font-medium text-white
                              shadow-sm transition hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ __('Add Transaction') }}
                    </a>

                    <a href="{{ route('transactions.bulk') }}"
                       class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-gray-200 bg-white px-4 text-sm
                              font-medium text-gray-900 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900
                              dark:text-gray-100 dark:hover:bg-gray-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        {{ __('Add Bulk Transaction') }}
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <x-ui.card>
                <form method="GET" action="{{ route('transactions.index') }}"
                      class="flex flex-wrap items-end gap-3 p-3 sm:p-4">
                    <input type="hidden" name="view" value="{{ $view }}">

                    <x-ui.field :label="__('Account')" class="w-full sm:w-56">
                        <x-ui.select name="account" @change="$el.form.submit()" x-data>
                            <option value="">{{ __('All accounts') }}</option>
                            @foreach ($accountGroups as $groupLabel => $group)
                                @if ($group->isNotEmpty())
                                    <optgroup label="{{ $groupLabel }}">
                                        @foreach ($group as $account)
                                            <option value="{{ $account->id }}" @selected($accountId == $account->id)>{{ $account->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field :label="__('Category')" class="w-full sm:w-56">
                        <x-ui.select name="category" @change="$el.form.submit()" x-data>
                            <option value="">{{ __('All categories') }}</option>
                            @foreach ($categoryGroups as $groupLabel => $group)
                                @if ($group->isNotEmpty())
                                    <optgroup label="{{ $groupLabel }}">
                                        @foreach ($group as $category)
                                            <option value="{{ $category->id }}" @selected($categoryId == $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.button variant="outline" class="!h-10">{{ __('Apply') }}</x-ui.button>

                    @if ($hasFilters)
                        <a href="{{ route('transactions.index', ['view' => $view]) }}"
                           class="inline-flex h-10 items-center text-sm font-medium text-gray-500 hover:underline dark:text-gray-400">
                            {{ __('Clear') }}
                        </a>

                        <span class="ms-auto text-xs text-gray-500 dark:text-gray-400">
                            {{ trans_choice(':count matching transaction|:count matching transactions', $transactions->total(), ['count' => $transactions->total()]) }}
                        </span>
                    @endif
                </form>
            </x-ui.card>

            @if ($transactions->isEmpty())
                <x-ui.card>
                    <p class="px-6 py-12 text-center text-sm text-gray-500">
                        {{ $hasFilters ? __('No transactions match these filters.') : __('No transactions yet.') }}
                    </p>
                </x-ui.card>
            @elseif ($view === 'cards')
                <!-- Grouped list view -->
                <div class="space-y-6">
                    @foreach ($grouped as $date => $rows)
                        <div>
                            <div class="mb-2">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ \Carbon\Carbon::parse($date)->isoFormat('ddd, D MMM YYYY') }}
                                </p>
                                <hr class="mt-1 border-gray-200 dark:border-gray-700">
                            </div>

                            <x-ui.card class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($rows as $transaction)
                                    @php
                                        $isTransfer = $transaction->type === TransactionType::Transfer;
                                        $isIncome = $transaction->type === TransactionType::Income;
                                    @endphp

                                    <div class="flex items-center gap-3 px-3 py-2.5 sm:gap-5 sm:px-6 sm:py-4">
                                        <!-- Leading icon -->
                                        <div class="shrink-0">
                                            @if ($isTransfer)
                                                <img src="{{ $transferIcon }}" alt="{{ __('Transfer') }}"
                                                     class="h-10 w-10 avatar rounded-full object-cover sm:h-12 sm:w-12">
                                            @elseif ($transaction->category?->icon)
                                                <img src="{{ $transaction->category->icon->url }}" alt=""
                                                     class="h-10 w-10 avatar rounded-full object-cover sm:h-12 sm:w-12">
                                            @else
                                                <div class="h-10 w-10 avatar rounded-full bg-gray-100 sm:h-12 sm:w-12 dark:bg-gray-700"></div>
                                            @endif
                                        </div>

                                        <!-- Title + account line -->
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-gray-900 sm:text-base dark:text-gray-100">
                                                {{ $transaction->title() }}
                                            </p>

                                            <div class="mt-0.5 flex items-center gap-1.5 sm:mt-1 sm:gap-2">
                                                @if ($isTransfer)
                                                    @if ($transaction->fromAccount?->icon)
                                                        <img src="{{ $transaction->fromAccount->icon->url }}" alt="" class="h-5 w-5 avatar rounded-full object-cover sm:h-6 sm:w-6">
                                                    @else
                                                        <span class="h-5 w-5 avatar rounded-full bg-gray-100 sm:h-6 sm:w-6 dark:bg-gray-700"></span>
                                                    @endif
                                                    <svg class="h-3 w-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                    @if ($transaction->toAccount?->icon)
                                                        <img src="{{ $transaction->toAccount->icon->url }}" alt="" class="h-5 w-5 avatar rounded-full object-cover sm:h-6 sm:w-6">
                                                    @else
                                                        <span class="h-5 w-5 avatar rounded-full bg-gray-100 sm:h-6 sm:w-6 dark:bg-gray-700"></span>
                                                    @endif
                                                @else
                                                    @if ($transaction->primaryAccount()?->icon)
                                                        <img src="{{ $transaction->primaryAccount()->icon->url }}" alt="" class="h-5 w-5 avatar rounded-full object-cover sm:h-6 sm:w-6">
                                                    @else
                                                        <span class="h-5 w-5 avatar rounded-full bg-gray-100 sm:h-6 sm:w-6 dark:bg-gray-700"></span>
                                                    @endif
                                                @endif

                                                <span class="truncate text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                                                    {{ $transaction->label() }}
                                                </span>
                                            </div>

                                            @if ($transaction->note)
                                                <p class="mt-0.5 truncate text-xs text-gray-400 sm:mt-1">{{ $transaction->note }}</p>
                                            @endif
                                        </div>

                                        <!-- Amounts -->
                                        <div class="shrink-0 text-right">
                                            <p class="text-sm font-semibold sm:text-base {{ $isIncome
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : ($isTransfer ? 'text-gray-900 dark:text-gray-100' : 'text-rose-600 dark:text-rose-400') }}">
                                                {{ $isIncome ? '+' : ($isTransfer ? '' : '−') }}{{ Util::displayAmount($transaction->amount) }}
                                            </p>

                                            @if ($transaction->charge > 0)
                                                <p class="text-[11px] text-gray-500 sm:text-xs">
                                                    {{ __('Charge') }}: {{ Util::displayAmount($transaction->charge) }}
                                                </p>
                                            @endif

                                            @if ($isTransfer)
                                                <p class="text-[11px] text-gray-400 sm:text-xs">
                                                    {{ $transaction->fromAccount?->name ?? '—' }}:
                                                    {{ Util::displayAmount($transaction->from_account_balance) }}
                                                </p>
                                                <p class="text-[11px] text-gray-400 sm:text-xs">
                                                    {{ $transaction->toAccount?->name ?? '—' }}:
                                                    {{ Util::displayAmount($transaction->to_account_balance) }}
                                                </p>
                                            @else
                                                <p class="text-[11px] text-gray-400 sm:text-xs">
                                                    {{ __('Balance') }}: {{ Util::displayAmount($transaction->primaryBalance()) }}
                                                </p>
                                            @endif
                                        </div>

                                        <x-transaction-menu :transaction="$transaction" />
                                    </div>
                                @endforeach
                            </x-ui.card>
                        </div>
                    @endforeach
                </div>
            @else
                <!-- Table view -->
                <x-ui.card>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Category') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Account') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Amount') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Balance') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Date') }}</th>
                                    <th class="px-4 py-3 sm:px-6"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($transactions as $transaction)
                                    @php
                                        $isTransfer = $transaction->type === TransactionType::Transfer;
                                        $isIncome = $transaction->type === TransactionType::Income;
                                    @endphp
                                    <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-900/30">
                                        <td class="px-4 py-3 sm:px-6">
                                            <div class="flex items-center gap-2">
                                                @if ($isTransfer)
                                                    <img src="{{ $transferIcon }}" alt="" class="h-8 w-8 avatar rounded-full object-cover">
                                                @elseif ($transaction->category?->icon)
                                                    <img src="{{ $transaction->category->icon->url }}" alt="" class="h-8 w-8 avatar rounded-full object-cover">
                                                @else
                                                    <span class="h-8 w-8 avatar rounded-full bg-gray-100 dark:bg-gray-700"></span>
                                                @endif
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $transaction->title() }}</span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 sm:px-6 dark:text-gray-400">
                                            {{ $transaction->label() }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right sm:px-6">
                                            <span class="text-sm font-semibold {{ $isIncome
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : ($isTransfer ? 'text-gray-900 dark:text-gray-100' : 'text-rose-600 dark:text-rose-400') }}">
                                                {{ $isIncome ? '+' : ($isTransfer ? '' : '−') }}{{ Util::displayAmount($transaction->amount) }}
                                            </span>
                                            @if ($transaction->charge > 0)
                                                <div class="text-[11px] text-gray-500">{{ __('Charge') }}: {{ Util::displayAmount($transaction->charge) }}</div>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 sm:px-6 dark:text-gray-300">
                                            @if ($isTransfer)
                                                <div class="text-xs">
                                                    <span class="text-gray-400">{{ $transaction->fromAccount?->name ?? '—' }}:</span>
                                                    {{ Util::displayAmount($transaction->from_account_balance) }}
                                                </div>
                                                <div class="text-xs">
                                                    <span class="text-gray-400">{{ $transaction->toAccount?->name ?? '—' }}:</span>
                                                    {{ Util::displayAmount($transaction->to_account_balance) }}
                                                </div>
                                            @else
                                                {{ Util::displayAmount($transaction->primaryBalance()) }}
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 sm:px-6">
                                            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $transaction->created_at->isoFormat('D MMM YYYY') }}</div>
                                            <div class="text-xs text-gray-500">{{ $transaction->created_at->format('h:i A') }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right sm:px-6">
                                            <x-transaction-menu :transaction="$transaction" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            @endif

            <div>
                {{ $transactions->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
