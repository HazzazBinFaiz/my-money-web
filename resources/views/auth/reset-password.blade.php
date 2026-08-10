<x-guest-layout :title="__('Reset password')"
                :heading="__('Choose a new password')"
                :subheading="__('Pick something you have not used elsewhere.')">

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.field :label="__('Email')" for="email" :error="$errors->get('email')">
            <x-ui.input id="email" name="email" type="email" :value="old('email', $request->email)"
                        required autofocus autocomplete="username" />
        </x-ui.field>

        <x-ui.field :label="__('New password')" for="password" :error="$errors->get('password')">
            <x-ui.input id="password" name="password" type="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field :label="__('Confirm password')" for="password_confirmation" :error="$errors->get('password_confirmation')">
            <x-ui.input id="password_confirmation" name="password_confirmation" type="password"
                        required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.button class="w-full !h-11">{{ __('Reset password') }}</x-ui.button>
    </form>
</x-guest-layout>
