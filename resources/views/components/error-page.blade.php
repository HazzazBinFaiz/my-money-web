@props([
    'code' => 500,
    'title' => null,
    'message' => null,
    'hint' => null,
])

@php
    // Error pages render when things are already going wrong, so they lean on
    // nothing but config: no database, no session, no view composers.
    $title ??= __('Something went wrong');
    $message ??= __('That request could not be completed.');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">

        <title>{{ $code }} · {{ $title }}</title>

        <x-favicon />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <x-theme-script />

        @vite(['resources/css/app.css'])
    </head>

    <body class="font-sans antialiased">
        <div class="relative flex min-h-screen flex-col overflow-hidden bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100">

            <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 -top-40 -z-10 transform-gpu blur-3xl">
                <div class="mx-auto aspect-[1155/678] w-[64rem] bg-gradient-to-tr from-emerald-200 to-indigo-300 opacity-30 dark:opacity-20"
                     style="clip-path: polygon(74% 44%, 100% 61%, 97% 26%, 85% 0%, 80% 2%, 72% 32%, 60% 62%, 52% 68%, 47% 58%, 45% 34%, 27% 76%, 0% 64%, 17% 100%, 27% 76%, 76% 97%, 74% 44%)"></div>
            </div>

            <header class="px-4 py-6 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2">
                    <x-application-logo class="h-9 w-9 rounded-xl" />
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>
            </header>

            <main class="flex flex-1 items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
                <div class="w-full max-w-lg text-center">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-gray-400">{{ __('Error') }} {{ $code }}</p>

                    <h1 class="mt-4 text-4xl font-bold tracking-tight sm:text-5xl">{{ $title }}</h1>

                    <p class="mt-4 text-base leading-7 text-gray-600 dark:text-gray-300">{{ $message }}</p>

                    @if ($hint)
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $hint }}</p>
                    @endif

                    <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <a href="{{ url('/') }}"
                           class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-gray-900 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800 sm:w-auto dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                            {{ __('Back to home') }}
                        </a>

                        <a href="javascript:history.back()"
                           class="inline-flex h-11 w-full items-center justify-center rounded-lg border border-gray-200 px-5 text-sm font-semibold text-gray-900 transition hover:bg-gray-50 sm:w-auto dark:border-gray-700 dark:text-gray-100 dark:hover:bg-gray-800">
                            {{ __('Go back') }}
                        </a>
                    </div>

                    {{ $slot }}
                </div>
            </main>

            <footer class="px-4 py-6 text-center text-xs text-gray-500 sm:px-6 lg:px-8 dark:text-gray-400">
                {{ __('Still stuck?') }}
                <a href="mailto:{{ config('site.contact_mail_address') }}" class="font-medium underline">
                    {{ config('site.contact_mail_address') }}
                </a>
            </footer>
        </div>
    </body>
</html>
