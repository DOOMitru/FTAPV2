<x-public-layout>
    <x-p-hero suit="spade" :eyebrow="__('League Standards & Governance')" :title="__('Rules and Regulations')" :highlight="__('Regulations')">
        {{ __('The First to Act league operates under a strict set of competitive standards. These rules ensure a consistent, fair, and professional environment for all participants.') }}
    </x-p-hero>

    <nav class="p-subnav" aria-label="{{ __('On this page') }}">
        <a class="p-subnav__link" href="#general-rules">{{ __('General Rules') }}</a>
        <a class="p-subnav__link" href="#season-structure">{{ __('Season Structure') }}</a>
        <a class="p-subnav__link" href="#final-stakes">{{ __('Final Stakes') }}</a>
    </nav>

    <section id="general-rules" class="p-anchor">
        <div class="p-part">
            <h2 class="p-part__label">{{ __('Part I: Tournament Fundamentals') }}</h2>
            <span class="p-part__line" aria-hidden="true"></span>
        </div>

    @php
        $generalRules = [

        ['title' => 'Standard Play', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'text' => 'All tournaments follow standard TDA (Tournament Directors Association) rules unless otherwise specified.'],
        ['title' => 'Blind Intervals', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Blinds increase every 20 minutes. A signal will sound at the end of each level.'],
        ['title' => 'Re-Entry Policy', 'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'text' => 'Players may re-enter until the end of the first break. Only one re-entry is permitted per player.'],
        ['title' => 'The Clock', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'If a player takes an excessive amount of time, any active player may call "Clock". The player then has 60 seconds to act.'],
        ];
    @endphp

        <div class="l-stack l-stack--tight">
            @foreach ($generalRules as $rule)
                <x-p-item :icon="$rule['icon']" :title="$rule['title']">{{ $rule['text'] }}</x-p-item>
            @endforeach
        </div>
    </section>

    <section id="season-structure" class="p-anchor">
        <div class="p-part">
            <h2 class="p-part__label">{{ __('Part II: Seasonal Progression') }}</h2>
            <span class="p-part__line" aria-hidden="true"></span>
        </div>

    @php
        $seasonRules = [

        ['id' => '01', 'title' => 'Tournament Schedule', 'text' => 'The season consists of 12 regular tournaments held over the course of the year.'],
        ['id' => '02', 'title' => 'Points Accumulation', 'text' => 'Players earn points based on their finishing position in each tournament. See the Points Structure page for details.'],
        ['id' => '03', 'title' => 'Seasonal Standings', 'text' => 'Standings are updated after every event. The top performers are highlighted in the league dashboard.'],
        ['id' => '04', 'title' => 'Qualification', 'text' => 'Participation in the Final Stakes is earned through consistent performance throughout the season.'],
        ];
    @endphp

        <div class="l-stack l-stack--tight">
            @foreach ($seasonRules as $rule)
                <x-p-item :number="$rule['id']" :title="$rule['title']">{{ $rule['text'] }}</x-p-item>
            @endforeach
        </div>
    </section>

    <section id="final-stakes" class="p-anchor">
        <div class="p-part">
            <h2 class="p-part__label">{{ __('Part III: The Seasonal Finale') }}</h2>
            <span class="p-part__line" aria-hidden="true"></span>
        </div>

    @php
        $finalRules = [

        ['label' => 'Qualification', 'effect' => 'Top 10 Players in Seasonal Standings'],
        ['label' => 'Point Multiplier', 'effect' => 'Double Weighted Points Awarded'],
        ['label' => 'The Trophy', 'effect' => 'Crown of the First to Act Champion'],
        ];
    @endphp

        <div class="p-panel p-panel--accent">
            <div class="p-panel__glow" aria-hidden="true"></div>

            <div class="p-panel__split">
                <div>
                    <h3 class="p-panel__title">{{ __('The Final Stakes') }}</h3>

                    <p class="p-panel__text">{{ __('The ultimate test of skill and endurance. The season culminates in a high-stakes finale where the league champion is crowned.') }}</p>

                    <p class="p-pill">
                        <span class="p-pill__dot" aria-hidden="true"></span>
                        {{ __('Championship Protocols Active') }}
                    </p>
                </div>

                <div class="l-stack l-stack--tight">
                    @foreach ($finalRules as $row)
                        <x-p-fact :label="$row['label']">{{ $row['effect'] }}</x-p-fact>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <footer class="p-page-foot">
        <p class="u-eyebrow p-page-foot__caption">
            {{ __('First to Act league Standard') }} &bull; {{ __('Established') }} {{ date('Y') }}
        </p>
        <hr class="p-rule">
    </footer>
</x-public-layout>
