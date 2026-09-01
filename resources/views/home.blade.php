<x-public-layout>
    {{-- The home lead is its own block rather than <x-p-hero>: it is the only
         hero carrying a mark, a tagline and a call to action, and bolting three
         optional props onto a component eight other pages share, to serve one
         page, is how a shared component rots. It reuses .p-hero__highlight and
         .p-hero__suit, both of which are standalone classes. --}}
    <section class="p-lead">
        {{-- Decorative: the title beside it says the name. First in the DOM
             because that is its mobile position; CSS order moves it right on a
             wide screen. --}}
        <img class="p-lead__mark" src="{{ asset('images/hero_logo.png') }}" alt="">

        <div class="p-lead__body">
            <p class="u-eyebrow p-lead__eyebrow">
                <span class="p-hero__suit" aria-hidden="true">&spades;</span>{{ __('Regina, Saskatchewan') }}
            </p>

            <h1 class="p-lead__title">
                {{ __('First to Act') }} <span class="p-hero__highlight">{{ __('Poker League') }}</span>
            </h1>

            {{-- Three spans, as in the footer: the motto may only break BETWEEN
                 clauses. text-wrap: balance was tried and is wrong here -- it
                 balances line lengths, which split "Play hard. Play / smart."
                 straight through the middle of a clause. --}}
            <p class="p-lead__tagline">
                <span>{{ __('Play hard.') }}</span>
                <span>{{ __('Play smart.') }}</span>
                <span>{{ __('Be first to act.') }}</span>
            </p>

            <p class="p-lead__lede">
                {{ __("Free Texas Hold'em every week, hosted by bars and lounges around Regina — turn up and you are backing them too. Season points, tournament wins and venue points all decide who plays the season finale, for a prize pool funded entirely by local sponsors.") }}
            </p>

            @auth
                {{-- A signed-in player has already joined; offering them "Join Now" is
                     asking for something they have done. --}}
                <div class="p-lead__welcome p-lead__actions">
                    <p class="p-lead__welcome-line">
                        {{ __('Welcome back,') }} <span class="p-hero__highlight">{{ auth()->user()->first_name }}</span>.
                    </p>

                    <p class="p-lead__welcome-note">
                        <a class="link" href="{{ route('dashboard') }}">{{ __('Your dashboard') }}</a>
                        {{ __('has your points, your results and what is coming up next.') }}
                    </p>
                </div>
            @else
                <div class="l-cluster p-lead__actions">
                    <x-btn variant="primary" :href="route('register')">{{ __('Join Now') }}</x-btn>
                    <x-btn variant="ghost" :href="route('about.index')">{{ __('Learn More') }}</x-btn>
                </div>
            @endauth
        </div>
    </section>


    {{-- Two sections, not one. "League Schedule" mixed the state of the season
         with the next date on the calendar -- two different questions, and a
         reader wanting either had to pick their card out of a row of three. --}}
    <section class="p-section">
        <x-p-hero plain :level="2" suit="diamond"
                  :eyebrow="__('The Season')"
                  :title="$currentSeason ? $currentSeason->name . ' ' . __('is Here') : __('Season Launching Soon')">
            {{ __('Where the season stands, and what it takes to reach the finale.') }}
        </x-p-hero>

        {{-- One card, not a card beside a gradient panel. "Season Status" and
             "Season Finale" ask the same question from two sides -- where the
             season is now, and what it takes to finish it -- and splitting them
             across two registers made the pair read as unrelated.

             The neutral register, not the panel's: merging into the gradient
             doubled the red field to the section's full width and made this
             block, rather than the hero, the loudest thing on the page. Red
             stays as accent here.

             .p-raised rather than the card component, so this matches the
             event card below it: shadow and a large radius, no hard border. --}}
        <div class="p-seasoncard p-raised">
            @if ($currentSeason)
                {{-- Rows are shared between the columns only when both hold a
                     facts list; see .p-seasoncard__cols--aligned. --}}
                <div class="p-seasoncard__cols{{ $currentSeason->hasThresholds() ? ' p-seasoncard__cols--aligned' : '' }}">
                    <div class="p-seasoncard__col">
                        <h3 class="p-seasoncard__head">{{ __('Where It Stands') }}</h3>

                        <dl class="p-seasoncard__facts">
                            <div class="p-seasoncard__row">
                                <dt class="p-contact__label">{{ __('Active') }}</dt>
                                <dd class="p-seasoncard__value">{{ $currentSeason->name }}</dd>
                            </div>

                            <div class="p-seasoncard__row">
                                <dt class="p-contact__label">{{ __('Runs') }}</dt>
                                <dd class="p-seasoncard__value">
                                    {{ $currentSeason->start_date?->format('M j, Y') ?? '?' }} &ndash;
                                    {{ $currentSeason->end_date?->format('M j, Y') ?? '?' }}
                                </dd>
                            </div>

                            <div class="p-seasoncard__row">
                                <dt class="p-contact__label">{{ __('Entry') }}</dt>
                                <dd class="p-seasoncard__value">{{ __('Free') }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="p-seasoncard__col p-seasoncard__col--tall">
                        <h3 class="p-seasoncard__head">{{ __('What It Takes') }}</h3>

                        {{-- Each figure is guarded on its own, not just the
                             block: hasThresholds() is true when ANY one is set,
                             so a season with only a points target reaches this
                             branch, and number_format(null) renders 0 -- a
                             target nobody chose and everybody has cleared. --}}
                        @if ($currentSeason->hasThresholds())
                            <dl class="p-seasoncard__facts">
                                <div class="p-seasoncard__row">
                                    <dt class="p-contact__label">{{ __('Season points') }}</dt>
                                    <dd class="p-seasoncard__value u-mono">
                                        {{ $currentSeason->finale_points_required !== null
                                            ? number_format($currentSeason->finale_points_required)
                                            : __('not set yet') }}
                                    </dd>
                                </div>

                                <div class="p-seasoncard__row">
                                    <dt class="p-contact__label">{{ __('Tournament wins') }}</dt>
                                    <dd class="p-seasoncard__value u-mono">
                                        {{ $currentSeason->finale_wins_required !== null
                                            ? $currentSeason->finale_wins_required
                                            : __('not set yet') }}
                                    </dd>
                                </div>

                                <div class="p-seasoncard__row">
                                    <dt class="p-contact__label">{{ __('Venue points') }}</dt>
                                    <dd class="p-seasoncard__value u-mono">
                                        {{ $currentSeason->finale_venue_points_required !== null
                                            ? number_format($currentSeason->finale_venue_points_required)
                                            : __('not set yet') }}
                                    </dd>
                                </div>
                            </dl>
                        @else
                            <p class="u-muted">
                                {{ __('The exact thresholds are still being set and will be published here once they are.') }}
                            </p>
                        @endif

                        {{-- Below the figures, not above them. Anything between
                             this heading and this list offsets the list from the
                             one in the column beside it, and the two stop
                             reading as one table.

                             Kept at all because the rows give three numbers and
                             never say you need all three, and "any one of these"
                             is a different competition. --}}
                        <p class="u-muted">
                            {{ __('Three things decide who plays: the points you accumulate over the season, how many tournaments you win, and the venue points you pick up along the way.') }}
                        </p>
                    </div>

                    {{-- Last in the DOM on purpose. Stacked, the link belongs at
                         the end of the card, which is where source order puts it;
                         side by side, CSS lifts it into the left column's third
                         row so it sits level with the sentence opposite. Nothing
                         is reordered, so it is last for a screen reader either
                         way. --}}
                    <a class="link p-cardlink p-seasoncard__link" href="{{ route('rules.points-structure') }}">
                        {{ __('View Point System') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                </div>
            @else
                {{-- No columns to level anything with, so the link is simply
                     last. x-empty-state is not used here: .empty is built on
                     the muted tokens, which is the register this card left. --}}
                <div class="p-seasoncard__col">
                    <h3 class="p-seasoncard__head">{{ __('Season Finale') }}</h3>

                    <p class="u-muted">
                        {{ __('Three things decide who plays: the points you accumulate over the season, how many tournaments you win, and the venue points you pick up along the way.') }}
                    </p>

                    <p class="u-muted">
                        {{ __('No season is running at the moment. The next one will appear here as soon as its dates are set.') }}
                    </p>

                    <a class="link p-cardlink" href="{{ route('rules.points-structure') }}">
                        {{ __('View Point System') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                </div>
            @endif
        </div>

        @auth
            @if ($topByPoints->isNotEmpty())
                {{-- Signed-in players only. A leaderboard is for people playing
                     in it; to a stranger it is a list of names. --}}
                <div class="l-grid p-season-standings">
                    <x-card :title="__('Most Points')" class="p-raised">
                        <ol class="p-standing">
                            @foreach ($topByPoints as $i => $row)
                                <li class="p-standing__row">
                                    <x-rank :place="$i + 1" />
                                    <span class="p-standing__name">{{ $row['name'] }}</span>
                                    <span class="p-standing__value">{{ number_format($row['points']) }} {{ __('pts') }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </x-card>

                    <x-card :title="__('Most Wins')" class="p-raised">
                        @if ($topByWins->isNotEmpty())
                            <ol class="p-standing">
                                @foreach ($topByWins as $i => $row)
                                    <li class="p-standing__row">
                                        <x-rank :place="$i + 1" />
                                        <span class="p-standing__name">{{ $row['name'] }}</span>
                                        <span class="p-standing__value">{{ $row['wins'] }} {{ trans_choice('win|wins', $row['wins']) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <x-empty-state :title="__('No wins yet this season.')" />
                        @endif
                    </x-card>
                </div>
            @endif
        @endauth
    </section>

    <section class="p-section">
        <x-p-hero plain :level="2" suit="club"
                  :eyebrow="__('Next Up')"
                  :title="$nextTournament ? __('The Next Event') : __('Nothing Scheduled Yet')">
            {{ $nextTournament
                ? __('The next league night, with everything you need to turn up.')
                : __('The calendar is empty for the moment.') }}
        </x-p-hero>

        @if ($nextTournament)
            {{-- The same card the events page draws, so the two cannot drift
                 apart -- including its register control, which is why the home
                 route loads viewer_registered the same way. --}}
            <x-p-event :tournament="$nextTournament" />
        @else
            <x-empty-state :title="__('No events on the calendar')">
                {{ __('Nothing is scheduled right now. Check back in a few days — new league nights go up here first, and the season page has everything played so far.') }}
            </x-empty-state>
        @endif
    </section>

    {{-- The whole section, heading included, only exists when there are
         sponsors. A "Proudly Supported By" heading over an empty grid
         advertises that nobody sponsors the league. --}}
    @if ($sponsors->isNotEmpty())
        <section class="p-section">
            <x-p-hero plain :level="2" suit="heart"
                      :eyebrow="__('Our Sponsors')"
                      :title="__('Proudly Supported By')"
                      :highlight="__('Supported By')">
                {{-- Warm on purpose, and the one place on this site where that
                     is right. Overclaiming to players costs credibility;
                     thanking the businesses who fund the prize pool is simply
                     owed, and every flourish here still maps to something
                     true. --}}
                {{ __('A heartfelt thank you to the local businesses standing behind this league. Their backing is what puts cards on the table every week, and we could not be prouder to have them in our corner. Please support the businesses that support us — tell them First to Act sent you.') }}
            </x-p-hero>

            <div class="p-sponsors">
                @foreach ($sponsors as $sponsor)
                    @php
                        $classes = 'p-sponsor p-raised p-lift'.($sponsor->isPremium() ? ' p-sponsor--premium' : '');
                    @endphp

                    {{-- The card is a link only when there is somewhere to go.
                         An <a> without an href is not a link, and a card that
                         looks clickable and is not is worse than a plain one. --}}
                    @if ($sponsor->website_url)
                        <a class="{{ $classes }}" href="{{ $sponsor->website_url }}"
                           target="_blank" rel="noopener noreferrer">
                            {{-- alt is the sponsor's NAME, not empty: the logo
                                 is the content here, and empty alt would leave
                                 a screen reader with nothing where a sponsor
                                 should be. --}}
                            <img class="p-sponsor__logo" src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }}">
                            <span class="p-sponsor__name">{{ $sponsor->name }}</span>
                            <span class="u-visually-hidden">{{ __('(opens in a new tab)') }}</span>
                        </a>
                    @else
                        <div class="{{ $classes }}">
                            <img class="p-sponsor__logo" src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }}">
                            <span class="p-sponsor__name">{{ $sponsor->name }}</span>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="p-cta">
                <p class="p-cta__lede">{{ __('Interested in becoming a sponsor?') }}</p>

                <x-btn variant="primary" :href="route('about.index') . '#become-a-sponsor'">
                    {{ __('Become a Sponsor') }}
                </x-btn>
            </div>
        </section>
    @endif

</x-public-layout>
