<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $tournament->name }}
                <span class="ml-2 px-2.5 py-0.5 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-400 text-[10px] font-bold rounded-full uppercase tracking-widest border border-indigo-200 dark:border-indigo-800">
                    {{ __('Tournament Details') }}
                </span>
            </h2>
            <div class="flex gap-3">
                <a href="{{ route('poker.tournaments.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    {{ __('Back') }}
                </a>
                <a href="{{ route('poker.tournaments.edit', $tournament) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 transition ease-in-out duration-150 shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-5M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    {{ __('Edit') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            @if (session('status'))
                <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 p-4 rounded-xl text-green-700 dark:text-green-400 text-sm font-bold flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 p-4 rounded-xl text-red-700 dark:text-red-400 text-sm font-bold flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('error') }}
                </div>
            @endif

            <!-- Tournament Identity Header -->
            <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 overflow-hidden shadow-sm rounded-2xl p-8">
                <div class="grid grid-cols-1 {{ $tournament->venue->address ? 'lg:grid-cols-2' : '' }} gap-12 items-start">
                    
                    @if($tournament->venue->address)
                        <!-- Left: Mapping & Location -->
                        <div class="space-y-4">
                            <div class="w-full h-80 rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-inner group relative">
                                <div class="absolute inset-0 bg-indigo-500/5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10"></div>
                                <iframe 
                                    class="w-full h-full grayscale opacity-80 invert dark:invert-0 transition-all group-hover:grayscale-0 group-hover:opacity-100" 
                                    frameborder="0" 
                                    scrolling="no" 
                                    marginheight="0" 
                                    marginwidth="0" 
                                    src="https://maps.google.com/maps?q={{ urlencode($tournament->venue->address) }}&t=&z=13&ie=UTF8&iwloc=&output=embed">
                                </iframe>
                            </div>
                            <div class="flex items-center text-xs font-bold text-gray-500 dark:text-gray-400 px-2 uppercase tracking-widest">
                                <svg class="w-3.5 h-3.5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $tournament->venue->address }}
                            </div>
                        </div>
                    @endif

                    <!-- Right: Identity & Actions -->
                    <div class="space-y-6">
                        <div class="space-y-4">
                            <div class="flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-widest">
                                <span class="px-2 py-1 bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400 rounded-lg border border-gray-200 dark:border-gray-700 flex items-center">
                                    <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    {{ \Illuminate\Support\Carbon::parse($tournament->start_time)->format('M d, Y @ h:i A') }}
                                </span>
                                <a href="{{ route('poker.venues.show', $tournament->venue) }}" class="px-2 py-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg border border-indigo-100 dark:border-indigo-800/50 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors flex items-center">
                                    <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $tournament->venue->name ?? 'TBD' }}
                                </a>
                                <a href="{{ route('poker.seasons.show', $tournament->season) }}" class="px-2 py-1 bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 rounded-lg border border-teal-100 dark:border-teal-800/50 hover:bg-teal-100 dark:hover:bg-teal-900/50 transition-colors flex items-center">
                                    <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    {{ $tournament->season->name }}
                                </a>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white leading-tight">
                                    {{ $tournament->name }}
                                </h1>
                                
                                @if($isUserRegistered)
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center px-3 py-1 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 text-xs font-bold rounded-lg border border-green-200 dark:border-green-800">
                                            <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            {{ __('Registered') }}
                                        </span>
                                        
                                        @if(!$isPast)
                                            <form action="{{ route('poker.tournaments.unregister', $tournament) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to unregister from this tournament?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[10px] font-bold text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 uppercase tracking-widest transition-colors">
                                                    {{ __('Unregister') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @elseif(!$isPast)
                                    <form action="{{ route('poker.tournaments.register', $tournament) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-400 transition-colors shadow-lg shadow-indigo-500/20 uppercase tracking-widest">
                                            {{ __('Register Now') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                            @if($tournament->description)
                                <p class="text-gray-500 dark:text-gray-400 italic">
                                    "{{ $tournament->description }}"
                                </p>
                            @endif
                        </div>

                        <!-- Top 3 Podium (Sub-Display) -->
                        @if($isPast && $podium->isNotEmpty())
                            <div class="flex items-end gap-3 pt-4">
                                <!-- 2nd Place -->
                                @if(isset($podium[1]))
                                    <div class="flex flex-col items-center group">
                                        <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-500 dark:text-slate-400 font-bold text-sm border-2 border-slate-200 dark:border-slate-600 group-hover:scale-110 transition-transform shadow-sm" title="{{ $podium[1]->player_name }}">
                                            {{ strtoupper(substr($podium[1]->player_name, 0, 1)) }}
                                        </div>
                                        <div class="h-8 w-8 bg-slate-100 dark:bg-slate-800/80 rounded-t-lg mt-1 flex items-center justify-center text-slate-400 font-black text-[10px]">2</div>
                                    </div>
                                @endif
                                <!-- 1st Place -->
                                <div class="flex flex-col items-center group -mt-2">
                                    <div class="w-14 h-14 rounded-full bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-amber-600 dark:text-amber-400 font-bold text-lg border-4 border-amber-200 dark:border-amber-700 group-hover:scale-110 transition-transform shadow-md" title="{{ $podium[0]->player_name }}">
                                        {{ strtoupper(substr($podium[0]->player_name, 0, 1)) }}
                                    </div>
                                    <div class="h-12 w-10 bg-amber-100 dark:bg-amber-900/60 rounded-t-lg mt-1 flex items-center justify-center text-amber-600 dark:text-amber-300 font-black text-sm">1</div>
                                </div>
                                <!-- 3rd Place -->
                                @if(isset($podium[2]))
                                    <div class="flex flex-col items-center group">
                                        <div class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-orange-600 dark:text-orange-400 font-bold text-sm border-2 border-orange-200 dark:border-orange-800/50 group-hover:scale-110 transition-transform shadow-sm" title="{{ $podium[2]->player_name }}">
                                            {{ strtoupper(substr($podium[2]->player_name, 0, 1)) }}
                                        </div>
                                        <div class="h-6 w-8 bg-orange-100 dark:bg-orange-900/40 rounded-t-lg mt-1 flex items-center justify-center text-orange-600/60 font-black text-[10px]">3</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Stats Ribbon -->
            <div class="grid grid-cols-2 {{ $isPast ? 'md:grid-cols-4' : 'md:grid-cols-1' }} gap-6">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">{{ __('Registrants') }}</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $registrantsCount }}</div>
                </div>
                
                @if($isPast)
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">{{ __('Final Results') }}</div>
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $resultsCount }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">{{ __('Avg Points') }}</div>
                        <div class="text-2xl font-bold text-teal-600 dark:text-teal-400">
                            {{ $resultsCount > 0 ? round($totalPoints / $resultsCount, 1) : 0 }}
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">{{ __('Points Pot') }}</div>
                        <div class="text-2xl font-bold text-violet-600 dark:text-violet-400">{{ number_format($totalPoints) }}</div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content (Standings & Registrants) -->
                <div class="lg:col-span-2 space-y-8">
                    @if($isPast)
                        <!-- Final Standings -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-2xl overflow-hidden">
                            <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Final Standings') }}</h3>
                                <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl">
                                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead class="bg-gray-50/30 dark:bg-gray-900/30 text-gray-500 uppercase text-[10px] font-bold tracking-widest">
                                        <tr>
                                            <th class="px-8 py-4 text-center w-20">{{ __('Pos') }}</th>
                                            <th class="px-4 py-4">{{ __('Player') }}</th>
                                            <th class="px-8 py-4 text-right">{{ __('Points') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @forelse($orderedResults as $result)
                                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/40 transition-colors">
                                                <td class="px-8 py-4 text-center">
                                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg {{ $result->place <= 3 ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400 font-bold' : 'text-gray-500' }}">
                                                        {{ $result->place }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $result->player_name }}</div>
                                                    <div class="text-[10px] text-gray-500">{{ $result->player_nickname ?? __('No nickname') }}</div>
                                                </td>
                                                <td class="px-8 py-4 text-right">
                                                    <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400 tracking-tight">{{ number_format($result->points) }}</div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="px-8 py-12 text-center text-gray-400 italic">
                                                    {{ __('No results recorded yet.') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Registered Players -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-2xl overflow-hidden">
                        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Registered Players') }}</h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                                {{ $registrantsCount }}
                            </span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-50 dark:divide-gray-700 max-h-[600px] overflow-y-auto custom-scrollbar">
                            @forelse($tournament->registrants->sortBy('player_name') as $registrant)
                                <div class="px-8 py-4 hover:bg-gray-50/50 dark:hover:bg-gray-900/40 transition-colors border-b border-gray-50 dark:border-gray-700">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-gray-50 dark:bg-gray-900 flex items-center justify-center text-sm font-bold text-gray-400 dark:text-gray-500 border border-gray-100 dark:border-gray-800">
                                            {{ strtoupper(substr($registrant->player_name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $registrant->player_name }}</div>
                                            <div class="text-[10px] text-gray-500 font-medium truncate italic">{{ $registrant->player_nickname }}</div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-span-2 px-8 py-12 text-center text-gray-400 italic">
                                    {{ __('No players registered yet.') }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Sidebar (Admin Tools & Points Structure) -->
                <div class="space-y-8">
                    @if(auth()->user()->is_admin && $availableUsers->isNotEmpty())
                        <!-- Admin Registration Panel -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-2xl overflow-hidden p-6">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-6 flex items-center justify-between">
                                {{ __('Admin: Register') }}
                                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            </h3>
                            <form action="{{ route('poker.tournaments.register', $tournament) }}" method="POST" class="space-y-4">
                                @csrf
                                <div>
                                    <label for="user_id" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">{{ __('Select Player') }}</label>
                                    <select name="user_id" id="user_id" required class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-700 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 transition-all dark:text-white">
                                        <option value="">{{ __('-- Choose User --') }}</option>
                                        @foreach($availableUsers as $user)
                                            <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }} ({{ $user->nickname ?? 'No Nick' }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-indigo-600 dark:bg-indigo-500 text-white text-xs font-bold rounded-xl hover:bg-indigo-700 dark:hover:bg-indigo-400 transition-colors shadow-lg shadow-indigo-500/20 uppercase tracking-widest">
                                    {{ __('Register Player') }}
                                </button>
                            </form>
                        </div>
                    @endif

                    @if(!$isPast && $pointsStructure->isNotEmpty())
                        <!-- Points Structure -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-2xl overflow-hidden">
                            <div class="px-6 py-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __('Points at Stake') }}</h3>
                                <div class="p-1.5 bg-amber-50 dark:bg-amber-900/30 rounded-lg">
                                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-50 dark:divide-gray-700">
                                @foreach($pointsStructure as $structure)
                                    <div class="px-6 py-4 flex justify-between items-center">
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs font-bold {{ $structure->place <= 3 ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500' }}">
                                                {{ $structure->place }}{{ match($structure->place) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }}
                                            </span>
                                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-tighter">{{ __('Place') }}</div>
                                        </div>
                                        <div class="text-sm font-black text-gray-900 dark:text-white tracking-tight">
                                            {{ number_format($structure->points) }} <span class="text-[10px] text-gray-400 font-bold uppercase ml-1">{{ __('Pts') }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="px-6 py-4 bg-gray-50/30 dark:bg-gray-900/30 border-t border-gray-50 dark:border-gray-700">
                                <p class="text-[9px] text-gray-400 font-medium italic text-center">
                                    {{ __('Points are based on league rules.') }}
                                </p>
                            </div>
                        </div>
                    @endif
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
