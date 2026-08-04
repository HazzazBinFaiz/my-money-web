@props([
    'listUrl',
    'action',
    'title' => 'Import from other book',
    'kind' => 'contact',
])

<div x-data="bookImport(@js(['listUrl' => $listUrl]))" class="inline-block">
    <x-ui.button type="button" variant="outline" class="!h-9 !text-xs" @click="openModal()">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4-4m0 0L8 7m4-4v12"/>
        </svg>
        {{ $title }}
    </x-ui.button>

    <x-ui.dialog show="open" :title="$title" size="max-w-xl">
        <form method="POST" action="{{ $action }}" class="space-y-3">
            @csrf

            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('Only names that do not exist in this book yet are listed.') }}
            </p>

            <p x-show="error" x-cloak x-text="error" class="text-sm text-red-600"></p>

            <x-ui.input type="text" x-model="search" placeholder="{{ __('Search...') }}" class="!h-9" />

            <div class="max-h-80 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700">
                <p x-show="loading" class="px-3 py-4 text-sm text-gray-500">{{ __('Loading...') }}</p>

                <p x-show="! loading && visible().length === 0" x-cloak class="px-3 py-6 text-center text-sm text-gray-500">
                    {{ __('Nothing to import.') }}
                </p>

                <template x-if="! loading && visible().length">
                    <div>
                        <button type="button" @click="toggleAll()"
                                class="w-full border-b border-gray-100 px-3 py-2 text-start text-xs font-medium text-gray-500
                                       transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                            <span x-text="allVisibleSelected() ? '{{ __('Clear selection') }}' : '{{ __('Select all') }}'"></span>
                        </button>

                        <template x-for="item in visible()" :key="item.id">
                            <label class="flex cursor-pointer items-center gap-3 border-b border-gray-100 px-3 py-2 transition
                                          last:border-b-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                <input type="checkbox" name="ids[]" :value="item.id"
                                       :checked="isSelected(item.id)" @change="toggle(item.id)"
                                       class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900/20 dark:border-gray-600">

                                <template x-if="item.icon">
                                    <img :src="item.icon" alt="" class="h-8 w-8 avatar rounded-full object-cover">
                                </template>
                                <template x-if="! item.icon">
                                    <span class="h-8 w-8 avatar rounded-full bg-gray-100 dark:bg-gray-600"></span>
                                </template>

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-gray-900 dark:text-gray-100" x-text="item.name"></span>
                                    <span class="block truncate text-xs text-gray-500">
                                        <template x-if="item.type_label">
                                            <span x-text="item.type_label + ' · '"></span>
                                        </template>
                                        <template x-if="item.phone || item.email">
                                            <span x-text="[item.phone, item.email].filter(Boolean).join(' · ') + ' · '"></span>
                                        </template>
                                        <span x-text="item.book"></span>
                                    </span>
                                </span>
                            </label>
                        </template>
                    </div>
                </template>
            </div>

            @if ($kind === 'contact')
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Imported contacts start with a zero balance in this book.') }}
                </p>
            @endif

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button type="button" variant="outline" @click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button ::disabled="selected.length === 0">
                    <span x-text="`{{ __('Import') }} ${selected.length}`"></span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
</div>
