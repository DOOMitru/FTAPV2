<x-public-layout>
    <x-p-hero suit="spade" :eyebrow="__('League Standards & Governance')" :title="__('Rules and Regulations')" :highlight="__('Regulations')">
        {{ __('The First to Act league operates under a strict set of competitive standards. These rules ensure a consistent, fair, and professional environment for all participants.') }}
    </x-p-hero>

    <nav class="p-subnav" aria-label="{{ __('On this page') }}">
        <a class="p-subnav__link" href="#general-rules">{{ __('Tournament Rules') }}</a>
        <a class="p-subnav__link" href="#final-stakes">{{ __('The Season Finale') }}</a>
        <a class="p-subnav__link" href="#championship-rules">{{ __('Championship Rules') }}</a>
    </nav>

    {{-- The id is load-bearing: an old route redirects to this anchor. --}}
    <section id="general-rules" class="p-anchor p-rules-doc">
        <x-p-section-head :title="__('Tournament Rules')"
                          icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />

        <x-p-rules :items="config('regulations.tournament')" />
    </section>

    {{-- The finale, described before its rules: what it takes to get there and
         what shape it is, then the clauses that govern it. The id is another
         redirect target. --}}
    <section id="final-stakes" class="p-anchor">
        @php
            // Drawn from the championship rules below and from the season
            // thresholds the app enforces -- nothing here is decorative.
            //
            // "Point Multiplier: Double Weighted Points Awarded" used to sit in
            // this list. Nothing in the league's rules says that and the app
            // does not score it, so it was a rule invented by a layout.
            $finalFacts = [
                ['label' => 'Qualification', 'effect' => 'Season Points, Wins and Venue Points'],
                ['label' => 'Format', 'effect' => 'Two Tournaments, Run Together'],
                ['label' => 'Blind Levels', 'effect' => '20 Minutes'],
            ];
        @endphp

        <div class="p-panel p-panel--accent">
            <div class="p-panel__glow" aria-hidden="true"></div>

            <div class="p-panel__split">
                <div>
                    <h3 class="p-panel__title">{{ __('The Season Finale') }}</h3>

                    {{-- Earned, not ranked: a season sets three targets and
                         everyone meeting all of them plays, however many that
                         turns out to be. The figures are deliberately not
                         repeated here -- they differ per season and the season
                         page publishes them, so a number written into a rules
                         page is one that goes stale unnoticed. --}}
                    <div class="p-panel__text">
                        <p>{{ __('The season ends with a finale, and a place in it is earned rather than ranked. Each season sets the points, tournament wins and venue points a player needs, and everyone who meets all three plays. Every night before it counts toward getting there.') }}</p>

                        <p>{{ __('Those points also decide where you sit: the finale runs as two tournaments at once, and players are placed into one or the other by what they accumulated over the season.') }}</p>
                    </div>

                    <p class="p-pill">
                        <span class="p-pill__dot" aria-hidden="true"></span>
                        {{ __('One finale per season') }}
                    </p>
                </div>

                <div class="l-stack l-stack--tight">
                    @foreach ($finalFacts as $row)
                        <x-p-fact :label="$row['label']">{{ $row['effect'] }}</x-p-fact>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="championship-rules" class="p-anchor p-rules-doc">
        <x-p-section-head :title="__('Championship Game Rules')"
                          icon="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />

        <p class="p-rules-doc__lead">{{ config('regulations.championship_lead') }}</p>

        <x-p-rules :items="config('regulations.championship')" />
    </section>

    <footer class="p-page-foot">
        <p class="u-eyebrow p-page-foot__caption">
            {{ __('First to Act league Standard') }}
        </p>
        <hr class="p-rule">
    </footer>
</x-public-layout>
