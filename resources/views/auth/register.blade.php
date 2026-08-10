<x-guest-layout :title="__('Create account')"
                :heading="__('Create your account')"
                :subheading="__('Your first book is set up for you. Free to start, no card required.')">

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <x-ui.field :label="__('Name')" for="name" :error="$errors->get('name')">
            <x-ui.input id="name" name="name" type="text" :value="old('name')"
                        required autofocus autocomplete="name" placeholder="{{ __('Ada Lovelace') }}" />
        </x-ui.field>

        <x-ui.field :label="__('Email')" for="email" :error="$errors->get('email')">
            <x-ui.input id="email" name="email" type="email" :value="old('email')"
                        required autocomplete="username" placeholder="you@example.com" />
        </x-ui.field>

        <x-ui.field :label="__('Password')" for="password" :error="$errors->get('password')">
            <x-ui.input id="password" name="password" type="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field :label="__('Confirm password')" for="password_confirmation" :error="$errors->get('password_confirmation')">
            <x-ui.input id="password_confirmation" name="password_confirmation" type="password"
                        required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.button class="w-full !h-11">{{ __('Create account') }}</x-ui.button>

        <p class="text-center text-xs text-gray-500 dark:text-gray-400">
            {{ __('By continuing you agree to our') }}
            <a href="{{ route('terms') }}" class="underline hover:text-gray-700 dark:hover:text-gray-200">{{ __('terms of use') }}</a>
            {{ __('and') }}
            <a href="{{ route('privacy') }}" class="underline hover:text-gray-700 dark:hover:text-gray-200">{{ __('privacy policy') }}</a>.
        </p>
    </form>

    <p class="mt-8 text-center text-sm text-gray-600 dark:text-gray-400">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" class="font-semibold text-gray-900 hover:underline dark:text-gray-100">
            {{ __('Log in') }}
        </a>
    </p>
</x-guest-layout>
