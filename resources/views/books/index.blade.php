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
                                <x-image-picker name="icon_id" :type="ImageType::Book" :image="$current->icon" :label="__('Icon')" />
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

            @if (session('mbak_summary'))
                @php($summary = session('mbak_summary'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800
                            dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                    {{ __('Backup imported into :book:', ['book' => $current?->name]) }}
                    {{ $summary['transactions_created'] }} {{ __('transactions') }},
                    {{ $summary['accounts_created'] }} {{ __('new accounts') }},
                    {{ $summary['categories_created'] }} {{ __('new categories') }}@if ($summary['skipped']),
                        {{ $summary['skipped'] }} {{ __('records skipped') }}@endif.
                </div>
            @endif

            <!-- Restore from a mobile app backup -->
            <x-ui.card :title="__('Import from .mbak backup')"
                       :description="__('Upload a backup exported by the mobile app to bring its accounts, categories and records in.')">
                <form method="POST" action="{{ route('books.import.mbak') }}" enctype="multipart/form-data"
                      class="p-4 sm:p-6"
                      x-data="{ fileName: '', dragging: false }">
                    @csrf

                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed
                                  px-4 py-8 text-center transition"
                           :class="dragging
                               ? 'border-gray-900 bg-gray-50 dark:border-gray-300 dark:bg-gray-900'
                               : 'border-gray-300 dark:border-gray-600'"
                           @dragover.prevent="dragging = true"
                           @dragleave.prevent="dragging = false"
                           @drop.prevent="dragging = false; $refs.backup.files = $event.dataTransfer.files; fileName = $refs.backup.files[0]?.name ?? ''">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>

                        <span class="text-sm text-gray-600 dark:text-gray-300">
                            <span x-show="! fileName">{{ __('Drop a .mbak file here, or click to choose one') }}</span>
                            <span x-show="fileName" x-cloak class="font-medium text-gray-900 dark:text-gray-100" x-text="fileName"></span>
                        </span>

                        <input type="file" name="backup" accept=".mbak" x-ref="backup" class="hidden"
                               @change="fileName = $event.target.files[0]?.name ?? ''">
                    </label>

                    <x-input-error :messages="$errors->get('backup')" class="mt-2" />

                    <div class="mt-4 flex justify-end">
                        <x-ui.button ::disabled="! fileName" class="w-full sm:w-auto">{{ __('Upload and read') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <!-- Export -->
            <x-ui.card :title="__('Export')"
                       :description="__('Take this book with you, as a spreadsheet or as a mobile app backup.')">
                <div class="flex flex-wrap items-center gap-3 p-4 sm:p-6">
                    <a href="{{ route('books.export.mbak') }}"
                       class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-gray-900 px-4 text-sm font-medium
                              text-white shadow-sm transition hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                        </svg>
                        {{ __('Download .mbak') }}
                    </a>

                    <a href="{{ route('books.export.excel') }}"
                       class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-gray-200 bg-white px-4 text-sm
                              font-medium text-gray-900 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900
                              dark:text-gray-100 dark:hover:bg-gray-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 17v-6h6v6M5 21h14a2 2 0 002-2V7l-4-4H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        {{ __('Download Excel') }}
                    </a>

                    <p class="w-full text-xs text-gray-500 dark:text-gray-400">
                        {{ __('The workbook has a sheet each for transactions, accounts, categories and contacts. In the .mbak, charges become separate "Transfer Charge" expenses and inactive accounts get a leading dot.') }}
                    </p>
                </div>
            </x-ui.card>

            <!-- All books -->
            <x-ui.card :title="__('Your books')">
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($books as $book)
                        <div class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-6" x-data="{ confirming: false }">
                            @if ($book->icon)
                                <img src="{{ $book->icon->url }}" alt="" class="h-10 w-10 avatar rounded-full object-cover">
                            @else
                                <span class="h-10 w-10 avatar rounded-full bg-gray-100 dark:bg-gray-700"></span>
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
                            <x-image-picker name="icon_id" :type="ImageType::Book" :label="__('Icon')" />
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
