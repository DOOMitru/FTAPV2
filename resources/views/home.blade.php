<x-public-layout>
    <div class="relative bg-white dark:bg-gray-900 overflow-hidden">
        <div class="max-w-7xl mx-auto">
            <div class="md:grid md:grid-cols-2 md:gap-8 md:items-center">
                <!-- Text Content -->
                <div class="px-4 py-12 sm:px-6 md:px-8">
                    <main>
                        <div class="sm:text-center md:text-left">
                            <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 dark:text-white sm:text-5xl md:text-6xl">
                                <span class="block xl:inline">First to Act</span>
                                <span class="block text-indigo-600 dark:text-indigo-500 xl:inline">Poker League</span>
                            </h1>
                            <p class="mt-3 text-base text-gray-500 dark:text-gray-400 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl md:mx-0">
                                Join the most exciting amateur poker league. Compete in tournaments, climb the leaderboard, and become the champion.
                            </p>
                            <div class="mt-5 sm:mt-8 sm:flex sm:justify-center md:justify-start">
                                <div class="rounded-md shadow">
                                    <a href="{{ route('register') }}" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 md:py-4 md:text-lg md:px-10">
                                        Join Now
                                    </a>
                                </div>
                                <div class="mt-3 sm:mt-0 sm:ml-3">
                                    <a href="{{ route('about.index') }}" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 dark:text-indigo-300 dark:bg-indigo-900 dark:hover:bg-indigo-800 md:py-4 md:text-lg md:px-10">
                                        Learn More
                                    </a>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
                
                <!-- Hero Image -->
                <div class="px-4 py-6 sm:px-6 md:px-8 md:py-12 flex items-center justify-center md:justify-end">
                    <img class="w-full h-auto max-w-[200px] md:max-w-sm lg:max-w-md" src="{{ asset('images/hero_logo.png') }}" alt="First to Act Poker League">
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="py-12 bg-gray-50 dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center">
                <h2 class="text-base text-indigo-600 dark:text-indigo-400 font-semibold tracking-wide uppercase">Competition</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                    A better way to play poker
                </p>
                <p class="mt-4 max-w-2xl text-xl text-gray-500 dark:text-gray-400 lg:mx-auto">
                    Experience structured tournaments, fair play, and a competitive environment with our comprehensive rules and point system.
                </p>
            </div>

            <div class="mt-10">
                <dl class="space-y-10 md:space-y-0 md:grid md:grid-cols-2 md:gap-x-8 md:gap-y-10">
                    <div class="relative">
                        <dt>
                            <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white">
                                <!-- Heroicon name: globe-alt -->
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                </svg>
                            </div>
                            <p class="ml-16 text-lg leading-6 font-medium text-gray-900 dark:text-white">Tournaments</p>
                        </dt>
                        <dd class="mt-2 ml-16 text-base text-gray-500 dark:text-gray-400">
                            Regular tournaments with points counting towards the season finale.
                        </dd>
                    </div>

                    <div class="relative">
                        <dt>
                            <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white">
                                <!-- Heroicon name: scale -->
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                                </svg>
                            </div>
                            <p class="ml-16 text-lg leading-6 font-medium text-gray-900 dark:text-white">Fair Play</p>
                        </dt>
                        <dd class="mt-2 ml-16 text-base text-gray-500 dark:text-gray-400">
                            Strict adherence to rules ensuring a fair game for everyone.
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <!-- Season Info Section -->
    <div class="py-16 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center mb-12">
                <h2 class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-[0.3em] mb-4">{{ __('League Schedule') }}</h2>
                <p class="mt-2 text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                    {{ $currentSeason ? ($currentSeason->name . ' ' . __('is Here')) : __('Season Launching Soon') }}
                </p>
                <p class="mt-4 max-w-2xl text-lg text-gray-500 dark:text-gray-400 lg:mx-auto">
                    {{ __('Track the latest seasonal progress, upcoming high-stakes tournaments, and the race to the championship finale.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Season Status Card -->
                <div class="bg-indigo-50 dark:bg-indigo-900/20 p-8 rounded-2xl border border-indigo-100 dark:border-indigo-800 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="p-3 bg-indigo-600 rounded-lg text-white">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('Season Status') }}</h3>
                    </div>
                    @if($currentSeason)
                        <div class="space-y-4">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ __('Active') }}</span>
                                <span class="text-gray-900 dark:text-white font-bold">{{ $currentSeason->name }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ __('Duration') }}</span>
                                <span class="text-gray-900 dark:text-white font-medium">{{ $currentSeason->start_date?->format('M Y') ?? '?' }} - {{ $currentSeason->end_date?->format('M Y') ?? '?' }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ __('Prize Pool') }}</span>
                                <span class="text-indigo-600 dark:text-indigo-400 font-black tracking-tight">{{ __('Dynamic') }}</span>
                            </div>
                        </div>
                    @else
                        <div class="py-6 text-center italic text-gray-400 text-sm">
                            {{ __('No active season found.') }}
                        </div>
                    @endif
                </div>

                <!-- Upcoming Events Card -->
                <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="p-3 bg-rose-600 rounded-lg text-white">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('Next Event') }}</h3>
                    </div>
                    @if($nextTournament)
                        <div class="space-y-4">
                            <div>
                                <p class="text-gray-900 dark:text-white font-bold text-lg leading-tight mb-1">{{ $nextTournament->name }}</p>
                                <p class="text-indigo-600 dark:text-indigo-400 font-bold text-sm">
                                    {{ $nextTournament->start_time?->format('F d, Y') ?? __('TBD') }}<br>
                                    <span class="opacity-70">{{ $nextTournament->start_time?->format('h:i A') ?? '' }}</span>
                                </p>
                            </div>
                            <div class="pt-4 border-t border-gray-50 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed italic">
                                    {{ $nextTournament->venue?->name ?? __('Location TBD') }}
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="py-6 text-center italic text-gray-400 text-sm">
                            {{ __('No upcoming events scheduled.') }}
                        </div>
                    @endif
                </div>

                <!-- Point System Highlights -->
                <div class="bg-gray-900 dark:bg-indigo-950 p-8 rounded-2xl shadow-xl transition-all duration-300 hover:scale-[1.02]">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="p-3 bg-yellow-400 rounded-lg text-gray-900">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white">Season Finale</h3>
                    </div>
                    <div class="space-y-4 text-gray-300">
                        <p class="text-sm leading-relaxed">
                            The top 20 players on the leaderboard at the end of the season qualify for the <span class="text-yellow-400 font-bold underline decoration-yellow-400/30 underline-offset-4">Grand Championship</span>.
                        </p>
                        <div class="pt-2">
                             <a href="#" class="text-white hover:text-yellow-400 font-semibold text-sm flex items-center gap-1 transition-colors">
                                View Point System
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                             </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sponsors Section -->
    <div class="py-16 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-base text-indigo-600 dark:text-indigo-400 font-semibold tracking-wide uppercase">Our Sponsors</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                    Proudly Supported By
                </p>
                <p class="mt-4 max-w-2xl text-xl text-gray-500 dark:text-gray-400 mx-auto">
                    Thank you to our amazing sponsors who make this league possible
                </p>
            </div>

            <div class="grid grid-cols-2 gap-8 md:grid-cols-3 lg:grid-cols-5">
                <!-- Sponsor 1: Ace High Beverages -->
                <div class="col-span-1 flex justify-center items-center p-8 bg-gray-50 dark:bg-gray-800 rounded-lg hover:shadow-lg transition-shadow duration-300">
                    <div class="text-center">
                        <div class="text-4xl font-bold text-red-600 dark:text-red-500 mb-2">A♥</div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">Ace High</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Beverages</div>
                    </div>
                </div>

                <!-- Sponsor 2: Full House Hospitality -->
                <div class="col-span-1 flex justify-center items-center p-8 bg-gray-50 dark:bg-gray-800 rounded-lg hover:shadow-lg transition-shadow duration-300">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-orange-600 dark:text-orange-500 mb-2">🏠</div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">Full House</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Hospitality</div>
                    </div>
                </div>

                <!-- Sponsor 3: Straight Tech Solutions -->
                <div class="col-span-1 flex justify-center items-center p-8 bg-gray-50 dark:bg-gray-800 rounded-lg hover:shadow-lg transition-shadow duration-300">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-500 mb-2">⚡</div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">Straight Tech</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Solutions</div>
                    </div>
                </div>

                <!-- Sponsor 4: All-In Athletics -->
                <div class="col-span-1 flex justify-center items-center p-8 bg-gray-50 dark:bg-gray-800 rounded-lg hover:shadow-lg transition-shadow duration-300">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600 dark:text-green-500 mb-2">💪</div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">All-In</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Athletics</div>
                    </div>
                </div>

                <!-- Sponsor 5: Pocket Kings Financial -->
                <div class="col-span-1 flex justify-center items-center p-8 bg-gray-50 dark:bg-gray-800 rounded-lg hover:shadow-lg transition-shadow duration-300">
                    <div class="text-center">
                        <div class="text-4xl font-bold text-yellow-600 dark:text-yellow-500 mb-2">K♠K♣</div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">Pocket Kings</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Financial</div>
                    </div>
                </div>
            </div>

            <!-- Optional: Add a call to action for sponsors -->
            <div class="mt-12 text-center">
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Interested in becoming a sponsor?
                </p>
                <a href="mailto:sponsors@firsttoact.com" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition-colors duration-300">
                    Contact Us
                </a>
            </div>
        </div>
    </div>
</x-public-layout>
