<x-public-layout>
    <div class="bg-white dark:bg-gray-900 transition-colors duration-500">
        <!-- Hero Header -->
        <div class="pt-20 pb-12 border-b border-gray-100 dark:border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-[0.3em] mb-3">{{ __('Financial & Player Governance') }}</h2>
                <h1 class="text-4xl sm:text-6xl font-extrabold text-gray-900 dark:text-white mb-6 tracking-tight">
                    {{ __('Betting & Conduct') }}
                </h1>
                <p class="text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
                    {{ __('Fairness at the table is maintained through strict financial integrity and mutual respect. These regulations define our mechanical and behavioral standards.') }}
                </p>
            </div>
        </div>

        <!-- Sticky Sub-Navigation -->
        <div class="sticky top-0 z-30 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md border-b border-gray-100 dark:border-gray-800 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-center gap-8 h-14">
                    <a href="#betting-rules" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 uppercase tracking-widest transition-colors">Betting Standards</a>
                    <div class="w-[1px] h-4 bg-gray-200 dark:bg-gray-700"></div>
                    <a href="#conduct-rules" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 uppercase tracking-widest transition-colors">Player Conduct</a>
                    <div class="w-[1px] h-4 bg-gray-200 dark:bg-gray-700"></div>
                    <a href="#penalties" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 uppercase tracking-widest transition-colors">Enforcement</a>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <!-- Section 1: Betting Standards -->
            <div id="betting-rules" class="scroll-mt-32 mb-32">
                <div class="flex items-center gap-4 mb-12">
                    <h3 class="text-sm font-black text-gray-400 uppercase tracking-widest">Part I: Wagering Regulations</h3>
                    <div class="h-[1px] flex-grow bg-gray-100 dark:bg-gray-800"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    @php
                        $bettingRules = [
                            ['title' => 'Verbal Declarations', 'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z', 'text' => 'Verbal statements are binding. Declaring "All-In" or "Call" commits the player to that action immediately.'],
                            ['title' => 'String Betting', 'icon' => 'M7 11l5 5m0 0l5-5m-5 5V3', 'text' => 'Chips must be placed in a single motion. Returning to the stack for more chips is a string bet and will be ruled as a call.'],
                            ['title' => 'Oversized Chips', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Placing a single oversized chip into the pot is ruled a call unless action is verbally declared beforehand.'],
                            ['title' => 'Raise Limits', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', 'text' => 'A raise must be at least equal to the previous bet or raise in the current round of wagering.'],
                        ];
                    @endphp

                    @foreach($bettingRules as $rule)
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-1.5 border border-gray-100 dark:border-gray-700 shadow-lg shadow-gray-200/40 dark:shadow-none group hover:scale-[1.01] transition-transform duration-300">
                            <div class="h-full bg-white dark:bg-gray-800 rounded-xl p-8 border border-gray-100 dark:border-gray-700 relative overflow-hidden">
                                <div class="flex items-center gap-4 mb-6">
                                    <div class="w-12 h-12 rounded-2xl bg-teal-50 dark:bg-teal-900/30 flex items-center justify-center text-teal-600 dark:text-teal-400 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $rule['icon'] }}"/></svg>
                                    </div>
                                    <h3 class="text-xl font-extrabold text-gray-900 dark:text-white group-hover:text-indigo-600 transition-colors">
                                        {{ $rule['title'] }}
                                    </h3>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed font-normal">
                                    {{ $rule['text'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Section 2: Player Conduct -->
            <div id="conduct-rules" class="scroll-mt-32 mb-32">
                <div class="flex items-center gap-4 mb-12">
                    <h3 class="text-sm font-black text-gray-400 uppercase tracking-widest">Part II: Behaviour Conduct</h3>
                    <div class="h-[1px] flex-grow bg-gray-100 dark:bg-gray-800"></div>
                </div>

                <div class="max-w-4xl mx-auto space-y-6">
                    @php
                        $conductRules = [
                            ['id' => '01', 'title' => 'Ethical Play', 'text' => 'Collusion, chip dumping, or any form of cooperative play is strictly prohibited and leads to immediate expulsion.'],
                            ['id' => '02', 'title' => 'Professional Courtesy', 'text' => 'Players must maintain a respectful attitude towards opponents and staff. Excessive celebration or berating is not tolerated.'],
                            ['id' => '03', 'title' => 'Table Communication', 'text' => 'English is the only language permitted while a hand is in progress. Discussing active hands is prohibited.'],
                            ['id' => '04', 'title' => 'Electronic Devices', 'text' => 'Phone use is prohibited while in a hand. Continuous use that stalls play will result in a penalty.'],
                        ];
                    @endphp

                    @foreach($conductRules as $rule)
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-1.5 border border-gray-100 dark:border-gray-700 shadow-lg shadow-gray-200/40 dark:shadow-none group">
                            <div class="h-full bg-white dark:bg-gray-800 rounded-xl p-8 border border-gray-100 dark:border-gray-700 flex gap-6 items-center">
                                <span class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest shrink-0 w-12">{{ $rule['id'] }}</span>
                                <div class="flex-grow">
                                    <h3 class="text-lg font-extrabold text-gray-900 dark:text-white mb-1 group-hover:text-indigo-600 transition-colors">{{ $rule['title'] }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-normal leading-relaxed">{{ $rule['text'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Section 3: Penalty Escalation -->
            <div id="penalties" class="scroll-mt-32">
                <div class="flex items-center gap-4 mb-12">
                    <h3 class="text-sm font-black text-red-600 dark:text-red-400 uppercase tracking-widest">Part III: Enforcement Protocol</h3>
                    <div class="h-[1px] flex-grow bg-red-100 dark:bg-red-900/30"></div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-[2.5rem] p-1.5 border border-gray-100 dark:border-gray-700 shadow-xl">
                    <div class="bg-white dark:bg-gray-800 rounded-[2.4rem] p-10 border border-gray-100 dark:border-gray-700 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-red-500/5 blur-[100px] pointer-events-none"></div>
                        
                        <div class="flex flex-col lg:flex-row items-center justify-between gap-12">
                            <div class="lg:w-1/2">
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white mb-4 tracking-tighter uppercase">Penalty <span class="text-red-600">Escalation</span></h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed mb-8">
                                    Violations follow a tiered disciplinary structure. The Tournament Director reserves the right to skip levels based on severity.
                                </p>
                                <div class="inline-flex items-center gap-3 px-4 py-2 bg-red-50 dark:bg-red-900/20 rounded-full border border-red-100 dark:border-red-900/30">
                                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                                    <span class="text-[10px] font-bold text-red-600 dark:text-red-400 uppercase tracking-widest">{{ __('Enforcement Protocol Active') }}</span>
                                </div>
                            </div>
                            
                            <div class="lg:w-1/2 w-full grid grid-cols-1 gap-3">
                                @php
                                    $penalties = [
                                        ['label' => 'First Offense', 'effect' => 'Formal Warning', 'color' => 'amber'],
                                        ['label' => 'Second Offense', 'effect' => '1-Round Penalty', 'color' => 'orange'],
                                        ['label' => 'Third Offense', 'effect' => 'Disqualification', 'color' => 'red'],
                                    ];
                                @endphp
                                @foreach($penalties as $penalty)
                                    <div class="flex items-center justify-between p-5 rounded-3xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700 hover:border-{{ $penalty['color'] }}-500/50 transition-colors group/item">
                                        <div>
                                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter mb-0.5">{{ $penalty['label'] }}</div>
                                            <div class="text-sm font-black text-gray-900 dark:text-white group-hover/item:text-{{ $penalty['color'] }}-600 transition-colors uppercase">{{ $penalty['effect'] }}</div>
                                        </div>
                                        <div class="w-2 h-10 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                            <div class="h-full bg-{{ $penalty['color'] }}-500 w-full"></div>
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
                <p class="text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-[0.5em] mb-4">First to Act league Standard • Season {{ date('Y') }}</p>
                <div class="w-16 h-1 bg-gradient-to-r from-transparent via-indigo-600 to-transparent mx-auto opacity-20"></div>
            </div>
        </div>
    </div>
</x-public-layout>
