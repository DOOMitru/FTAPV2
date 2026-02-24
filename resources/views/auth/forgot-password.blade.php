<x-public-layout>
    <div class="min-h-screen flex items-center justify-center py-16 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-6xl w-full">
            <div class="grid md:grid-cols-2 gap-12 lg:gap-16 items-center">
                <!-- Left Column - Forgot Password Form -->
                <div class="w-full">
                    <div class="bg-white dark:bg-gray-800 py-10 px-6 shadow-xl sm:rounded-lg sm:px-12">
                        <div class="mb-8">
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-3">
                                Reset your password
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.
                            </p>
                        </div>

                        <!-- Session Status -->
                        <x-auth-session-status class="mb-6" :status="session('status')" />

                        <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
                            @csrf

                            <!-- Email Address -->
                            <div>
                                <x-input-label for="email" :value="__('Email')" class="text-gray-700 dark:text-gray-300 mb-2" />
                                <x-text-input id="email" class="block mt-1 w-full bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-indigo-500 focus:border-indigo-500" type="email" name="email" :value="old('email')" required autofocus />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div class="flex items-center justify-between pt-2">
                                <a class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300" href="{{ route('login') }}">
                                    {{ __('Back to login') }}
                                </a>

                                <x-primary-button class="ms-4 py-3 bg-indigo-600 hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 ring-indigo-500">
                                    {{ __('Email Password Reset Link') }}
                                </x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column - Branding & Info -->
                    <div class="flex flex-col md:flex-row items-center gap-6 mb-8">
                        <img src="{{ asset('images/hero_logo.png') }}" alt="First to Act Poker League" class="h-20 w-auto">
                        <h1 class="text-4xl lg:text-5xl font-extrabold text-gray-900 dark:text-white">
                            First to Act Poker League
                        </h1>
                    </div>
                    <p class="text-lg text-gray-600 dark:text-gray-400 leading-relaxed">
                        Join the most exciting amateur poker league. Compete in tournaments, climb the leaderboard, and become the champion.
                    </p>

                    <div class="space-y-6 pt-4">
                        <div class="flex items-start gap-4">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Regular Tournaments</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Compete in weekly tournaments with points counting towards the season finale</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Fair Play</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Strict adherence to rules ensuring a fair game for everyone</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Community</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Join a passionate community of poker enthusiasts</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
