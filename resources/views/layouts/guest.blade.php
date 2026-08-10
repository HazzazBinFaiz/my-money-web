@props([
    'title' => null,
    'heading' => null,
    'subheading' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

        <x-favicon />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <x-theme-script />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased">
        <div class="grid min-h-screen bg-white lg:grid-cols-2 dark:bg-gray-950">

            <!-- Brand panel -->
            <div class="relative hidden overflow-hidden bg-gray-900 lg:flex lg:flex-col lg:justify-between lg:p-12">
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-30">
                    <div class="absolute -left-24 top-10 h-72 w-72 rounded-full bg-emerald-500 blur-3xl"></div>
                    <div class="absolute -right-16 bottom-0 h-80 w-80 rounded-full bg-indigo-500 blur-3xl"></div>
                </div>

                <a href="{{ route('site.home') }}" class="relative flex items-center gap-2 text-white">
                    <x-application-logo class="h-9 w-9 rounded-xl" />
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>

                <div class="relative">
                    <h2 class="text-3xl font-bold leading-tight text-white">
                        {{ __('Know where your money actually went.') }}
                    </h2>
                    <p class="mt-4 max-w-md text-gray-300">{{ config('site.tagline') }}</p>

                    <ul class="mt-8 space-y-3 text-sm text-gray-300">
                        @foreach ([
                            __('Accounts, contacts and categories in one ledger'),
                            __('A month of entries in a single sitting'),
                            __('Balances that recalculate themselves'),
                        ] as $point)
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ $point }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="relative text-xs text-gray-400">
                    &copy; {{ date('Y') }} {{ config('site.legal.company') }}
                </p>
            </div>

            <!-- Form panel -->
            <div class="flex flex-col justify-center px-4 py-10 sm:px-6 lg:px-12">
                <div class="mx-auto w-full max-w-md">
                    <!-- Small screens get the mark here, since the brand panel is hidden -->
                    <a href="{{ route('site.home') }}" class="mb-8 flex items-center justify-center gap-2 lg:hidden">
                        <x-application-logo class="h-9 w-9 rounded-xl" />
                        <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                    </a>

                    @if ($heading)
                        <div class="mb-8">
                            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">{{ $heading }}</h1>

                            @if ($subheading)
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $subheading }}</p>
                            @endif
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
