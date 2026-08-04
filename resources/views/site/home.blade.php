@php
    $steps = [
        [
            'title' => __('Open a book'),
            'body' => __('Personal, business, or one per household. Each book carries its own currency, decimals and history.'),
        ],
        [
            'title' => __('Add accounts and categories'),
            'body' => __('Cash, bank, cards and the people you lend to. Pick icons from the built in library or crop your own.'),
        ],
        [
            'title' => __('Record what moves'),
            'body' => __('One at a time, or a whole month pasted into the grid. Balances update themselves as you go.'),
        ],
    ];

    $faqs = [
        [
            'q' => __('Can I bring data from another app?'),
            'a' => __('Yes. Upload a .mbak backup and its accounts, categories and records are read straight in, matched by name so nothing is duplicated. There is a CSV import for the bulk grid too, and you can export a book back out at any time.'),
        ],
        [
            'q' => __('Does it handle lending money to people?'),
            'a' => __('Contacts are first class. Each one gets its own account behind the scenes, so what you lent and what came back sits in the same ledger as everything else.'),
        ],
        [
            'q' => __('Can I keep personal and business apart?'),
            'a' => __('Separate books do exactly that. Switching book re-scopes every page, and you can copy contacts or categories between books without copying the balances.'),
        ],
        [
            'q' => __('What currency does it use?'),
            'a' => __('Whichever you set per book, shown before or after the amount, with zero to two decimal places. Amounts are stored as exact whole units, so nothing drifts through rounding.'),
        ],
        [
            'q' => __('What does it cost?'),
            'a' => __('Nothing to start, and no card is asked for. Create an account and you are in.'),
        ],
    ];

    $features = [
        [
            'title' => __('Accounts and contacts'),
            'body' => __('Cash, cards, banks and the people you lend to, each with its own running balance and opening figure.'),
            'icon' => 'M3 10h18M7 15h4m-7 5h16a2 2 0 002-2V6a2 2 0 00-2-2H4a2 2 0 00-2 2v12a2 2 0 002 2z',
        ],
        [
            'title' => __('Income, expense, transfer'),
            'body' => __('Three kinds of movement, charges included, with the closing balance stored on every entry.'),
            'icon' => 'M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4',
        ],
        [
            'title' => __('Spreadsheet style bulk entry'),
            'body' => __('Paste a block from a spreadsheet, import a CSV, or type across the grid with the keyboard alone.'),
            'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16',
        ],
        [
            'title' => __('Amounts that do the maths'),
            'body' => __('Type 10 * 2 or (4 * 4)+54 into any amount and it resolves as you leave the field.'),
            'icon' => 'M9 7h6m-6 4h6m-6 4h2m-5 6h12a2 2 0 002-2V5a2 2 0 00-2-2H6a2 2 0 00-2 2v14a2 2 0 002 2z',
        ],
        [
            'title' => __('Separate books'),
            'body' => __('Keep personal and business apart, each with its own currency, decimals and history.'),
            'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
        ],
        [
            'title' => __('Balances that never drift'),
            'body' => __('Edit an opening balance or remove an entry and every figure after it is recalculated for you.'),
            'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        ],
    ];
@endphp

<x-site-layout>
    <!-- Hero -->
    <section class="relative overflow-hidden">
        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-0 -top-40 -z-10 transform-gpu blur-3xl">
            <div class="mx-auto aspect-[1155/678] w-[72rem] bg-gradient-to-tr from-emerald-200 to-indigo-300 opacity-30 dark:opacity-20"
                 style="clip-path: polygon(74% 44%, 100% 61%, 97% 26%, 85% 0%, 80% 2%, 72% 32%, 60% 62%, 52% 68%, 47% 58%, 45% 34%, 27% 76%, 0% 64%, 17% 100%, 27% 76%, 76% 97%, 74% 44%)"></div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-600 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    {{ __('Personal finance, without the spreadsheet sprawl') }}
                </span>

                <h1 class="mt-6 text-4xl font-bold tracking-tight sm:text-6xl">
                    {{ __('Know where your money') }}
                    <span class="bg-gradient-to-r from-emerald-600 to-indigo-600 bg-clip-text text-transparent dark:from-emerald-400 dark:to-indigo-400">
                        {{ __('actually went') }}
                    </span>
                </h1>

                <p class="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-300">
                    {{ config('site.tagline') }}
                    {{ __('Track accounts, contacts and categories across separate books, and enter a month of transactions in a single sitting.') }}
                </p>

                <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ auth()->check() ? route('dashboard') : route('register') }}"
                       class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-gray-900 px-6 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800 sm:w-auto dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">
                        {{ auth()->check() ? __('Open your dashboard') : __('Get started') }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>

                    <a href="#features"
                       class="inline-flex h-12 w-full items-center justify-center rounded-lg border border-gray-200 bg-white px-6 text-sm font-semibold text-gray-900 shadow-sm transition hover:bg-gray-50 sm:w-auto dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800">
                        {{ __('See what it does') }}
                    </a>
                </div>

                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Free to start · No card required') }}
                </p>
            </div>
        </div>
    </section>

    <!-- Product shot -->
    <section class="pb-20 sm:pb-24">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <x-site.app-mock />

            <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ __('Every entry shows what it was, which account it touched, and where that account stood afterwards.') }}
            </p>
        </div>
    </section>

    <!-- How it works -->
    <section class="border-t border-gray-100 py-20 sm:py-24 dark:border-gray-800">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Up and running in three steps') }}</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-300">{{ __('No setup wizard, no chart of accounts to design first.') }}</p>
            </div>

            <ol class="mt-14 grid gap-8 md:grid-cols-3">
                @foreach ($steps as $index => $step)
                    <li class="relative">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-900 text-sm font-bold text-white dark:bg-white dark:text-gray-900">
                            {{ $index + 1 }}
                        </span>
                        <h3 class="mt-4 text-lg font-semibold">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <!-- Bulk entry spotlight -->
    <section class="border-t border-gray-100 bg-gray-50 py-20 sm:py-24 dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">{{ __('Bulk entry') }}</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ __('A month of entries in one sitting') }}</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-300">
                    {{ __('The grid behaves like the spreadsheet you were already using: paste a block straight in, walk the cells with the arrow keys, duplicate a row with Ctrl+D.') }}
                </p>

                <ul class="mt-6 space-y-3 text-sm text-gray-600 dark:text-gray-400">
                    @foreach ([
                        __('Paste from a spreadsheet; accounts and categories are matched by name'),
                        __('Amounts accept arithmetic, so 250 * 2.5 resolves itself'),
                        __('A running closing balance per row, before you save anything'),
                        __('Nothing is written unless every row is valid'),
                    ] as $point)
                        <li class="flex items-start gap-3">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <x-site.bulk-mock />
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="scroll-mt-20 border-t border-gray-100 bg-gray-50 py-20 sm:py-24 dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Everything the ledger needs') }}</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-300">
                    {{ __('Built around how money actually moves: between accounts, to people, and out the door.') }}
                </p>
            </div>

            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as $feature)
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md dark:border-gray-800 dark:bg-gray-950">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-900 text-white dark:bg-white dark:text-gray-900">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feature['icon'] }}"/>
                            </svg>
                        </span>

                        <h3 class="mt-5 text-base font-semibold">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $feature['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="scroll-mt-20 py-20 sm:py-24">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div>
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Contact us') }}</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-300">
                    {{ __('Questions, bug reports or a feature you wish existed: write in and a person will read it.') }}
                </p>

                <dl class="mt-8 space-y-4 text-sm">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </span>
                        <a href="mailto:{{ config('site.contact_mail_address') }}" class="font-medium hover:underline">
                            {{ config('site.contact_mail_address') }}
                        </a>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8 dark:border-gray-800 dark:bg-gray-900">
                @if (session('contact-sent'))
                    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                        {{ __('Thanks, your message is on its way. We will reply by email.') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('site.contact') }}" class="space-y-4">
                    @csrf

                    <!-- Honeypot, hidden from people and read by the request -->
                    <div class="hidden" aria-hidden="true">
                        <label for="website">{{ __('Website') }}</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <x-ui.field :label="__('Your name')" for="contact-name" :error="$errors->get('name')">
                        <x-ui.input id="contact-name" name="name" type="text" :value="old('name')" required />
                    </x-ui.field>

                    <x-ui.field :label="__('Email')" for="contact-email" :error="$errors->get('email')">
                        <x-ui.input id="contact-email" name="email" type="email" :value="old('email')" required />
                    </x-ui.field>

                    <x-ui.field :label="__('Message')" for="contact-message" :error="$errors->get('message')">
                        <textarea id="contact-message" name="message" rows="5" required
                                  class="flex w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm
                                         transition placeholder:text-gray-400 focus:border-gray-400 focus:outline-none focus:ring-2
                                         focus:ring-gray-900/10 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100
                                         dark:focus:ring-white/10">{{ old('message') }}</textarea>
                    </x-ui.field>

                    <x-input-error :messages="$errors->get('website')" />

                    <x-ui.button class="w-full !h-11">{{ __('Send message') }}</x-ui.button>
                </form>
            </div>
        </div>
    </section>
    <!-- FAQ -->
    <section class="border-t border-gray-100 bg-gray-50 py-20 sm:py-24 dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Questions people ask first') }}</h2>

            <div class="mt-12 divide-y divide-gray-200 rounded-2xl border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-950">
                @foreach ($faqs as $index => $faq)
                    <div x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }" class="px-5 sm:px-6">
                        <button type="button" @click="open = ! open"
                                class="flex w-full items-center justify-between gap-4 py-5 text-left">
                            <span class="text-base font-medium">{{ $faq['q'] }}</span>
                            <svg class="h-5 w-5 shrink-0 text-gray-400 transition-transform" :class="open && 'rotate-180'"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-collapse>
                            <p class="pb-5 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Closing call to action -->
    <section class="border-t border-gray-100 py-20 sm:py-24 dark:border-gray-800">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-gray-900 px-6 py-16 text-center shadow-xl sm:px-16 dark:bg-white">
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-0 opacity-20">
                    <div class="absolute -left-24 -top-24 h-64 w-64 rounded-full bg-emerald-400 blur-3xl"></div>
                    <div class="absolute -bottom-24 -right-24 h-64 w-64 rounded-full bg-indigo-400 blur-3xl"></div>
                </div>

                <div class="relative">
                    <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl dark:text-gray-900">
                        {{ __('Start tracking this month') }}
                    </h2>
                    <p class="mx-auto mt-4 max-w-xl text-gray-300 dark:text-gray-600">
                        {{ __('Create a book, add your accounts, and enter the last few weeks in one sitting.') }}
                    </p>

                    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <a href="{{ auth()->check() ? route('dashboard') : route('register') }}"
                           class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-white px-6 text-sm font-semibold text-gray-900 shadow-sm transition hover:bg-gray-100 sm:w-auto dark:bg-gray-900 dark:text-white dark:hover:bg-gray-800">
                            {{ auth()->check() ? __('Open your dashboard') : __('Get started') }}
                        </a>
                        <a href="#contact"
                           class="inline-flex h-12 w-full items-center justify-center rounded-lg border border-white/30 px-6 text-sm font-semibold text-white transition hover:bg-white/10 sm:w-auto dark:border-gray-900/20 dark:text-gray-900 dark:hover:bg-gray-900/5">
                            {{ __('Ask a question') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-site-layout>
