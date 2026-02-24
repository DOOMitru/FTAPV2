<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'First to Act Poker') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png"/>
        <script>
            // On page load or when changing themes, best to add inline in `head` to avoid FOUC
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            } else {
                document.documentElement.classList.remove('dark')
            }

            function toggleTheme() {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark')
                    localStorage.theme = 'light'
                } else {
                    document.documentElement.classList.add('dark')
                    localStorage.theme = 'dark'
                }
            }
        </script>
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900 dark:bg-gray-900 dark:text-white">
        <div class="min-h-screen flex flex-col">
            <!-- Navigation -->
            <nav class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700" x-data="{ mobileMenuOpen: false }">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <a href="{{ route('home') }}" class="text-2xl font-bold text-indigo-500">
                                    <img src="{{ asset('images/header_logo.png') }}" alt="FTAP Logo" class="h-10 w-auto">
                                </a>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden sm:ml-6 sm:flex sm:items-center sm:space-x-4 md:space-x-6">
                                <x-nav-link :href="route('home')" :active="request()->routeIs('home')">
                                    {{ __('Home') }}
                                </x-nav-link>
                                
                                <x-nav-link :href="route('about.index')" :active="request()->routeIs('about.*')">
                                    {{ __('About') }}
                                </x-nav-link>

                                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                    <button @click="open = !open" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                        <div>Rules</div>
                                        <div class="ml-1">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                    <div x-show="open"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         class="absolute z-50 left-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 top-full"
                                         style="display: none;"
                                         @click="open = false">
                                        <div class="py-1">
                                            <a href="{{ route('rules.tournament') }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Rules & Regulations</a>
                                            <a href="{{ route('rules.betting') }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Betting & Conduct</a>
                                            <a href="{{ route('rules.texas-holdem') }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Texas Hold'em</a>
                                            <a href="{{ route('rules.points-structure') }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Points Structure</a>
                                        </div>
                                    </div>
                                </div>

                                <x-nav-link :href="route('events')" :active="request()->routeIs('events')">
                                    {{ __('Events') }}
                                </x-nav-link>

                                <x-nav-link :href="route('contact')" :active="request()->routeIs('contact')">
                                    {{ __('Contact Us') }}
                                </x-nav-link>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            <button onclick="toggleTheme()" class="mr-4 rounded-lg p-2.5 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-700">
                                <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5 dark:block" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                                <svg id="theme-toggle-light-icon" class="block w-5 h-5 dark:hidden" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 100 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
                            </button>
                            @if (Route::has('login'))
                                <div class="p-6 text-right z-10">
                                    @auth
                                        <a href="{{ url('/dashboard') }}" class="font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">Dashboard</a>
                                    @else
                                        <a href="{{ route('login') }}" class="font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">Log in</a>

                                        @if (Route::has('register'))
                                            <a href="{{ route('register') }}" class="ml-4 font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">Register</a>
                                        @endif
                                    @endauth
                                </div>
                            @endif
                        </div>

                        <!-- Mobile menu button -->
                        <div class="flex items-center sm:hidden">
                            <button onclick="toggleTheme()" class="mr-2 rounded-lg p-2.5 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 focus:outline-none">
                                <svg id="theme-toggle-dark-icon-mobile" class="hidden w-5 h-5 dark:block" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                                <svg id="theme-toggle-light-icon-mobile" class="block w-5 h-5 dark:hidden" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 100 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
                            </button>
                            <button @click="mobileMenuOpen = !mobileMenuOpen" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{'hidden': mobileMenuOpen, 'inline-flex': !mobileMenuOpen }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{'hidden': !mobileMenuOpen, 'inline-flex': mobileMenuOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile menu -->
                <div :class="{'block': mobileMenuOpen, 'hidden': !mobileMenuOpen}" class="hidden sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <x-responsive-nav-link :href="route('home')" :active="request()->routeIs('home')">
                            {{ __('Home') }}
                        </x-responsive-nav-link>

                        <x-responsive-nav-link :href="route('about.index')" :active="request()->routeIs('about.*')">
                            {{ __('About') }}
                        </x-responsive-nav-link>

                        <!-- Rules submenu -->
                        <div x-data="{ rulesOpen: false }">
                            <button @click="rulesOpen = !rulesOpen" class="w-full flex items-center justify-between px-4 py-2 text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <span>Rules</span>
                                <svg class="h-5 w-5 transform transition-transform" :class="{'rotate-180': rulesOpen}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="rulesOpen" class="pl-4 space-y-1">
                                <a href="{{ route('rules.tournament') }}" class="block px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">Rules & Regulations</a>
                                <a href="{{ route('rules.betting') }}" class="block px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">Betting & Conduct</a>
                                <a href="{{ route('rules.texas-holdem') }}" class="block px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">Texas Hold'em</a>
                                <a href="{{ route('rules.points-structure') }}" class="block px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">Points Structure</a>
                            </div>
                        </div>

                        <x-responsive-nav-link :href="route('events')" :active="request()->routeIs('events')">
                            {{ __('Events') }}
                        </x-responsive-nav-link>

                        <x-responsive-nav-link :href="route('contact')" :active="request()->routeIs('contact')">
                            {{ __('Contact Us') }}
                        </x-responsive-nav-link>

                        @if (Route::has('login'))
                            <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
                                @auth
                                    <a href="{{ url('/dashboard') }}" class="block px-4 py-2 text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Dashboard</a>
                                @else
                                    <a href="{{ route('login') }}" class="block px-4 py-2 text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Log in</a>
                                    @if (Route::has('register'))
                                        <a href="{{ route('register') }}" class="block px-4 py-2 text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Register</a>
                                    @endif
                                @endauth
                            </div>
                        @endif
                    </div>
                </div>
            </nav>

            <!-- Page Content -->
            <main class="flex-grow">
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer class="bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-500 dark:text-gray-400">
                    <p>&copy; {{ date('Y') }} First to Act Poker. All rights reserved.</p>
                </div>
            </footer>
        </div>
    </body>
</html>
