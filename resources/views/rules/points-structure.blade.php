<x-public-layout>
    <div class="bg-white dark:bg-gray-900 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-[0.3em] mb-3">{{ __('League Rules & Logic') }}</h2>
                <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 dark:text-white mb-6 tracking-tight">
                    {{ __('Points Structure') }}
                </h1>
                <p class="text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
                    {{ __('Our proprietary scoring algorithms reward consistency, deep runs, and tournament dominance. Points form the backbone of our seasonal rankings.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-1.5 border border-gray-100 dark:border-gray-700 shadow-lg shadow-gray-200/40 dark:shadow-none">
                        <div class="overflow-hidden rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50/50 dark:bg-gray-900/50 text-gray-400 uppercase text-[9px] font-bold tracking-[0.2em] border-b border-gray-100 dark:border-gray-700">
                                    <tr>
                                        <th class="px-8 py-5 text-center w-24">{{ __('Rank') }}</th>
                                        <th class="px-6 py-5">{{ __('Placement Tier') }}</th>
                                        <th class="px-8 py-5 text-right">{{ __('Awarded Points') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                    @forelse($pointsStructure as $structure)
                                        <tr class="group hover:bg-indigo-50/30 dark:hover:bg-indigo-900/10 transition-colors">
                                            <td class="px-8 py-8 text-center">
                                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl {{ $structure->place <= 3 ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-500' }} font-bold text-sm">
                                                    {{ $structure->place }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-8">
                                                <div class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 transition-colors mb-0.5">
                                                    {{ $structure->place }}{{ match($structure->place) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }} {{ __('Place') }}
                                                </div>
                                                @if($structure->place == 1)
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                        <span class="text-[9px] font-bold text-amber-500 uppercase tracking-widest">{{ __('Tournament Champion') }}</span>
                                                    </div>
                                                @elseif($structure->place <= 3)
                                                    <span class="text-[9px] font-bold text-indigo-400 uppercase tracking-widest">{{ __('Podium Level') }}</span>
                                                @endif
                                            </td>
                                            <td class="px-8 py-8 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <span class="text-3xl font-black text-gray-900 dark:text-white tracking-tighter">{{ number_format($structure->points) }}</span>
                                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest pt-2">{{ __('Pts') }}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-8 py-20 text-center">
                                                <div class="w-16 h-16 bg-gray-50 dark:bg-gray-900 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-gray-100 dark:border-gray-700">
                                                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </div>
                                                <p class="text-gray-400 italic text-sm font-medium">{{ __('Standard league structure is being finalized.') }}</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Content -->
                <div class="space-y-8">
                    <!-- Season Preview -->
                    @if($topPerformers->isNotEmpty())
                        <div class="bg-indigo-600 dark:bg-indigo-500 rounded-2xl p-6 text-white shadow-xl shadow-indigo-500/20 relative overflow-hidden group border border-white/10">
                            <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                            
                            <h3 class="text-[9px] font-bold uppercase tracking-[0.2em] mb-6 opacity-80">{{ __('Season Leaders Peak') }}</h3>
                            <div class="space-y-5 relative z-10">
                                @foreach($topPerformers as $performer)
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center font-bold text-xs">
                                                {{ $loop->iteration }}
                                            </div>
                                            <div>
                                                <div class="text-xs font-bold">{{ $performer->first_name }} {{ $performer->last_name }}</div>
                                                <div class="text-[9px] opacity-70 italic font-medium">{{ $performer->nickname }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-md font-black tracking-tighter">{{ number_format($performer->tournament_results_sum_points ?? 0) }}</div>
                                            <div class="text-[8px] uppercase font-bold opacity-60">{{ __('Pts') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            
                            <div class="mt-8 pt-6 border-t border-white/10 text-center">
                                <a href="{{ route('poker.seasons.show', $currentSeason) }}" class="inline-flex items-center text-[10px] font-bold uppercase tracking-widest hover:opacity-80 transition-opacity">
                                    {{ __('Full Season Standings') }}
                                    <svg class="w-3 h-3 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- Logic Summary -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h3 class="text-[10px] font-bold text-gray-900 dark:text-white uppercase tracking-widest mb-6">{{ __('Scoring Logic') }}</h3>
                        <div class="space-y-6">
                            <div class="flex gap-4">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-teal-50 dark:bg-teal-900/30 flex items-center justify-center text-teal-600 dark:text-teal-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <div class="text-[11px] font-bold text-gray-900 dark:text-white mb-1">{{ __('Base Points') }}</div>
                                    <p class="text-[10px] text-gray-500 leading-relaxed">{{ __('Awarded strictly by finishing position as shown in the primary table.') }}</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <div>
                                    <div class="text-[11px] font-bold text-gray-900 dark:text-white mb-1">{{ __('Multipliers') }}</div>
                                    <p class="text-[10px] text-gray-500 leading-relaxed">{{ __('High-stakes tournaments and finals may feature significant point multipliers.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-16 text-center">
                <p class="text-xs text-gray-400 font-medium italic max-w-2xl mx-auto">
                    {{ __('Points are verified by the league steward. In the event of ties, prize pools are split but points are awarded to the higher finishing position in the official bracket.') }}
                </p>
            </div>
        </div>
    </div>
</x-public-layout>
