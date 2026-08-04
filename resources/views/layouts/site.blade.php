@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="{{ config('site.tagline') }}">

        <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <x-theme-script />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="scroll-smooth font-sans antialiased">
        <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100">

            <!-- Sticky header -->
            <header x-data="{ open: false, scrolled: false }"
                    @scroll.window="scrolled = window.scrollY > 8"
                    class="sticky top-0 z-50 border-b transition-colors"
                    :class="scrolled
                        ? 'border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-950/90'
                        : 'border-transparent bg-white dark:bg-gray-950'">

                <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <!-- Logo -->
                    <a href="{{ route('site.home') }}" class="flex items-center gap-2">
                        <x-application-logo class="h-9 w-9 rounded-xl" />
                        <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                    </a>

                    <!-- Desktop nav -->
                    <nav class="hidden items-center gap-8 md:flex">
                        <a href="{{ route('site.home') }}#features" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">{{ __('Features') }}</a>
                        <a href="{{ route('site.home') }}#contact" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">{{ __('Contact') }}</a>
                        <a href="{{ route('privacy') }}" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">{{ __('Privacy') }}</a>
                    </nav>

                    <div class="hidden items-center gap-3 md:flex">
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="inline-flex h-10 items-center justify-center rounded-lg bg-gray-900 px-4 text-sm font-semibold text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                                {{ __('Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                {{ __('Log in') }}
                            </a>
                            <a href="{{ route('register') }}"
                               class="inline-flex h-10 items-center justify-center rounded-lg bg-gray-900 px-4 text-sm font-semibold text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                                {{ __('Get started') }}
                            </a>
                        @endauth
                    </div>

                    <!-- Mobile toggle -->
                    <button type="button" @click="open = ! open"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 md:hidden dark:text-gray-400 dark:hover:bg-gray-800">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="! open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Mobile nav -->
                <div x-show="open" x-cloak @click="open = false"
                     class="border-t border-gray-100 bg-white px-4 py-3 md:hidden dark:border-gray-800 dark:bg-gray-950">
                    <a href="{{ route('site.home') }}#features" class="block rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800">{{ __('Features') }}</a>
                    <a href="{{ route('site.home') }}#contact" class="block rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800">{{ __('Contact') }}</a>
                    <a href="{{ route('privacy') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800">{{ __('Privacy') }}</a>

                    <div class="mt-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                        @auth
                            <a href="{{ route('dashboard') }}" class="block rounded-lg bg-gray-900 px-3 py-2 text-center text-sm font-semibold text-white dark:bg-white dark:text-gray-900">{{ __('Dashboard') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300">{{ __('Log in') }}</a>
                            <a href="{{ route('register') }}" class="mt-1 block rounded-lg bg-gray-900 px-3 py-2 text-center text-sm font-semibold text-white dark:bg-white dark:text-gray-900">{{ __('Get started') }}</a>
                        @endauth
                    </div>
                </div>
            </header>

            <main>
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer class="border-t border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                        <div class="max-w-sm">
                            <div class="flex items-center gap-2">
                                <x-application-logo class="h-8 w-8 rounded-lg" />
                                <span class="font-semibold">{{ config('app.name') }}</span>
                            </div>
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ config('site.tagline') }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-8 sm:grid-cols-3">
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Product') }}</h3>
                                <ul class="mt-3 space-y-2 text-sm">
                                    <li><a href="{{ route('site.home') }}#features" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ __('Features') }}</a></li>
                                    <li><a href="{{ route('register') }}" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ __('Get started') }}</a></li>
                                    <li><a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ __('Log in') }}</a></li>
                                </ul>
                            </div>

                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Legal') }}</h3>
                                <ul class="mt-3 space-y-2 text-sm">
                                    <li><a href="{{ route('privacy') }}" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ __('Privacy policy') }}</a></li>
                                    <li><a href="{{ route('terms') }}" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ __('Terms of use') }}</a></li>
                                </ul>
                            </div>

                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Support') }}</h3>
                                <ul class="mt-3 space-y-2 text-sm">
                                    <li><a href="{{ route('site.home') }}#contact" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ __('Contact us') }}</a></li>
                                    <li>
                                        <a href="mailto:{{ config('site.contact_mail_address') }}" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                                            {{ config('site.contact_mail_address') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10 border-t border-gray-200 pt-6 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        &copy; {{ date('Y') }} {{ config('site.legal.company') }}. {{ __('All rights reserved.') }}
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
