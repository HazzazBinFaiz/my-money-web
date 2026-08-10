<x-guest-layout :title="__('Confirm password')"
                :heading="__('Confirm your password')"
                :subheading="__('This is a secure area. Please confirm your password before continuing.')">

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <x-ui.field :label="__('Password')" for="password" :error="$errors->get('password')">
            <x-ui.input id="password" name="password" type="password" required autocomplete="current-password" autofocus />
        </x-ui.field>

        <x-ui.button class="w-full !h-11">{{ __('Confirm') }}</x-ui.button>
    </form>
</x-guest-layout>
