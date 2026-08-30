<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $venue->name }}
                <span class="ml-2 px-2.5 py-0.5 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-400 text-[10px] font-bold rounded-full uppercase tracking-widest border border-indigo-200 dark:border-indigo-800">
                    {{ __('Venue Statistics') }}
                </span>
            </h2>
            <div class="flex gap-3">
                <a href="{{ route('poker.venues.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    {{ __('Back') }}
                </a>
                <a href="{{ route('poker.venues.edit', $venue) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 transition ease-in-out duration-150 shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-5M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    {{ __('Edit') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            <!-- Venue Summary Header -->
            <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 overflow-hidden shadow-sm rounded-2xl p-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
                    <!-- Left: Mapping & Location -->
                    <div class="space-y-4">
                        @if($venue->address)
                            <div class="w-full h-80 rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-inner group relative">
                                <div class="absolute inset-0 bg-indigo-500/5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10"></div>
                                <iframe 
                                    class="w-full h-full grayscale opacity-80 invert dark:invert-0 transition-all group-hover:grayscale-0 group-hover:opacity-100" 
                                    frameborder="0" 
                                    scrolling="no" 
                                    marginheight="0" 
                                    marginwidth="0" 
                                    src="https://maps.google.com/maps?q={{ urlencode($venue->address) }}&t=&z=13&ie=UTF8&iwloc=&output=embed">
                                </iframe>
                            </div>
                            <div class="flex items-center text-xs font-bold text-gray-500 dark:text-gray-400 px-2 uppercase tracking-widest">
                                <svg class="w-3.5 h-3.5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $venue->address }}
                            </div>
                        @else
                            <div class="w-full h-80 rounded-2xl bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-200 dark:border-gray-700 flex flex-col items-center justify-center text-center p-8">
                                <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <p class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-1">{{ __('No Address Provided') }}</p>
                                <p class="text-xs text-gray-500">{{ __('Edit the venue to include a location and enable the map.') }}</p>
                            </div>
                        @endif
                    </div>

                    <!-- Right: Identity & KPIs -->
                    <div class="space-y-8">
                        <div class="space-y-4">
                            <div class="flex items-center text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">
                                <span class="px-2 py-0.5 bg-indigo-50 dark:bg-indigo-900/30 rounded border border-indigo-100 dark:border-indigo-800/50">
                                    {{ __('Host Venue') }}
                                </span>
                            </div>
                            <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white leading-tight">
                                {{ $venue->name }}
                            </h1>
                            @if($venue->description)
                                <p class="text-gray-500 dark:text-gray-400 text-sm leading-relaxed">
                                    {{ $venue->description }}
                                </p>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-50/50 dark:bg-gray-900/40 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/50 group hover:border-indigo-500/30 transition-colors">
                                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 flex items-center">
                                    <svg class="w-3 h-3 mr-1.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    {{ __('Tournaments') }}
                                </div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalTournaments }}</div>
                            </div>
                            <div class="bg-gray-50/50 dark:bg-gray-900/40 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/50 group hover:border-teal-500/30 transition-colors">
                                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 flex items-center">
                                    <svg class="w-3 h-3 mr-1.5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                    {{ __('Unique Players') }}
                                </div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $uniqueVenuePointPlayers }}</div>
                            </div>
                            <div class="bg-gray-50/50 dark:bg-gray-900/40 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/50 group hover:border-violet-500/30 transition-colors">
                                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 flex items-center">
                                    <svg class="w-3 h-3 mr-1.5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                    {{ __('Tournament Pts') }}
                                </div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalTournamentPoints) }}</div>
                            </div>
                            <div class="bg-gray-50/50 dark:bg-gray-900/40 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/50 group hover:border-amber-500/30 transition-colors">
                                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 flex items-center">
                                    <svg class="w-3 h-3 mr-1.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ __('Venue Points') }}
                                </div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalVenuePoints) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Venue Points Leaderboard -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-2xl overflow-hidden">
                        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Venue Points Leaderboard') }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ranked by dedicated venue points.') }}</p>
                            </div>
                            <div class="p-2 bg-teal-50 dark:bg-teal-900/30 rounded-xl">
                                <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50/30 dark:bg-gray-900/30 text-gray-500 uppercase text-[10px] font-bold tracking-widest">
                                    <tr>
                                        <th class="px-8 py-4 text-center w-20">{{ __('Rank') }}</th>
                                        <th class="px-4 py-4">{{ __('Player') }}</th>
                                        <th class="px-4 py-4 text-center">{{ __('Earned Count') }}</th>
                                        <th class="px-8 py-4 text-right">{{ __('Total Points') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @forelse($venueLeaderboard as $index => $entry)
                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/40 transition-colors">
                                            <td class="px-8 py-6 text-center">
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg {{ $index < 3 ? 'bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-400 font-bold' : 'text-gray-500' }}">
                                                    {{ $index + 1 }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-6 whitespace-nowrap">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 font-bold border border-gray-200 dark:border-gray-600 uppercase">
                                                        {{ strtoupper(substr($entry['user_name'], 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $entry['user_name'] }}</div>
                                                        <div class="text-[9px] text-gray-400 uppercase tracking-tighter">
                                                            {{ __('Last earned') }}: {{ \Illuminate\Support\Carbon::parse($entry['last_earned'])->format('M d, Y') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-6 text-center text-xs font-bold text-gray-600 dark:text-gray-400 tracking-widest">
                                                {{ $entry['count'] }}
                                            </td>
                                            <td class="px-8 py-6 text-right">
                                                <div class="text-xl font-bold text-teal-600 dark:text-teal-400 tracking-tight">{{ number_format($entry['total_amount']) }}</div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-8 py-16 text-center text-gray-400 italic font-medium">
                                                {{ __('No venue points have been recorded for this location yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Tournaments Side Panel -->
                <div>
                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-2xl overflow-hidden">
                        <div class="px-6 py-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __('Tournament History') }}</h3>
                            <span class="text-[10px] font-bold text-gray-400">{{ $totalTournaments }}</span>
                        </div>
                        <div class="divide-y divide-gray-50 dark:divide-gray-700">
                            @forelse($venue->tournaments->sortByDesc('start_time')->take(10) as $tournament)
                                <a href="{{ route('tournaments.show', $tournament) }}" class="block px-6 py-5 hover:bg-gray-50/50 dark:hover:bg-gray-900/40 transition-all group">
                                    <div class="flex justify-between items-start gap-4">
                                        <div class="min-w-0">
                                            <div class="text-xs font-bold text-gray-900 dark:text-white truncate group-hover:text-indigo-600 transition-colors">{{ $tournament->name }}</div>
                                            <div class="text-[9px] text-gray-500 mt-0.5">{{ \Illuminate\Support\Carbon::parse($tournament->start_time)->format('M d, Y') }}</div>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <div class="text-[10px] font-black text-indigo-600 uppercase tracking-tighter">{{ $tournament->results->count() }} {{ __('Rslts') }}</div>
                                            <div class="text-[9px] text-gray-400 mt-0.5">{{ $tournament->season->name }}</div>
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div class="px-6 py-8 text-center text-gray-400 text-xs italic">
                                    {{ __('No tournaments hosted yet.') }}
                                </div>
                            @endforelse
                        </div>
                        @if($totalTournaments > 10)
                            <div class="px-6 py-4 bg-gray-50/30 dark:bg-gray-900/30 border-t border-gray-50 dark:border-gray-700 text-center">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ __('Showing latest 10') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
