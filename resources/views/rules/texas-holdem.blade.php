<x-public-layout>
    <x-p-hero suit="heart" :eyebrow="__('Official Gameplay Guide')"
              :title="__('Texas Hold\'em Rules')"
              :highlight="__('Hold\'em')">
        {{ __('The definitive guide to the world\'s most popular poker variant. From the initial shuffle to the final showdown, these regulations govern every hand played in First to Act tournaments.') }}
    </x-p-hero>

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

    <div class="p-sections">
        @foreach ($sections as $section)
            <section class="p-sections__group">
                <x-p-section-head :title="$section['title']" :icon="$section['icon']" />

                @foreach ($section['rules'] as $rule)
                    <x-p-item :number="$rule['num']" :title="$rule['title']">{{ $rule['text'] }}</x-p-item>
                @endforeach
            </section>
        @endforeach
    </div>

    <section class="p-panel">
        <div class="p-panel__glow" aria-hidden="true"></div>

        {{-- Not the two-column split this used to be: a hand is five cards
             wide, and half a panel is not enough to show one at a size anybody
             can read. The heading takes its own line and the hands take the
             width. --}}
        <div class="p-panel__lead">
            <h2 class="p-panel__title">{{ __('Hand Hierarchy') }}</h2>

            <p class="p-panel__text">
                {{ __('Understanding the value of your hand is crucial. We follow standard high-poker rankings from the Royal Flush down to the High Card.') }}
            </p>

            <p class="p-pill">
                <span class="p-pill__dot" aria-hidden="true"></span>
                {{ __('Official League Rank') }}
            </p>
        </div>

        <div class="p-hand-grid">
            @foreach ($hands as $i => $hand)
                <x-p-hand :name="__($hand['name'])" :cards="$hand['cards']" :index="$i + 1" />
            @endforeach
        </div>
    </section>

    <footer class="p-page-foot">
        <p class="u-eyebrow p-page-foot__caption">
            {{ __('First to Act league Standard') }} &bull; {{ __('Established') }} {{ date('Y') }}
        </p>
        <hr class="p-rule">
    </footer>
</x-public-layout>
