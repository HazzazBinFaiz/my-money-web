@php
    $reportMenu = [
        ['label' => __('Expense Overview'), 'route' => 'reports.expenses'],
        ['label' => __('Income Overview'), 'route' => 'reports.incomes'],
        ['label' => __('Expense Flow'), 'route' => 'reports.expense-flow'],
        ['label' => __('Income Flow'), 'route' => 'reports.income-flow'],
        ['label' => __('Account Analysis'), 'route' => 'reports.accounts'],
        ['label' => __('Category Analysis'), 'route' => 'reports.categories'],
    ];
@endphp

<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('transactions.index')" :active="request()->routeIs('transactions.*')">
                        {{ __('Transactions') }}
                    </x-nav-link>
                    <x-nav-link :href="route('accounts.index')" :active="request()->routeIs('accounts.*')">
                        {{ __('Accounts') }}
                    </x-nav-link>
                    <x-nav-link :href="route('contacts.index')" :active="request()->routeIs('contacts.*')">
                        {{ __('Contacts') }}
                    </x-nav-link>
                    <x-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">
                        {{ __('Categories') }}
                    </x-nav-link>
                    <x-nav-link :href="route('books.index')" :active="request()->routeIs('books.*')">
                        {{ __('Books') }}
                    </x-nav-link>
                    <!-- Reports -->
                    <div class="relative flex items-center" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = ! open"
                                class="inline-flex h-16 items-center gap-1 border-b-2 px-1 text-sm font-medium leading-5 transition
                                       focus:outline-none {{ request()->routeIs('reports.*')
                                           ? 'border-indigo-400 text-gray-900 dark:border-indigo-600 dark:text-gray-100'
                                           : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                            {{ __('Reports') }}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-cloak @click="open = false"
                             class="absolute start-0 top-14 z-50 w-56 overflow-hidden rounded-md border border-gray-200 bg-white py-1
                                    shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            @foreach ($reportMenu as $item)
                                @if ($item['route'])
                                    <a href="{{ route($item['route']) }}"
                                       class="block px-4 py-2 text-sm transition hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs($item['route'])
                                           ? 'font-semibold text-gray-900 dark:text-white'
                                           : 'text-gray-600 dark:text-gray-300' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @else
                                    <span class="flex items-center justify-between px-4 py-2 text-sm text-gray-400 dark:text-gray-500">
                                        {{ $item['label'] }}
                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium dark:bg-gray-700">{{ __('Soon') }}</span>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-book-switcher :books="$availableBooks ?? collect()" :current="$currentBook ?? null"
                                         class="border-b border-gray-100 dark:border-gray-700" />

                        <x-theme-switcher class="border-b border-gray-100 dark:border-gray-700" />

                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('transactions.index')" :active="request()->routeIs('transactions.*')">
                {{ __('Transactions') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('accounts.index')" :active="request()->routeIs('accounts.*')">
                {{ __('Accounts') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('contacts.index')" :active="request()->routeIs('contacts.*')">
                {{ __('Contacts') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">
                {{ __('Categories') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('books.index')" :active="request()->routeIs('books.*')">
                {{ __('Books') }}
            </x-responsive-nav-link>

            <p class="px-4 pb-1 pt-3 text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Reports') }}</p>

            @foreach ($reportMenu as $item)
                @if ($item['route'])
                    <x-responsive-nav-link :href="route($item['route'])" :active="request()->routeIs($item['route'])">
                        {{ $item['label'] }}
                    </x-responsive-nav-link>
                @else
                    <span class="block border-l-4 border-transparent py-2 pe-4 ps-3 text-base font-medium text-gray-400 dark:text-gray-500">
                        {{ $item['label'] }} · {{ __('Soon') }}
                    </span>
                @endif
            @endforeach
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-book-switcher :books="$availableBooks ?? collect()" :current="$currentBook ?? null" />

                <x-theme-switcher />

                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
