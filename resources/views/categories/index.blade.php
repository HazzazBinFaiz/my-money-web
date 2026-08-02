@php use App\Enums\CategoryStatus; use App\Enums\CategoryType; use App\Enums\ImageType; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Categories') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <!-- Create form -->
            <div class="flex justify-end">
                <x-book-import :list-url="route('books.import.categories')"
                               :action="route('books.import.categories.store')"
                               :title="__('Import from other book')" kind="category" />
            </div>

            <x-ui.card :title="__('New category')" :description="__('Type is fixed once the category is created.')">
                <form method="POST" action="{{ route('categories.store') }}" class="p-4 sm:p-6">
                    @csrf

                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        <div class="flex justify-center sm:block">
                            <x-image-picker name="icon_id" :type="ImageType::Icon" :label="__('Icon')" />
                        </div>

                        <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.field :label="__('Type')" for="type" :error="$errors->get('type')">
                                <x-ui.select id="type" name="type">
                                    @foreach (CategoryType::cases() as $type)
                                        <option value="{{ $type->value }}" @selected(old('type') == $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>

                            <x-ui.field :label="__('Name')" for="name" :error="$errors->get('name')">
                                <x-ui.input id="name" name="name" type="text" :value="old('name')"
                                            placeholder="{{ __('Groceries, Salary...') }}" required />
                            </x-ui.field>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <x-ui.button class="w-full sm:w-auto">{{ __('Add category') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <!-- List -->
            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="w-24 px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Icon') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Name') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Type') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Status') }}</th>
                                <th class="px-4 py-3 sm:px-6"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($categories as $category)
                                <tr x-data="{ editing: false }" class="hover:bg-gray-50/60 dark:hover:bg-gray-900/30">
                                    <td class="px-4 py-3 sm:px-6">
                                        @if ($category->icon)
                                            <img src="{{ $category->icon->url }}" alt="" class="h-12 w-12 rounded-full object-cover sm:h-[69px] sm:w-[69px]">
                                        @else
                                            <div class="h-12 w-12 rounded-full bg-gray-100 sm:h-[69px] sm:w-[69px] dark:bg-gray-700"></div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900 sm:px-6 dark:text-gray-100">{{ $category->name }}</td>
                                    <td class="px-4 py-3 sm:px-6">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $category->type === CategoryType::Income
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                            : 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300' }}">
                                            {{ $category->type->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 sm:px-6">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $category->status === CategoryStatus::Active
                                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                            {{ $category->status->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right sm:px-6">
                                        <button type="button" @click="editing = true"
                                                class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-300">{{ __('Edit') }}</button>

                                        <form method="POST" action="{{ route('categories.destroy', $category) }}" class="inline"
                                              onsubmit="return confirm('{{ __('Delete this category?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ms-3 text-sm font-medium text-red-600 hover:underline">{{ __('Delete') }}</button>
                                        </form>

                                        <x-ui.dialog show="editing" :title="__('Edit category')">
                                            <form method="POST" action="{{ route('categories.update', $category) }}" class="space-y-4">
                                                @csrf
                                                @method('PUT')

                                                <div class="flex justify-center sm:justify-start">
                                                    <x-image-picker name="icon_id" :type="ImageType::Icon" :image="$category->icon" :label="__('Icon')" />
                                                </div>

                                                <x-ui.field :label="__('Name')">
                                                    <x-ui.input name="name" type="text" :value="$category->name" required />
                                                </x-ui.field>

                                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <x-ui.field :label="__('Type')" :hint="__('Type cannot be changed.')">
                                                        <x-ui.input type="text" :value="$category->type->label()" disabled />
                                                    </x-ui.field>

                                                    <x-ui.field :label="__('Status')">
                                                        <x-ui.select name="status">
                                                            @foreach (CategoryStatus::cases() as $status)
                                                                <option value="{{ $status->value }}" @selected($category->status === $status)>{{ $status->label() }}</option>
                                                            @endforeach
                                                        </x-ui.select>
                                                    </x-ui.field>
                                                </div>

                                                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                                                    <x-ui.button type="button" variant="outline" @click="editing = false">{{ __('Cancel') }}</x-ui.button>
                                                    <x-ui.button>{{ __('Save changes') }}</x-ui.button>
                                                </div>
                                            </form>
                                        </x-ui.dialog>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">{{ __('No categories yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
