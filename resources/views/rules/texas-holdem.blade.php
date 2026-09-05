<x-public-layout>
    <x-p-hero suit="heart" :eyebrow="__('Official Gameplay Guide')"
              :title="__('Texas Hold\'em Rules')"
              :highlight="__('Hold\'em')">
        {{ __('The definitive guide to the world\'s most popular poker variant. From the initial shuffle to the final showdown, these regulations govern every hand played in First to Act tournaments.') }}
    </x-p-hero>

    {{-- One entry, and it earns its place: the hand hierarchy is a screen of
         cards, and without this the rules are a scroll away from the top of
         the page. --}}
    <nav class="p-subnav" aria-label="{{ __('On this page') }}">
        <a class="p-subnav__link" href="#holdem-rules">{{ __('Texas Hold\'em Rules') }}</a>
    </nav>

    <section id="hand-hierarchy" class="p-anchor p-panel">
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

    {{-- The rules themselves, from config/holdem.php. Data rather than
         markup: the page renders them, a test checks them, and neither one
         restates the other. --}}
    <section id="holdem-rules" class="p-anchor p-rules-doc">
        <x-p-section-head :title="__('Texas Hold\'em Game Rules')"
                          icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />

        <x-p-rules :items="config('holdem.rules')" />
    </section>

    <footer class="p-page-foot">
        <p class="u-eyebrow p-page-foot__caption">
            {{ __('First to Act league Standard') }}
        </p>
        <hr class="p-rule">
    </footer>
</x-public-layout>
