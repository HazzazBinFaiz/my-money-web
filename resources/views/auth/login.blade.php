<x-guest-layout :title="__('Log in')"
                :heading="__('Welcome back')"
                :subheading="__('Sign in to pick up where your ledger left off.')">

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-ui.field :label="__('Email')" for="email" :error="$errors->get('email')">
            <x-ui.input id="email" name="email" type="email" :value="old('email')"
                        required autofocus autocomplete="username" placeholder="you@example.com" />
        </x-ui.field>

        <x-ui.field :label="__('Password')" for="password" :error="$errors->get('password')">
            <x-ui.input id="password" name="password" type="password" required autocomplete="current-password" />
        </x-ui.field>

        <div class="flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2">
                <input id="remember_me" type="checkbox" name="remember"
                       class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900/20 dark:border-gray-600 dark:bg-gray-900">
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-sm font-medium text-gray-600 hover:text-gray-900 hover:underline dark:text-gray-400 dark:hover:text-gray-200">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <x-ui.button class="w-full !h-11">{{ __('Log in') }}</x-ui.button>
    </form>

    @if (Route::has('register'))
        <p class="mt-8 text-center text-sm text-gray-600 dark:text-gray-400">
            {{ __('New here?') }}
            <a href="{{ route('register') }}" class="font-semibold text-gray-900 hover:underline dark:text-gray-100">
                {{ __('Create an account') }}
            </a>
        </p>
    @endif
</x-guest-layout>
