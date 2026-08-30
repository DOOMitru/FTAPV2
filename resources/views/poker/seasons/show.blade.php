<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $season->name }}
                <span class="ml-2 px-2.5 py-0.5 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-400 text-[10px] font-bold rounded-full uppercase tracking-widest border border-indigo-200 dark:border-indigo-800">
                    {{ __('Season Stats') }}
                </span>
            </h2>
            <div class="flex gap-3">
                @if (auth()->user()->is_admin)
                    <a href="{{ route('poker.seasons.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        {{ __('Back') }}
                    </a>
                    <a href="{{ route('poker.seasons.edit', $season) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 transition ease-in-out duration-150 shadow-sm shadow-indigo-200 dark:shadow-none">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-5M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        {{ __('Edit') }}
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            <!-- Info Banner -->
            <div class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 overflow-hidden shadow-sm rounded-2xl p-8">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-4">
                        <div class="flex items-center text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ \Illuminate\Support\Carbon::parse($season->start_date)->format('M d, Y') }} — {{ \Illuminate\Support\Carbon::parse($season->end_date)->format('M d, Y') }}
                        </div>
                        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white sm:text-3xl leading-tight">
                            {{ $season->description ?? __('Performance tracking for the current competitive season.') }}
                        </h1>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-2 gap-4 flex-shrink-0">
                        <div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-xl border border-indigo-100 dark:border-indigo-800/50">
                            <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">{{ __('Tournaments') }}</div>
                            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalTournaments }}</div>
                        </div>
                        <div class="bg-teal-50 dark:bg-teal-900/20 p-4 rounded-xl border border-teal-100 dark:border-teal-800/50">
                            <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">{{ __('Players') }}</div>
                            <div class="text-2xl font-bold text-teal-600 dark:text-teal-400">{{ $uniquePlayersCount }}</div>
                        </div>
                        <div class="col-span-2 bg-violet-50 dark:bg-violet-900/20 p-4 rounded-xl border border-violet-100 dark:border-violet-800/50">
                            <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">{{ __('Season Points Pot') }}</div>
                            <div class="text-2xl font-bold text-violet-600 dark:text-violet-400">{{ number_format($totalPoints) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Leaderboard -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-2xl overflow-hidden">
                        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Season Leaderboard') }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ranked by accumulated points.') }}</p>
                            </div>
                            <svg class="w-6 h-6 text-indigo-500 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50/30 dark:bg-gray-900/30 text-gray-500 uppercase text-[10px] font-bold tracking-widest">
                                    <tr>
                                        <th class="px-8 py-4">{{ __('Rank') }}</th>
                                        <th class="px-4 py-4">{{ __('Player') }}</th>
                                        <th class="px-4 py-4 text-center">{{ __('Performance') }}</th>
                                        <th class="px-8 py-4 text-right">{{ __('Points') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @forelse($leaderboard as $index => $entry)
                                        <tr class="group hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors duration-150">
                                            <td class="px-8 py-6 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    @if($index === 0)
                                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 font-bold text-sm border border-amber-200 dark:border-amber-800">1</span>
                                                    @elseif($index === 1)
                                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700/40 text-slate-700 dark:text-slate-400 font-bold text-sm border border-slate-200 dark:border-slate-800">2</span>
                                                    @elseif($index === 2)
                                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-400 font-bold text-sm border border-orange-200 dark:border-orange-800">3</span>
                                                    @else
                                                        <span class="text-gray-400 font-medium ml-2">{{ $index + 1 }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-6 whitespace-nowrap">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 font-bold border border-gray-200 dark:border-gray-600">
                                                        {{ strtoupper(substr($entry['player_name'], 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $entry['player_name'] }}</div>
                                                        @if($entry['user'])
                                                            <div class="text-[10px] text-gray-500">{{ $entry['user']->email }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-6 whitespace-nowrap text-center">
                                                <div class="inline-flex items-center gap-2">
                                                    <div class="flex flex-col items-center px-2 py-1 bg-green-50 dark:bg-green-900/20 rounded-lg" title="{{ __('Wins') }}">
                                                        <span class="text-[10px] text-green-600 dark:text-green-500 font-bold">{{ $entry['wins'] }}</span>
                                                        <span class="text-[8px] uppercase tracking-tighter text-green-400">{{ __('Win') }}</span>
                                                    </div>
                                                    <div class="flex flex-col items-center px-2 py-1 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg" title="{{ __('Top 3') }}">
                                                        <span class="text-[10px] text-indigo-600 dark:text-indigo-500 font-bold">{{ $entry['top3'] }}</span>
                                                        <span class="text-[8px] uppercase tracking-tighter text-indigo-400">{{ __('T3') }}</span>
                                                    </div>
                                                    <div class="flex flex-col items-center px-2 py-1 bg-gray-50 dark:bg-gray-700 rounded-lg" title="{{ __('Played') }}">
                                                        <span class="text-[10px] text-gray-600 dark:text-gray-400 font-bold">{{ $entry['played'] }}</span>
                                                        <span class="text-[8px] uppercase tracking-tighter text-gray-400">{{ __('Play') }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 whitespace-nowrap text-right">
                                                <div class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">
                                                    {{ number_format($entry['points']) }}
                                                </div>
                                                <div class="text-[9px] text-gray-400 uppercase font-bold tracking-widest">{{ __('Points') }}</div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-8 py-16 text-center text-gray-500 italic">
                                                {{ __('No results recorded yet for this season.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-8">
                    <!-- Venue Stats -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-2xl p-6">
                        <h3 class="font-bold text-gray-900 dark:text-white mb-6 text-sm flex items-center">
                            <span class="w-1 h-4 bg-indigo-500 rounded-full mr-2"></span>
                            {{ __('Venue Hostings') }}
                        </h3>
                        <div class="space-y-5">
                            @forelse($venueStats as $venue)
                                @php $percentage = $totalTournaments > 0 ? ($venue['count'] / $totalTournaments) * 100 : 0; @endphp
                                <div class="space-y-1.5">
                                    <div class="flex justify-between items-center text-[11px] font-bold">
                                        <span class="text-gray-700 dark:text-gray-300">{{ $venue['name'] }}</span>
                                        <span class="text-gray-500">{{ $venue['count'] }}</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-gray-100 dark:bg-gray-900 rounded-full overflow-hidden">
                                        <div class="h-full bg-indigo-500 rounded-full transition-all duration-700" style="width: {{ $percentage }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-gray-400 text-xs italic">{{ __('No data available.') }}</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Simple Schedule -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-2xl p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="font-bold text-gray-900 dark:text-white text-sm flex items-center">
                                <span class="w-1 h-4 bg-teal-500 rounded-full mr-2"></span>
                                {{ __('Schedule') }}
                            </h3>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">{{ $totalTournaments }} {{ __('Total') }}</span>
                        </div>
                        <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                            @forelse($season->tournaments->sortByDesc('start_time') as $tournament)
                                @php $isFuture = \Illuminate\Support\Carbon::parse($tournament->start_time)->isFuture(); @endphp
                                <a href="{{ auth()->user()->is_admin ? route('poker.tournaments.edit', $tournament) : route('tournaments.show', $tournament) }}" class="block p-3 rounded-xl border border-transparent hover:border-gray-100 dark:hover:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-all group">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-xs font-bold text-gray-900 dark:text-white truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                                {{ $tournament->name }}
                                            </div>
                                            <div class="text-[10px] text-gray-500 mt-0.5 truncate">{{ $tournament->venue->name ?? 'TBD' }}</div>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <div class="text-[10px] font-bold text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Carbon::parse($tournament->start_time)->format('M d') }}</div>
                                            @if($isFuture)
                                                <span class="inline-block text-[8px] font-bold text-teal-600 dark:text-teal-500 uppercase tracking-tighter">{{ __('Next') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div class="text-gray-400 text-xs italic">{{ __('No tournaments.') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(99, 102, 241, 0.1); border-radius: 10px; }
        .custom-scrollbar:hover::-webkit-scrollbar-thumb { background: rgba(99, 102, 241, 0.3); }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(99, 102, 241, 0.2); }
    </style>
</x-app-layout>
