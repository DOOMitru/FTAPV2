<x-public-layout>
    <x-p-hero :eyebrow="__('Financial & Player Governance')" :title="__('Betting and Conduct')" :highlight="__('Conduct')">
        {{ __('Fairness at the table is maintained through strict financial integrity and mutual respect. These regulations define our mechanical and behavioral standards.') }}
    </x-p-hero>

    <nav class="p-subnav" aria-label="{{ __('On this page') }}">
        <a class="p-subnav__link" href="#betting-rules">{{ __('Betting Standards') }}</a>
        <a class="p-subnav__link" href="#conduct-rules">{{ __('Player Conduct') }}</a>
        <a class="p-subnav__link" href="#penalties">{{ __('Enforcement') }}</a>
    </nav>

    <section id="betting-rules" class="p-anchor">
        <div class="p-part">
            <h2 class="p-part__label">{{ __('Part I: Wagering Regulations') }}</h2>
            <span class="p-part__line" aria-hidden="true"></span>
        </div>

    @php
        $bettingRules = [

        ['title' => 'Verbal Declarations', 'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z', 'text' => 'Verbal statements are binding. Declaring "All-In" or "Call" commits the player to that action immediately.'],
        ['title' => 'String Betting', 'icon' => 'M7 11l5 5m0 0l5-5m-5 5V3', 'text' => 'Chips must be placed in a single motion. Returning to the stack for more chips is a string bet and will be ruled as a call.'],
        ['title' => 'Oversized Chips', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Placing a single oversized chip into the pot is ruled a call unless action is verbally declared beforehand.'],
        ['title' => 'Raise Limits', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', 'text' => 'A raise must be at least equal to the previous bet or raise in the current round of wagering.'],
        ];
    @endphp

        <div class="l-stack l-stack--tight">
            @foreach ($bettingRules as $rule)
                <x-p-item :icon="$rule['icon']" :title="$rule['title']">{{ $rule['text'] }}</x-p-item>
            @endforeach
        </div>
    </section>

    <section id="conduct-rules" class="p-anchor">
        <div class="p-part">
            <h2 class="p-part__label">{{ __('Part II: Behavioural Standards') }}</h2>
            <span class="p-part__line" aria-hidden="true"></span>
        </div>

    @php
        $conductRules = [

        ['id' => '01', 'title' => 'Ethical Play', 'text' => 'Collusion, chip dumping, or any form of cooperative play is strictly prohibited and leads to immediate expulsion.'],
        ['id' => '02', 'title' => 'Professional Courtesy', 'text' => 'Players must maintain a respectful attitude towards opponents and staff. Excessive celebration or berating is not tolerated.'],
        ['id' => '03', 'title' => 'Table Communication', 'text' => 'English is the only language permitted while a hand is in progress. Discussing active hands is prohibited.'],
        ['id' => '04', 'title' => 'Electronic Devices', 'text' => 'Phone use is prohibited while in a hand. Continuous use that stalls play will result in a penalty.'],
        ];
    @endphp

        <div class="l-stack l-stack--tight">
            @foreach ($conductRules as $rule)
                <x-p-item :number="$rule['id']" :title="$rule['title']">{{ $rule['text'] }}</x-p-item>
            @endforeach
        </div>
    </section>

    <section id="penalties" class="p-anchor">
        <div class="p-part">
            <h2 class="p-part__label">{{ __('Part III: Enforcement Protocol') }}</h2>
            <span class="p-part__line" aria-hidden="true"></span>
        </div>

    @php
        $penalties = [

        ['label' => 'First Offense', 'effect' => 'Formal Warning'],
        ['label' => 'Second Offense', 'effect' => '1-Round Penalty'],
        ['label' => 'Third Offense', 'effect' => 'Disqualification'],
        ];
    @endphp

        <div class="p-panel p-panel--accent">
            <div class="p-panel__glow" aria-hidden="true"></div>

            <div class="p-panel__split">
                <div>
                    <h3 class="p-panel__title">{{ __('Penalty Escalation') }}</h3>

                    <p class="p-panel__text">{{ __('Violations follow a tiered disciplinary structure. The Tournament Director reserves the right to skip levels based on severity.') }}</p>

                    <p class="p-pill">
                        <span class="p-pill__dot" aria-hidden="true"></span>
                        {{ __('Enforcement Protocol Active') }}
                    </p>
                </div>

                <div class="l-stack l-stack--tight">
                    @foreach ($penalties as $row)
                        <x-p-fact :label="$row['label']">{{ $row['effect'] }}</x-p-fact>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <footer class="p-page-foot">
        <p class="u-eyebrow p-page-foot__caption">
            {{ __('First to Act league Standard') }} &bull; {{ __('Season') }} {{ date('Y') }}
        </p>
        <hr class="p-rule">
    </footer>
</x-public-layout>
