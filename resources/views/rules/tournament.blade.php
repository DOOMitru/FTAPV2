<x-public-layout>
    <div class="bg-white dark:bg-gray-900 transition-colors duration-500">
        <!-- Hero Header -->
        <div class="pt-20 pb-12 border-b border-gray-100 dark:border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-[0.3em] mb-3">{{ __('League Standards & Governance') }}</h2>
                <h1 class="text-4xl sm:text-6xl font-extrabold text-gray-900 dark:text-white mb-6 tracking-tight">
                    {{ __('Rules & Regulations') }}
                </h1>
                <p class="text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
                    {{ __('The First to Act league operates under a strict set of competitive standards. These rules ensure a consistent, fair, and professional environment for all participants.') }}
                </p>
            </div>
        </div>

        <!-- Sticky Sub-Navigation -->
        <div class="sticky top-0 z-30 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md border-b border-gray-100 dark:border-gray-800 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-center gap-8 h-14">
                    <a href="#general-rules" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 uppercase tracking-widest transition-colors">General Rules</a>
                    <div class="w-[1px] h-4 bg-gray-200 dark:bg-gray-700"></div>
                    <a href="#season-structure" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 uppercase tracking-widest transition-colors">Season Structure</a>
                    <div class="w-[1px] h-4 bg-gray-200 dark:bg-gray-700"></div>
                    <a href="#final-stakes" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 uppercase tracking-widest transition-colors">Final Stakes</a>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <!-- Section 1: General Rules -->
            <div id="general-rules" class="scroll-mt-32 mb-32">
                <div class="flex items-center gap-4 mb-12">
                    <h3 class="text-sm font-black text-gray-400 uppercase tracking-widest">Part I: Tournament Fundamentals</h3>
                    <div class="h-[1px] flex-grow bg-gray-100 dark:bg-gray-800"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    @php
                        $generalRules = [
                            ['title' => 'Standard Play', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'text' => 'All tournaments follow standard TDA (Tournament Directors Association) rules unless otherwise specified.'],
                            ['title' => 'Blind Intervals', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Blinds increase every 20 minutes. A signal will sound at the end of each level.'],
                            ['title' => 'Re-Entry Policy', 'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'text' => 'Players may re-enter until the end of the first break. Only one re-entry is permitted per player.'],
                            ['title' => 'The Clock', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'If a player takes an excessive amount of time, any active player may call "Clock". The player then has 60 seconds to act.'],
                        ];
                    @endphp

                    @foreach($generalRules as $rule)
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-1.5 border border-gray-100 dark:border-gray-700 shadow-lg group hover:scale-[1.01] transition-transform duration-300">
                            <div class="h-full bg-white dark:bg-gray-800 rounded-xl p-8 border border-gray-100 dark:border-gray-700 relative overflow-hidden">
                                <div class="flex items-center gap-4 mb-6">
                                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $rule['icon'] }}"/></svg>
                                    </div>
                                    <h3 class="text-xl font-extrabold text-gray-900 dark:text-white group-hover:text-indigo-600 transition-colors">
                                        {{ $rule['title'] }}
                                    </h3>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                    {{ $rule['text'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Section 2: Season Structure -->
            <div id="season-structure" class="scroll-mt-32 mb-32">
                <div class="flex items-center gap-4 mb-12">
                    <h3 class="text-sm font-black text-gray-400 uppercase tracking-widest">Part II: Seasonal Progression</h3>
                    <div class="h-[1px] flex-grow bg-gray-100 dark:bg-gray-800"></div>
                </div>

                <div class="max-w-4xl mx-auto space-y-6">
                    @php
                        $seasonRules = [
                            ['id' => '01', 'title' => 'Tournament Schedule', 'text' => 'The season consists of 12 regular tournaments held over the course of the year.'],
                            ['id' => '02', 'title' => 'Points Accumulation', 'text' => 'Players earn points based on their finishing position in each tournament. See the Points Structure page for details.'],
                            ['id' => '03', 'title' => 'Seasonal Standings', 'text' => 'Standings are updated after every event. The top performers are highlighted in the league dashboard.'],
                            ['id' => '04', 'title' => 'Qualification', 'text' => 'Participation in the Final Stakes is earned through consistent performance throughout the season.'],
                        ];
                    @endphp

                    @foreach($seasonRules as $rule)
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-1.5 border border-gray-100 dark:border-gray-700 shadow-md group">
                            <div class="h-full bg-white dark:bg-gray-800 rounded-xl p-8 border border-gray-100 dark:border-gray-700 flex gap-6 items-center">
                                <span class="text-xs font-black text-teal-600 dark:text-teal-400 uppercase tracking-widest shrink-0 w-12">{{ $rule['id'] }}</span>
                                <div class="flex-grow">
                                    <h3 class="text-lg font-extrabold text-gray-900 dark:text-white mb-1 group-hover:text-teal-600 transition-colors">{{ $rule['id'] }}. {{ $rule['title'] }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-normal leading-relaxed">{{ $rule['text'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Section 3: The Final Stakes -->
            <div id="final-stakes" class="scroll-mt-32">
                <div class="flex items-center gap-4 mb-12">
                    <h3 class="text-sm font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest">Part III: The Seasonal Finale</h3>
                    <div class="h-[1px] flex-grow bg-amber-100 dark:bg-amber-900/30"></div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-[2.5rem] p-1.5 border border-gray-100 dark:border-gray-700 shadow-xl">
                    <div class="bg-white dark:bg-gray-800 rounded-[2.4rem] p-10 border border-gray-100 dark:border-gray-700 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-amber-500/5 blur-[100px] pointer-events-none"></div>
                        
                        <div class="flex flex-col lg:flex-row items-center justify-between gap-12">
                            <div class="lg:w-1/2">
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white mb-4 tracking-tighter uppercase">The <span class="text-amber-600">Final Stakes</span></h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed mb-8">
                                    The ultimate test of skill and endurance. The season culminates in a high-stakes finale where the league champion is crowned.
                                </p>
                                <div class="inline-flex items-center gap-3 px-4 py-2 bg-amber-50 dark:bg-amber-900/20 rounded-full border border-amber-100 dark:border-amber-900/30">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest">{{ __('Championship Protocols Active') }}</span>
                                </div>
                            </div>
                            
                            <div class="lg:w-1/2 w-full grid grid-cols-1 gap-3">
                                @php
                                    $finalRules = [
                                        ['label' => 'Qualification', 'effect' => 'Top 10 Players in Seasonal Standings', 'color' => 'indigo'],
                                        ['label' => 'Point Multiplier', 'effect' => 'Double Weighted Points Awarded', 'color' => 'teal'],
                                        ['label' => 'The Trophy', 'effect' => 'Crown of the First to Act Champion', 'color' => 'amber'],
                                    ];
                                @endphp
                                @foreach($finalRules as $rule)
                                    <div class="flex items-center justify-between p-5 rounded-3xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700 hover:border-{{ $rule['color'] }}-500/50 transition-colors group/item">
                                        <div>
                                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter mb-0.5">{{ $rule['label'] }}</div>
                                            <div class="text-sm font-black text-gray-900 dark:text-white group-hover/item:text-{{ $rule['color'] }}-600 transition-colors uppercase">{{ $rule['effect'] }}</div>
                                        </div>
                                        <div class="w-2 h-10 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden text-transparent">.
                                            <div class="h-full bg-{{ $rule['color'] }}-500 w-full"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Global Footer Note -->
            <div class="mt-32 pt-16 border-t border-gray-100 dark:border-gray-800 text-center">
                <p class="text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-[0.5em] mb-4">First to Act league Standard • Established {{ date('Y') }}</p>
                <div class="w-16 h-1 bg-gradient-to-r from-transparent via-indigo-600 to-transparent mx-auto opacity-20"></div>
            </div>
        </div>
    </div>
</x-public-layout>
