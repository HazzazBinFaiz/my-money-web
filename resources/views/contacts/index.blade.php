@php use App\Enums\ImageType; use App\Lib\Util; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Contacts') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <!-- Create form -->
            <x-ui.card :title="__('New contact')"
                       :description="__('A contact account with the same name and initial amount is created automatically.')">
                <form method="POST" action="{{ route('contacts.store') }}" class="p-4 sm:p-6">
                    @csrf

                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        <div class="flex justify-center sm:block">
                            <x-image-picker name="picture_id" :type="ImageType::Picture" :label="__('Picture')" />
                        </div>

                        <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <x-ui.field :label="__('Name')" for="name" :error="$errors->get('name')">
                                <x-ui.input id="name" name="name" type="text" :value="old('name')" required />
                            </x-ui.field>

                            <x-ui.field :label="__('Phone')" for="phone" :error="$errors->get('phone')">
                                <x-ui.input id="phone" name="phone" type="tel" inputmode="tel" :value="old('phone')"
                                            placeholder="{{ __('Optional') }}" />
                            </x-ui.field>

                            <x-ui.field :label="__('Email')" for="email" :error="$errors->get('email')">
                                <x-ui.input id="email" name="email" type="email" :value="old('email')"
                                            placeholder="{{ __('Optional') }}" />
                            </x-ui.field>

                            <x-ui.field :label="__('Initial amount')" for="initial_amount" :error="$errors->get('initial_amount')">
                                <x-ui.input id="initial_amount" name="initial_amount" type="number" step="1" inputmode="numeric"
                                            :value="old('initial_amount', 0)" required />
                            </x-ui.field>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <x-ui.button class="w-full sm:w-auto">{{ __('Add contact') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <!-- List -->
            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="w-24 px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Picture') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Name') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Phone') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Email') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Initial') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Balance') }}</th>
                                <th class="px-4 py-3 sm:px-6"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($contacts as $contact)
                                <tr x-data="{ editing: false }" class="hover:bg-gray-50/60 dark:hover:bg-gray-900/30">
                                    <td class="px-4 py-3 sm:px-6">
                                        @if ($contact->picture)
                                            <img src="{{ $contact->picture->url }}" alt="" class="h-12 w-12 rounded-full object-cover sm:h-[69px] sm:w-[69px]">
                                        @else
                                            <div class="h-12 w-12 rounded-full bg-gray-100 sm:h-[69px] sm:w-[69px] dark:bg-gray-700"></div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900 sm:px-6 dark:text-gray-100">{{ $contact->name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 sm:px-6 dark:text-gray-400">{{ $contact->phone ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 sm:px-6 dark:text-gray-400">{{ $contact->email ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600 sm:px-6 dark:text-gray-400">
                                        {{ Util::displayAmount($contact->account?->initial_amount) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900 sm:px-6 dark:text-gray-100">
                                        {{ Util::displayAmount($contact->account?->amount) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right sm:px-6">
                                        <button type="button" @click="editing = true"
                                                class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-300">{{ __('Edit') }}</button>

                                        <form method="POST" action="{{ route('contacts.destroy', $contact) }}" class="inline"
                                              onsubmit="return confirm('{{ __('Delete this contact and its account?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ms-3 text-sm font-medium text-red-600 hover:underline">{{ __('Delete') }}</button>
                                        </form>

                                        <x-ui.dialog show="editing" :title="__('Edit contact')">
                                            <form method="POST" action="{{ route('contacts.update', $contact) }}" class="space-y-4">
                                                @csrf
                                                @method('PUT')

                                                <div class="flex justify-center sm:justify-start">
                                                    <x-image-picker name="picture_id" :type="ImageType::Picture" :image="$contact->picture" :label="__('Picture')" />
                                                </div>

                                                <x-ui.field :label="__('Name')">
                                                    <x-ui.input name="name" type="text" :value="$contact->name" required />
                                                </x-ui.field>

                                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <x-ui.field :label="__('Phone')">
                                                        <x-ui.input name="phone" type="tel" :value="$contact->phone" />
                                                    </x-ui.field>

                                                    <x-ui.field :label="__('Email')">
                                                        <x-ui.input name="email" type="email" :value="$contact->email" />
                                                    </x-ui.field>
                                                </div>

                                                <x-ui.field :label="__('Initial amount')">
                                                    <x-ui.input name="initial_amount" type="number" step="1" inputmode="numeric"
                                                                :value="$contact->account?->initial_amount ?? 0" required />
                                                </x-ui.field>

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
                                    <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">{{ __('No contacts yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
