<x-site-layout :title="__('Privacy policy')">
    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Privacy policy') }}</h1>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Last updated') }}: {{ \Carbon\Carbon::parse(config('site.legal.updated_at'))->isoFormat('D MMMM YYYY') }}
        </p>

        <div class="mt-10 space-y-8 text-gray-700 dark:text-gray-300">
            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('What we store') }}</h2>
                <p>{{ __('Your account details (name and email), and the financial records you enter: books, accounts, contacts, categories, transactions and any icons you upload.') }}</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('How it is used') }}</h2>
                <p>{{ __('Solely to run the service for you. Your financial records are scoped to your own account and are not sold, shared or used for advertising.') }}</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Contact messages') }}</h2>
                <p>{{ __('Messages sent through the contact form reach our inbox with the name and email address you supply, so that we can reply.') }}</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Deleting your data') }}</h2>
                <p>{{ __('Deleting a book removes everything inside it. Deleting your account removes your books, records and uploads.') }}</p>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Questions') }}</h2>
                <p>
                    {{ __('Write to') }}
                    <a href="mailto:{{ config('site.contact_mail_address') }}" class="font-medium underline">{{ config('site.contact_mail_address') }}</a>.
                </p>
            </section>
        </div>
    </article>
</x-site-layout>
