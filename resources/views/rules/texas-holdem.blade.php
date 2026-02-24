<x-public-layout>
    <div class="bg-white dark:bg-gray-900 py-16 transition-colors duration-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Hero Header -->
            <div class="text-center mb-16">
                <h2 class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-[0.3em] mb-3">{{ __('Official Gameplay Guide') }}</h2>
                <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 dark:text-white mb-6 tracking-tight">
                    Texas <span class="text-indigo-600 dark:text-indigo-400">Hold'em</span> Rules
                </h1>
                <p class="text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
                    {{ __('The definitive guide to the world\'s most popular poker variant. From the initial shuffle to the final showdown, these regulations govern every hand played in First to Act tournaments.') }}
                </p>
            </div>

            @php
                $sections = [
                    [
                        'title' => 'Structural Standards',
                        'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                        'rules' => [
                            ['num' => '01', 'title' => 'The Deck', 'text' => 'Played with a standard 52-card deck and a single "Dealer" button moving clockwise.'],
                            ['num' => '02', 'title' => 'The Blinds', 'text' => 'Forced bets by the Small Blind (left of button) and Big Blind (left of Small Blind).'],
                        ]
                    ],
                    [
                        'title' => 'The Dealing Phase',
                        'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
                        'rules' => [
                            ['num' => '03', 'title' => 'The Shuffle', 'text' => 'Automatic shufflers used when available; otherwise, a standard riffle-riffle-box-riffle.'],
                            ['num' => '04', 'title' => 'Hole Cards', 'text' => 'Two cards dealt face-down recursively to each player, starting from the Small Blind.'],
                            ['num' => '05', 'title' => 'Misdeals', 'text' => 'If the first or second card is exposed, a misdeal is declared. Later exposures are burnt.'],
                            ['num' => '06', 'title' => 'Dead Hands', 'text' => 'Hands are ruled dead if a player acts out of turn or fails to protect their cards.'],
                        ]
                    ],
                    [
                        'title' => 'Wagering Intervals',
                        'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                        'rules' => [
                            ['num' => '07', 'title' => 'Pre-Flop Betting', 'text' => 'Action begins with the player to the left of the Big Blind ("Under the Gun").'],
                            ['num' => '08', 'title' => 'The Flop', 'text' => 'Three community cards dealt face-up. Betting begins with the Small Blind.'],
                            ['num' => '09', 'title' => 'The Turn', 'text' => 'A fourth community card. Same betting order as the flop.'],
                            ['num' => '10', 'title' => 'The River', 'text' => 'The fifth and final community card. Final round of wagering.'],
                            ['num' => '11', 'title' => 'Burn Cards', 'text' => 'The top card of the deck is burnt before dealing the Flop, Turn, and River.'],
                        ]
                    ],
                    [
                        'title' => 'The Showdown',
                        'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
                        'rules' => [
                            ['num' => '12', 'title' => 'Card Value', 'text' => 'Standard rankings apply (Ace high or low). No suit rankings exist.'],
                            ['num' => '13', 'title' => 'Order of Show', 'text' => 'The last aggressor must show first. In no-bet rounds, action starts from SB.'],
                            ['num' => '14', 'title' => 'Cards Speak', 'text' => 'The cards determine the winner. Verbal claims are non-binding at showdown.'],
                            ['num' => '15', 'title' => 'Splitting Pots', 'text' => 'Identical hands split the pot. Odd chips go to the first player left of the button.'],
                            ['num' => '16', 'title' => 'Mucking', 'text' => 'A player may muck their hand if they realize it is beaten at showdown.'],
                        ]
                    ],
                    [
                        'title' => 'Technical Provisions',
                        'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
                        'rules' => [
                            ['num' => '17', 'title' => 'Side Pots', 'text' => 'Created when one or more players are all-in while others continue betting.'],
                            ['num' => '18', 'title' => 'Rabbitting', 'text' => 'Prohibited. No secondary cards may be exposed after a hand ends.'],
                            ['num' => '19', 'title' => 'Playing the Board', 'text' => 'A player may use all five community cards as their best hand.'],
                            ['num' => '20', 'title' => 'Chip Positioning', 'text' => 'Higher value chips must be clearly visible and at the front of stacks.'],
                            ['num' => '21', 'title' => 'Director Finality', 'text' => 'Any situation not explicitly covered is resolved by the Tournament Director.'],
                        ]
                    ]
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                @foreach($sections as $section)
                    <div class="space-y-6">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-700/30">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{!! $section['icon'] !!}"/></svg>
                            </div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ $section['title'] }}</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-4">
                            @foreach($section['rules'] as $rule)
                                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-1 border border-gray-100 dark:border-gray-700 group hover:scale-[1.01] transition-transform">
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-100 dark:border-gray-700 flex gap-4">
                                        <span class="text-indigo-600 dark:text-indigo-400 font-black text-sm pt-1">{{ $rule['num'] }}</span>
                                        <div>
                                            <h4 class="font-bold text-gray-900 dark:text-white mb-1 group-hover:text-indigo-600 transition-colors">{{ $rule['title'] }}</h4>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">{{ $rule['text'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <!-- Hand Rankings (Featured Sidebar-style Card) -->
                <div class="md:col-span-2 mt-8">
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-3xl p-1.5 border border-gray-100 dark:border-gray-700 shadow-xl">
                        <div class="bg-indigo-600 dark:bg-indigo-700 rounded-[1.4rem] p-8 sm:p-12 text-white relative overflow-hidden group">
                            <div class="absolute -right-8 -top-8 w-64 h-64 bg-white/10 rounded-full blur-3xl group-hover:scale-125 transition-transform duration-700"></div>
                            
                            <div class="relative z-10 flex flex-col lg:flex-row gap-12 items-center">
                                <div class="lg:w-1/3">
                                    <h3 class="text-3xl font-extrabold mb-4 tracking-tighter">Hand <span class="text-indigo-200">Hierarchy</span></h3>
                                    <p class="text-indigo-100 text-sm mb-8 leading-relaxed">
                                        Understanding the value of your hand is crucial. We follow standard high-poker rankings from the Royal Flush down to the High Card.
                                    </p>
                                    <div class="inline-flex items-center gap-3 px-4 py-2 bg-white/10 border border-white/20 rounded-full text-[10px] font-bold uppercase tracking-widest">
                                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                                        Official League Rank
                                    </div>
                                </div>
                                <div class="lg:w-2/3 w-full grid grid-cols-2 sm:grid-cols-5 gap-3">
                                    @php
                                        $rankings = ['Royal Flush', 'Straight Flush', 'Four of a Kind', 'Full House', 'Flush', 'Straight', 'Three of a Kind', 'Two Pair', 'One Pair', 'High Card'];
                                    @endphp
                                    @foreach($rankings as $i => $rank)
                                        <div class="px-4 py-4 bg-white/10 dark:bg-black/20 border border-white/10 rounded-xl flex flex-col items-center justify-center text-center hover:bg-white/20 transition-colors">
                                            <span class="text-indigo-300 font-bold text-[9px] mb-1 uppercase tracking-tighter">{{ $i + 1 }}</span>
                                            <span class="text-white font-bold text-[11px] leading-tight tracking-tight">{{ $rank }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Royale -->
            <div class="mt-20 text-center">
                <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-[.3em] mb-4">First to Act league Standard • Established {{ date('Y') }}</p>
                <div class="w-16 h-1 bg-gradient-to-r from-transparent via-indigo-600 to-transparent mx-auto opacity-20"></div>
            </div>
        </div>
    </div>
</x-public-layout>
