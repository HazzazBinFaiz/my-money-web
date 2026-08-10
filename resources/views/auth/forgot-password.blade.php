<x-guest-layout :title="__('Forgot password')"
                :heading="__('Forgot your password?')"
                :subheading="__('Tell us the email you signed up with and we will send a reset link.')">

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-ui.field :label="__('Email')" for="email" :error="$errors->get('email')">
            <x-ui.input id="email" name="email" type="email" :value="old('email')"
                        required autofocus placeholder="you@example.com" />
        </x-ui.field>

        <x-ui.button class="w-full !h-11">{{ __('Email password reset link') }}</x-ui.button>
    </form>

    <p class="mt-8 text-center text-sm text-gray-600 dark:text-gray-400">
        <a href="{{ route('login') }}" class="font-semibold text-gray-900 hover:underline dark:text-gray-100">
            {{ __('Back to log in') }}
        </a>
    </p>
</x-guest-layout>
