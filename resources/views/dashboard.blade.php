<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Player Command Center') }}
            </h2>
            <div class="px-3 py-1 bg-indigo-50 dark:bg-indigo-900/30 rounded-full border border-indigo-100 dark:border-indigo-800/50 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">
                {{ Auth::user()->first_name }} {{ Auth::user()->last_name }}
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            <!-- Quick Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">{{ __('Career Points') }}</div>
                    <div class="text-3xl font-black text-gray-900 dark:text-white">{{ number_format($totalPoints) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">{{ __('Events Played') }}</div>
                    <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ $tournamentsPlayed }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">{{ __('Podium Finishes') }}</div>
                    <div class="text-3xl font-black text-amber-500">{{ $podiums }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">{{ __('Tournament Wins') }}</div>
                    <div class="text-3xl font-black text-teal-600">{{ $wins }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Sidebar: Current Season & Ranking -->
                <div class="space-y-8">
                    <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-2xl p-6 text-white shadow-xl shadow-indigo-500/20">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="text-sm font-bold opacity-80 uppercase tracking-wider mb-1">{{ __('Active Season') }}</h3>
                                <p class="text-xl font-black">{{ $currentSeason->name ?? __('None') }}</p>
                            </div>
                            <div class="p-2 bg-white/10 rounded-xl backdrop-blur-md">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-white/10 rounded-xl p-4 backdrop-blur-md">
                                <div class="text-[9px] font-bold opacity-70 uppercase tracking-widest mb-1">{{ __('Season Rank') }}</div>
                                <div class="text-2xl font-black">#{{ $seasonRank ?? '—' }}</div>
                            </div>
                            <div class="bg-white/10 rounded-xl p-4 backdrop-blur-md">
                                <div class="text-[9px] font-bold opacity-70 uppercase tracking-widest mb-1">{{ __('Season Points') }}</div>
                                <div class="text-2xl font-black">{{ number_format($seasonPoints) }}</div>
                            </div>
                        </div>
                        
                        @if($currentSeason)
                            <a href="{{ route('poker.seasons.show', $currentSeason) }}" class="mt-6 w-full inline-flex items-center justify-center px-4 py-3 bg-white text-indigo-700 text-xs font-bold rounded-xl hover:bg-indigo-50 transition-colors uppercase tracking-widest">
                                {{ __('Full Season Stats') }}
                            </a>
                        @endif
                    </div>

                    <!-- Points Structure Quick Reference -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 flex justify-between items-center">
                            <h3 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-wider">{{ __('Points Structure') }}</h3>
                            <a href="{{ route('rules.points-structure') }}" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-700 uppercase tracking-tighter">{{ __('Full Rules') }}</a>
                        </div>
                        <div class="p-2">
                             @php $pts = \App\Models\PointsStructure::orderBy('place')->take(5)->get(); @endphp
                             @foreach($pts as $p)
                                <div class="flex justify-between items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-xl transition-colors">
                                    <span class="text-xs font-bold text-gray-500">{{ $p->place }}{{ match($p->place) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }} {{ __('Place') }}</span>
                                    <span class="text-sm font-black text-gray-900 dark:text-white">{{ number_format($p->points) }} {{ __('pts') }}</span>
                                </div>
                             @endforeach
                        </div>
                    </div>
                </div>

                <!-- Main Section: Upcoming Tournaments & Recent Results -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Upcoming Tournaments -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Upcoming Tournaments') }}</h3>
                            <div class="flex items-center text-xs font-bold text-indigo-600 dark:text-indigo-400 px-2.5 py-1 bg-indigo-50 dark:bg-indigo-900/40 rounded-lg">
                                {{ $upcomingTournaments->count() }} {{ __('Events') }}
                            </div>
                        </div>
                        <div class="divide-y divide-gray-50 dark:divide-gray-700">
                            @forelse($upcomingTournaments as $tournament)
                                @php $isReg = $tournament->registrants->contains('user_id', Auth::id()); @endphp
                                <div class="px-8 py-6 flex flex-col md:flex-row md:items-center justify-between gap-6 group">
                                    <div class="flex items-start gap-4">
                                        <div class="w-12 h-12 rounded-2xl bg-gray-50 dark:bg-gray-900 flex flex-col items-center justify-center border border-gray-100 dark:border-gray-700 flex-shrink-0">
                                            <span class="text-[8px] font-black text-indigo-500 uppercase">{{ \Illuminate\Support\Carbon::parse($tournament->scheduled_at)->format('M') }}</span>
                                            <span class="text-lg font-black text-gray-900 dark:text-white">{{ \Illuminate\Support\Carbon::parse($tournament->scheduled_at)->format('d') }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 transition-colors truncate">{{ $tournament->name }}</h4>
                                            <div class="flex items-center gap-4 mt-1">
                                                <span class="flex items-center text-[10px] text-gray-400 font-bold uppercase tracking-tighter" title="Registration Deadline">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    {{ \Illuminate\Support\Carbon::parse($tournament->scheduled_at)->format('h:i A') }}
                                                </span>
                                                <span class="flex items-center text-[10px] text-gray-400 font-bold uppercase tracking-tighter">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                    {{ $tournament->venue->name ?? 'TBD' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-4">
                                        @if($isReg)
                                            <span class="inline-flex items-center px-3 py-1 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 text-[10px] font-black rounded-lg border border-green-200 dark:border-green-800 uppercase tracking-widest">
                                                <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                {{ __('Registered') }}
                                            </span>
                                        @else
                                            <form action="{{ route('poker.tournaments.register', $tournament) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-[10px] font-black rounded-lg hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-500/20 uppercase tracking-widest">
                                                    {{ __('Sign Up') }}
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('poker.tournaments.show', $tournament) }}" class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    </div>
                                </div>
                            @empty
                                <div class="px-8 py-12 text-center text-gray-400 italic">
                                    {{ __('No upcoming tournaments scheduled.') }}
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Recent Career Results -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Recent Results') }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50/50 dark:bg-gray-900/50 text-gray-400 uppercase text-[10px] font-black tracking-widest border-b border-gray-100 dark:border-gray-700">
                                    <tr>
                                        <th class="px-8 py-4">{{ __('Tournament') }}</th>
                                        <th class="px-4 py-4 text-center">{{ __('Place') }}</th>
                                        <th class="px-8 py-4 text-right">{{ __('Points') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                    @forelse($userResults->take(5) as $result)
                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/40 transition-colors">
                                            <td class="px-8 py-5">
                                                <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $result->tournament->name }}</div>
                                                <div class="text-[10px] text-gray-400 font-bold uppercase">{{ \Illuminate\Support\Carbon::parse($result->tournament->start_time)->format('M d, Y') }}</div>
                                            </td>
                                            <td class="px-4 py-5 text-center">
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg {{ $result->place <= 3 ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 font-black shadow-sm' : 'text-gray-500 font-bold' }} text-xs">
                                                    {{ $result->place }}
                                                </span>
                                            </td>
                                            <td class="px-8 py-5 text-right">
                                                <div class="text-lg font-black text-gray-900 dark:text-white">{{ number_format($result->points) }}</div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-8 py-12 text-center text-gray-400 italic">
                                                {{ __('No result data recorded yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($tournamentsPlayed > 5)
                            <div class="px-8 py-4 bg-gray-50/30 dark:bg-gray-900/30 border-t border-gray-100 dark:border-gray-700 text-center">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ __('Showing latest 5 career results') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
