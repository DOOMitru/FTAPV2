<x-public-layout>
    <div class="bg-white dark:bg-gray-900 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-[0.3em] mb-3">{{ __('League Schedule') }}</h2>
                <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 dark:text-white mb-6 tracking-tight">
                    {{ __('Upcoming Events') }}
                </h1>
                <p class="text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
                    {{ __('Join us at our premier venues for high-stakes competition. Register early to secure your seat at the table.') }}
                </p>
            </div>

            <div class="space-y-8">
                @forelse($upcomingTournaments as $tournament)
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-1.5 border border-gray-100 dark:border-gray-700 shadow-lg shadow-gray-200/40 dark:shadow-none transition-all hover:shadow-xl hover:shadow-indigo-500/10 duration-500">
                        <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden border border-gray-100 dark:border-gray-700">
                            <div class="grid grid-cols-1 {{ ($tournament->venue && $tournament->venue->address) ? 'lg:grid-cols-2' : '' }} gap-0">
                                
                                @if($tournament->venue && $tournament->venue->address)
                                    <!-- Mapping & Location -->
                                    <div class="relative h-64 lg:h-full min-h-[320px] group border-b lg:border-b-0 lg:border-r border-gray-100 dark:border-gray-700">
                                        <iframe 
                                            class="w-full h-full grayscale opacity-70 invert dark:invert-0 transition-all group-hover:grayscale-0 group-hover:opacity-100" 
                                            frameborder="0" 
                                            scrolling="no" 
                                            marginheight="0" 
                                            marginwidth="0" 
                                            src="https://maps.google.com/maps?q={{ urlencode($tournament->venue->address) }}&t=&z=13&ie=UTF8&iwloc=&output=embed">
                                        </iframe>
                                        <div class="absolute bottom-4 left-4 right-4">
                                            <div class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-md px-4 py-2.5 rounded-xl border border-white/20 shadow-lg flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-[9px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">{{ __('Venue Address') }}</div>
                                                    <div class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $tournament->venue->address }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <!-- Identity & Actions -->
                                <div class="p-8 lg:p-10 flex flex-col justify-center">
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <span class="px-2.5 py-0.5 bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 text-[9px] font-bold uppercase tracking-widest rounded-full border border-green-100 dark:border-green-800/50 flex items-center">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                                            {{ __('Registration Open') }}
                                        </span>
                                        <span class="px-2.5 py-0.5 bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 text-[9px] font-bold uppercase tracking-widest rounded-full border border-gray-100 dark:border-gray-700">
                                            {{ $tournament->season->name }}
                                        </span>
                                    </div>

                                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 tracking-tight">
                                        {{ $tournament->name }}
                                    </h3>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                                        <div>
                                            <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ __('Registration Closes') }}</div>
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $tournament->scheduled_at ? $tournament->scheduled_at->format('M d, Y') : $tournament->start_time->format('M d, Y') }}<br>
                                                <span class="text-indigo-600 dark:text-indigo-400">{{ $tournament->scheduled_at ? $tournament->scheduled_at->format('h:i A') : $tournament->start_time->format('h:i A') }}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ __('Location') }}</div>
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $tournament->venue->name ?? 'Location TBD' }}<br>
                                                <span class="text-gray-500 font-medium text-xs">{{ $tournament->venue->city ?? 'Main Hall' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    @if($tournament->description)
                                        <p class="text-gray-500 dark:text-gray-400 text-sm italic mb-6 border-l-2 border-indigo-100 dark:border-indigo-900/50 pl-4">
                                            "{{ $tournament->description }}"
                                        </p>
                                    @endif

                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('tournaments.show', $tournament) }}" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 dark:bg-indigo-500 text-white text-[10px] font-bold rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-400 transition-all shadow-md shadow-indigo-500/20 uppercase tracking-widest group">
                                            {{ __('Details') }}
                                            <svg class="w-3.5 h-3.5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                        </a>
                                        @auth
                                            @if($tournament->registration_open)
                                                <form action="{{ route('tournaments.register', $tournament) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="p-2.5 bg-gray-50 dark:bg-gray-900 text-gray-400 dark:text-gray-500 rounded-lg border border-gray-100 dark:border-gray-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors" title="Quick Register">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        @endauth
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700">
                        <div class="w-16 h-16 bg-white dark:bg-gray-800 rounded-xl flex items-center justify-center mx-auto mb-4 shadow-sm border border-gray-100 dark:border-gray-700">
                            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('No Scheduled Events') }}</h3>
                        <p class="text-gray-500 text-sm mt-1">{{ __('Check back soon for our next seasonal announcement.') }}</p>
                    </div>
                @endforelse
            </div>

            @if($pastTournaments->isNotEmpty())
                <div class="mt-20 pt-12 border-t border-gray-100 dark:border-gray-800">
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">{{ __('Tournament Archives') }}</h2>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-1">{{ __('Past Results') }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($pastTournaments as $tournament)
                            <a href="{{ route('tournaments.show', $tournament) }}" class="group bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-6 rounded-xl shadow-sm hover:shadow-lg hover:border-indigo-100 dark:hover:border-indigo-900/50 transition-all duration-300">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="text-[9px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">{{ $tournament->start_time->format('M Y') }}</div>
                                    <span class="px-2 py-0.5 bg-gray-50 dark:bg-gray-900 text-gray-400 dark:text-gray-500 text-[8px] font-bold uppercase rounded border border-gray-100 dark:border-gray-700">{{ __('Completed') }}</span>
                                </div>
                                <h4 class="text-md font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 transition-colors tracking-tight mb-1">{{ $tournament->name }}</h4>
                                <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-6">{{ $tournament->venue->name ?? 'Location TBD' }}</div>
                                
                                <div class="flex items-center justify-between pt-4 border-t border-gray-50 dark:border-gray-700">
                                    <div class="flex -space-x-1.5">
                                        @foreach($tournament->results->take(3) as $result)
                                            <div class="w-5 h-5 rounded-full border border-white dark:border-gray-800 bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-[7px] font-bold text-gray-400" title="{{ $result->player_name }}">
                                                {{ strtoupper(substr($result->player_name, 0, 1)) }}
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest group-hover:text-indigo-500 transition-colors flex items-center">
                                        {{ __('Results') }}
                                        <svg class="w-3 h-3 ml-1.5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-public-layout>
