<x-site-layout :title="__('Terms of use')">
    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Terms of use') }}</h1>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Last updated') }}: {{ \Carbon\Carbon::parse(config('site.legal.updated_at'))->isoFormat('D MMMM YYYY') }}
        </p>

        <div class="mt-10 space-y-8 text-gray-700 dark:text-gray-300">
            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Using the service') }}</h2>
                <p>{{ __('Keep your login details to yourself, and use the service lawfully. You are responsible for what you record in your books.') }}</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Your records') }}</h2>
                <p>{{ __('Your data stays yours. You can export a book at any time, and delete a book or your account whenever you like.') }}</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Availability') }}</h2>
                <p>{{ __('The service is provided as is, without warranty. We aim to keep it running but cannot guarantee uninterrupted access.') }}</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Not financial advice') }}</h2>
                <p>{{ __('The figures shown are the ones you enter. Nothing here is financial, tax or accounting advice.') }}</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Changes') }}</h2>
                <p>{{ __('These terms may change; continued use after an update means you accept the revised version.') }}</p>
            </section>
        </div>
    </article>
</x-site-layout>
