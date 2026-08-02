@php use App\Enums\AccountStatus; use App\Enums\ImageType; use App\Lib\Util; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Accounts') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <!-- Create form -->
            <x-ui.card :title="__('New account')" :description="__('Add an account and its opening balance.')">
                <form method="POST" action="{{ route('accounts.store') }}" class="p-4 sm:p-6">
                    @csrf

                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        <div class="flex justify-center sm:block">
                            <x-image-picker name="icon_id" :type="ImageType::Icon" :label="__('Icon')" />
                        </div>

                        <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.field :label="__('Name')" for="name" :error="$errors->get('name')">
                                <x-ui.input id="name" name="name" type="text" :value="old('name')"
                                            placeholder="{{ __('Cash, Bank, Card...') }}" required />
                            </x-ui.field>

                            <x-ui.field :label="__('Initial amount')" for="initial_amount" :error="$errors->get('initial_amount')">
                                <x-ui.input id="initial_amount" name="initial_amount" type="number" step="0.01" inputmode="decimal"
                                            :value="old('initial_amount', 0)" required />
                            </x-ui.field>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <x-ui.button class="w-full sm:w-auto">{{ __('Add account') }}</x-ui.button>
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
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Initial') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 sm:px-6">{{ __('Balance') }}</th>
                                <th class="px-4 py-3 sm:px-6"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($accounts as $account)
                                <tr x-data="{ editing: false }" class="hover:bg-gray-50/60 dark:hover:bg-gray-900/30">
                                    <td class="px-4 py-3 sm:px-6">
                                        @if ($account->icon)
                                            <img src="{{ $account->icon->url }}" alt="" class="h-12 w-12 rounded-full object-cover sm:h-[69px] sm:w-[69px]">
                                        @else
                                            <div class="h-12 w-12 rounded-full bg-gray-100 sm:h-[69px] sm:w-[69px] dark:bg-gray-700"></div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 sm:px-6">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $account->name }}</div>
                                        @if ($account->status === AccountStatus::Inactive)
                                            <span class="mt-1 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                {{ __('Inactive') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600 sm:px-6 dark:text-gray-400">
                                        {{ Util::displayAmount($account->initial_amount) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900 sm:px-6 dark:text-gray-100">
                                        {{ Util::displayAmount($account->amount) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right sm:px-6">
                                        <button type="button" @click="editing = true"
                                                class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-300">{{ __('Edit') }}</button>

                                        <form method="POST" action="{{ route('accounts.destroy', $account) }}" class="inline"
                                              onsubmit="return confirm('{{ __('Delete this account?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ms-3 text-sm font-medium text-red-600 hover:underline">{{ __('Delete') }}</button>
                                        </form>

                                        <x-ui.dialog show="editing" :title="__('Edit account')">
                                            <form method="POST" action="{{ route('accounts.update', $account) }}" class="space-y-4">
                                                @csrf
                                                @method('PUT')

                                                <div class="flex justify-center sm:justify-start">
                                                    <x-image-picker name="icon_id" :type="ImageType::Icon" :image="$account->icon" :label="__('Icon')" />
                                                </div>

                                                <x-ui.field :label="__('Name')">
                                                    <x-ui.input name="name" type="text" :value="$account->name" required />
                                                </x-ui.field>

                                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <x-ui.field :label="__('Initial amount')">
                                                        <x-ui.input name="initial_amount" type="number" step="0.01" inputmode="decimal"
                                                                    :value="\App\Lib\Util::toMajorUnits($account->initial_amount)" required />
                                                    </x-ui.field>

                                                    <x-ui.field :label="__('Status')">
                                                        <x-ui.select name="status">
                                                            @foreach (AccountStatus::cases() as $status)
                                                                <option value="{{ $status->value }}" @selected($account->status === $status)>{{ $status->label() }}</option>
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
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">{{ __('No accounts yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
