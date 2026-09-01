<x-public-layout>
    <div class="p-split">
        <div>
            <x-p-hero suit="spade" align="start"
                      :eyebrow="__('Regina, Saskatchewan')"
                      :title="__('First to Act Poker League')"
                      :highlight="__('Poker League')">
                {{ __("Free Texas Hold'em every week across Regina. Play the season, earn points at every table, and the top 20 play the finale — for a prize pool funded entirely by local sponsors.") }}
            </x-p-hero>

            <div class="l-cluster">
                <x-btn variant="primary" :href="route('register')">{{ __('Join Now') }}</x-btn>
                <x-btn variant="ghost" :href="route('about.index')">{{ __('Learn More') }}</x-btn>
            </div>
        </div>

        <img class="p-figure" src="{{ asset('images/hero_logo.png') }}" alt="">
    </div>

    <section class="p-section">
        <x-p-hero plain :level="2" suit="diamond"
                  :eyebrow="__('League Schedule')"
                  :title="$currentSeason ? $currentSeason->name . ' ' . __('is Here') : __('Season Launching Soon')">
            {{ __('Where the season stands, what is scheduled next, and who is in front.') }}
        </x-p-hero>

        <div class="l-grid l-grid--wide">
            <x-card :title="__('Season Status')" class="p-raised">
                @if ($currentSeason)
                    <dl class="rows">
                        <div class="row">
                            <dt class="row__label">{{ __('Active') }}</dt>
                            <dd class="row__value">{{ $currentSeason->name }}</dd>
                        </div>

                        <div class="row">
                            <dt class="row__label">{{ __('Duration') }}</dt>
                            <dd class="row__value">
                                {{ $currentSeason->start_date?->format('M Y') ?? '?' }} &ndash;
                                {{ $currentSeason->end_date?->format('M Y') ?? '?' }}
                            </dd>
                        </div>

                        <div class="row">
                            <dt class="row__label">{{ __('Prize Pool') }}</dt>
                            <dd class="row__value">{{ __('Dynamic') }}</dd>
                        </div>
                    </dl>
                @else
                    <x-empty-state :title="__('No active season found.')" />
                @endif
            </x-card>

            <x-card :title="__('Next Event')" class="p-raised">
                @if ($nextTournament)
                    <dl class="rows">
                        <div class="row">
                            <dt class="row__label">{{ __('Event') }}</dt>
                            <dd class="row__value">{{ $nextTournament->name }}</dd>
                        </div>

                        <div class="row">
                            <dt class="row__label">{{ __('Starts') }}</dt>
                            <dd class="row__value">
                                {{ $nextTournament->start_time?->format('F d, Y') ?? __('TBD') }}
                                @if ($nextTournament->start_time)
                                    &middot; {{ $nextTournament->start_time->format('h:i A') }}
                                @endif
                            </dd>
                        </div>

                        <div class="row">
                            <dt class="row__label">{{ __('Venue') }}</dt>
                            <dd class="row__value">{{ $nextTournament->venue?->name ?? __('Location TBD') }}</dd>
                        </div>
                    </dl>
                @else
                    <x-empty-state :title="__('No upcoming events scheduled.')" />
                @endif
            </x-card>

            <section class="p-panel">
                <div class="p-panel__glow" aria-hidden="true"></div>

                <h3 class="p-panel__eyebrow">{{ __('Season Finale') }}</h3>

                <p class="p-panel__text">
                    {{ __('The top 20 players on the leaderboard at the end of the season qualify for the Grand Championship.') }}
                </p>

                {{-- Was href="#". The points structure page is what "View Point
                     System" has always meant. --}}
                <a class="p-panel__link" href="{{ route('rules.points-structure') }}">
                    {{ __('View Point System') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </section>
        </div>
    </section>

    <section class="p-section">
        <x-p-hero plain :level="2" suit="heart"
                  :eyebrow="__('Our Sponsors')"
                  :title="__('Proudly Supported By')"
                  :highlight="__('Supported By')">
            {{ __('These businesses pay for the finale prize pool. That is what keeps the games free.') }}
        </x-p-hero>

        @php
            $sponsors = [
                ['mark' => 'A♥',   'name' => 'Ace High',     'trade' => 'Beverages'],
                ['mark' => '🏠',    'name' => 'Full House',   'trade' => 'Hospitality'],
                ['mark' => '⚡',    'name' => 'Straight Tech', 'trade' => 'Solutions'],
                ['mark' => '💪',    'name' => 'All-In',       'trade' => 'Athletics'],
                ['mark' => 'K♠K♣', 'name' => 'Pocket Kings', 'trade' => 'Financial'],
            ];
        @endphp

        <div class="p-sponsors">
            @foreach ($sponsors as $sponsor)
                <div class="p-sponsor p-raised p-lift">
                    <span class="p-sponsor__mark" aria-hidden="true">{{ $sponsor['mark'] }}</span>
                    <span class="p-sponsor__name">{{ $sponsor['name'] }}</span>
                    <span class="p-sponsor__trade">{{ $sponsor['trade'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="p-cta">
            <p class="p-cta__lede">{{ __('Interested in becoming a sponsor?') }}</p>

            {{-- The about page has a real sponsorship form; a mailto: link was
                 sending people to their mail client instead. --}}
            <x-btn variant="primary" :href="route('about.index') . '#become-a-sponsor'">
                {{ __('Become a Sponsor') }}
            </x-btn>
        </div>
    </section>
</x-public-layout>
