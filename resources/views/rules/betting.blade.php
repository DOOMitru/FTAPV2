<x-public-layout>
    <x-p-hero suit="club" :eyebrow="__('Financial & Player Governance')" :title="__('Betting and Conduct')" :highlight="__('Conduct')">
        {{ __('Fairness at the table is maintained through strict financial integrity and mutual respect. These regulations define our mechanical and behavioral standards.') }}
    </x-p-hero>

    {{-- Two sections now, not three. The old Enforcement panel restated an
         escalation ladder that behaviour rule 5 already sets out clause by
         clause; kept, it would have been the same rule written twice, in two
         places, free to disagree. --}}
    <nav class="p-subnav" aria-label="{{ __('On this page') }}">
        <a class="p-subnav__link" href="#betting-rules">{{ __('Betting Rules') }}</a>
        <a class="p-subnav__link" href="#conduct-rules">{{ __('Behaviour Rules') }}</a>
    </nav>

    {{-- Both sets come from config/conduct.php and render through the same
         component as the hold'em rules, so the league's rule pages read alike
         and a clause is cited the same way on either of them. --}}
    <section id="betting-rules" class="p-anchor p-rules-doc">
        <x-p-section-head :title="__('Betting Rules')"
                          icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

        <x-p-rules :items="config('conduct.betting')" />
    </section>

    {{-- The id is load-bearing: /rules/behaviour redirects to this anchor. --}}
    <section id="conduct-rules" class="p-anchor p-rules-doc">
        <x-p-section-head :title="__('Behaviour Rules')"
                          icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />

        <x-p-rules :items="config('conduct.behaviour')" />
    </section>

    <footer class="p-page-foot">
        <p class="u-eyebrow p-page-foot__caption">
            {{ __('First to Act league Standard') }}
        </p>
        <hr class="p-rule">
    </footer>
</x-public-layout>
