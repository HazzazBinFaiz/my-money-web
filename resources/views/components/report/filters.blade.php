@props([
    'route',
    'range',
    'filter',
    'accounts',
    'categories' => null,
    // Which side the page is on, so the account label says what it means.
    'income' => false,
    // Reports that show both sides at once label neutrally and offer every category.
    'both' => false,
])

@php
    use App\Enums\AccountType;
    use App\Enums\CategoryType;

    $accountGroups = [
        __('Accounts') => $accounts->where('type', AccountType::Account),
        __('Contacts') => $accounts->where('type', AccountType::Contact),
    ];

    $side = $income ? CategoryType::Income : CategoryType::Expense;

    // Only the side's own categories can match, unless both sides are on screen.
    $categoryGroups = $both
        ? [CategoryType::Income->label() => $categories?->where('type', CategoryType::Income),
           CategoryType::Expense->label() => $categories?->where('type', CategoryType::Expense)]
        : [null => $categories?->where('type', $side)];

    // The range travels with the filter, either as a preset or as raw dates.
    $rangeFields = $range->preset === 'custom'
        ? ['from' => $range->start->format('Y-m-d'), 'to' => $range->end->format('Y-m-d')]
        : ['range' => $range->preset];
@endphp

<x-ui.card>
    <form method="GET" action="{{ route($route) }}" class="flex flex-wrap items-end gap-3 p-3 sm:p-4">
        @foreach ($rangeFields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        <x-ui.field :label="$both ? __('Account') : ($income ? __('Received into') : __('Paid from'))" class="w-full sm:w-56">
            <x-ui.select name="account" @change="$el.form.submit()" x-data>
                <option value="">{{ __('All accounts') }}</option>
                @foreach ($accountGroups as $groupLabel => $group)
                    @if ($group->isNotEmpty())
                        <optgroup label="{{ $groupLabel }}">
                            @foreach ($group as $account)
                                <option value="{{ $account->id }}" @selected($filter->accountId == $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </x-ui.select>
        </x-ui.field>

        @if ($categories)
            <x-ui.field :label="__('Category')" class="w-full sm:w-56">
                <x-ui.select name="category" @change="$el.form.submit()" x-data>
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categoryGroups as $groupLabel => $group)
                        @if ($group?->isNotEmpty())
                            @if ($groupLabel)
                                <optgroup label="{{ $groupLabel }}">
                            @endif

                            @foreach ($group as $category)
                                <option value="{{ $category->id }}" @selected($filter->categoryId == $category->id)>{{ $category->name }}</option>
                            @endforeach

                            @if ($groupLabel)
                                </optgroup>
                            @endif
                        @endif
                    @endforeach
                </x-ui.select>
            </x-ui.field>
        @endif

        <x-ui.button variant="outline" class="!h-10">{{ __('Apply') }}</x-ui.button>

        @unless ($filter->isEmpty())
            <a href="{{ route($route, $rangeFields) }}"
               class="inline-flex h-10 items-center text-sm font-medium text-gray-500 hover:underline dark:text-gray-400">
                {{ __('Clear') }}
            </a>
        @endunless
    </form>
</x-ui.card>
