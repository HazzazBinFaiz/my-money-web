<x-guest-layout :title="__('Verify email')"
                :heading="__('Verify your email')"
                :subheading="__('We sent a link to your inbox. Click it and you are in.')">

    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ __('A fresh verification link has been sent to your email address.') }}
        </div>
    @endif

    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('Did not get it? Check your spam folder, or send another.') }}
    </p>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form method="POST" action="{{ route('verification.send') }}" class="sm:flex-1">
            @csrf
            <x-ui.button class="w-full !h-11">{{ __('Resend verification email') }}</x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900 hover:underline dark:text-gray-400 dark:hover:text-gray-200">
                {{ __('Log out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
