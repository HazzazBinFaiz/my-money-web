@php
    use App\Enums\CurrencyPosition;
    use App\Enums\ImageType;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Books') }}
            </h2>

            <button type="button" x-data @click="$dispatch('open-new-book')"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-gray-900 px-3 text-sm font-medium text-white
                           shadow-sm transition hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('New book') }}
            </button>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <!-- Preferences of the active book -->
            @if ($current)
                <x-ui.card :title="__('Book preferences')"
                           :description="__('Applies to :book only.', ['book' => $current->name])">
                    <form method="POST" action="{{ route('books.update', $current) }}" class="p-4 sm:p-6">
                        @csrf
                        @method('PUT')

                        <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                            <div class="flex justify-center sm:block">
                                <x-image-picker name="icon_id" :type="ImageType::Icon" :image="$current->icon" :label="__('Icon')" />
                            </div>

                            <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-ui.field :label="__('Name')" for="name" :error="$errors->get('name')">
                                    <x-ui.input id="name" name="name" type="text" :value="old('name', $current->name)" required />
                                </x-ui.field>

                                <x-ui.field :label="__('Decimal places')" for="decimal_places" :error="$errors->get('decimal_places')">
                                    <x-ui.select id="decimal_places" name="decimal_places">
                                        @foreach ([0, 1, 2] as $places)
                                            <option value="{{ $places }}" @selected(old('decimal_places', $current->decimal_places) == $places)>
                                                {{ $places }}
                                            </option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>

                                <x-ui.field :label="__('Currency')" for="currency" :error="$errors->get('currency')"
                                            :hint="__('Symbol or code, e.g. $ or BDT.')">
                                    <x-ui.input id="currency" name="currency" type="text" maxlength="8"
                                                :value="old('currency', $current->currency)" placeholder="{{ __('None') }}" />
                                </x-ui.field>

                                <x-ui.field :label="__('Currency position')" for="currency_position"
                                            :error="$errors->get('currency_position')">
                                    <x-ui.select id="currency_position" name="currency_position">
                                        @foreach (CurrencyPosition::cases() as $position)
                                            <option value="{{ $position->value }}"
                                                @selected(old('currency_position', $current->currency_position->value) == $position->value)>
                                                {{ $position->label() }}
                                            </option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <x-ui.button class="w-full sm:w-auto">{{ __('Save preferences') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endif

            <!-- All books -->
            <x-ui.card :title="__('Your books')">
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($books as $book)
                        <div class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-6" x-data="{ confirming: false }">
                            @if ($book->icon)
                                <img src="{{ $book->icon->url }}" alt="" class="h-10 w-10 rounded-full object-cover">
                            @else
                                <span class="h-10 w-10 rounded-full bg-gray-100 dark:bg-gray-700"></span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-gray-900 dark:text-gray-100">
                                    {{ $book->name }}
                                    @if ($book->is_default)
                                        <span class="ms-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            {{ __('Default') }}
                                        </span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $book->accounts_count }} {{ __('accounts') }} ·
                                    {{ $book->transactions_count }} {{ __('transactions') }} ·
                                    {{ $book->currency ?: __('no currency') }} ·
                                    {{ $book->decimal_places }} {{ __('dp') }}
                                </p>
                            </div>

                            @if ($current && $current->id === $book->id)
                                <span class="rounded-md bg-gray-900 px-2.5 py-1 text-xs font-medium text-white dark:bg-white dark:text-gray-900">
                                    {{ __('Active') }}
                                </span>
                            @else
                                <form method="POST" action="{{ route('books.switch', $book) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="outline" class="!h-9 !text-xs">{{ __('Switch to') }}</x-ui.button>
                                </form>
                            @endif

                            @if ($books->count() > 1)
                                <x-ui.button type="button" variant="ghost" class="!h-9 !text-xs !text-red-600"
                                             @click="confirming = true">
                                    {{ __('Delete') }}
                                </x-ui.button>

                                <x-ui.dialog show="confirming" :title="__('Delete book')">
                                    <form method="POST" action="{{ route('books.destroy', $book) }}" class="space-y-4">
                                        @csrf
                                        @method('DELETE')

                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ __('This deletes every account, contact, category and transaction in this book. Type') }}
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $book->name }}</span>
                                            {{ __('to confirm.') }}
                                        </p>

                                        <x-ui.field :label="__('Book name')">
                                            <x-ui.input name="name" type="text" autocomplete="off" required />
                                        </x-ui.field>

                                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                            <x-ui.button type="button" variant="outline" @click="confirming = false">
                                                {{ __('Cancel') }}
                                            </x-ui.button>
                                            <x-ui.button variant="danger">{{ __('Delete book') }}</x-ui.button>
                                        </div>
                                    </form>
                                </x-ui.dialog>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <!-- New book -->
            <div x-data="{ creating: false }" @open-new-book.window="creating = true">
                <x-ui.dialog show="creating" :title="__('New book')">
                    <form method="POST" action="{{ route('books.store') }}" class="space-y-4">
                        @csrf

                        <div class="flex justify-center sm:justify-start">
                            <x-image-picker name="icon_id" :type="ImageType::Icon" :label="__('Icon')" />
                        </div>

                        <x-ui.field :label="__('Name')">
                            <x-ui.input name="name" type="text" required />
                        </x-ui.field>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-ui.field :label="__('Decimal places')">
                                <x-ui.select name="decimal_places">
                                    @foreach ([0, 1, 2] as $places)
                                        <option value="{{ $places }}" @selected($places === 2)>{{ $places }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>

                            <x-ui.field :label="__('Currency')">
                                <x-ui.input name="currency" type="text" maxlength="8" placeholder="{{ __('None') }}" />
                            </x-ui.field>

                            <x-ui.field :label="__('Position')">
                                <x-ui.select name="currency_position">
                                    @foreach (CurrencyPosition::cases() as $position)
                                        <option value="{{ $position->value }}">{{ $position->label() }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        </div>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-ui.button type="button" variant="outline" @click="creating = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button>{{ __('Create book') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.dialog>
            </div>
        </div>
    </div>
</x-app-layout>
