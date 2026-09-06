@props(['tournament', 'details' => true])

{{-- One upcoming-event card, shared by the events page, the home page and the
     tournament details page. Extracted rather than copied: the three would
     drift, and this card already carries four conditional branches --
     registration open, already registered, awaiting approval, closed -- that
     must agree with the controller wherever it is drawn. --}}
<article class="p-event p-raised">
    @if ($tournament->venue && $tournament->venue->address)
        <div class="map">
            <iframe title="{{ __('Map of :venue', ['venue' => $tournament->venue->name]) }}"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                    src="https://maps.google.com/maps?q={{ urlencode($tournament->venue->address) }}&t=&z=13&ie=UTF8&iwloc=&output=embed"></iframe>

            <div class="map__pin">
                <span class="p-icon-tile">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>

                <div>
                    <span class="p-contact__label">{{ __('Venue Address') }}</span>
                    <span class="p-contact__value">{{ $tournament->venue->address }}</span>
                </div>
            </div>
        </div>
    @endif

    <div class="p-event__body">
        {{-- Every piece of STATUS sits here, together. "Registered" and
             "Awaiting approval" used to live down in the action row, where a
             badge shared a line with buttons and had to be excluded from their
             layout. They are states, like the two beside them. --}}
        {{-- The badges wrap inside their own group; the menu is a separate
             item pinned to the end. Sharing one cluster, the menu's auto margin
             put it at the end of whatever line it happened to land on -- on a
             phone the badges wrap and it slid down to the second one, which is
             not the card's top-end corner. --}}
        <div class="p-event__status">
            <div class="l-cluster">
            {{-- No Registration Open / Registration Closed badge here any
                 more. Its only input was the deadline, and there is no deadline
                 -- a badge reading "Registration Open" on every card forever
                 tells a reader nothing they could act on. What governs entry
                 now is whether the tournament has results, and the card already
                 shows that: a settled field shows finishes, not a button. --}}
            <x-badge>{{ $tournament->season->name }}</x-badge>

            @auth
                {{-- "Registered" is not here any more: it moved down to the
                     action row, beside the button that undoes it. The badges in
                     this cluster describe the TOURNAMENT -- whether it is open,
                     which season it belongs to -- and that one describes YOU. --}}
                @if (! ($tournament->viewer_registered ?? false) && ! auth()->user()->isApproved())
                    {{-- Same reasoning: the controller refuses this case too,
                         and a button that vanishes with no explanation reads as
                         a bug rather than as a decision pending on you. Both
                         this and the guard call isApproved(), so what is
                         offered and what is refused cannot disagree. --}}
                    <x-badge>{{ __('Awaiting approval') }}</x-badge>
                @endif
            @endauth

            </div>

            {{-- Everything except Register lives in here now. In the card's
                 top-end corner, in flow beside the badges rather than
                 positioned over them -- .p-event has overflow:hidden to clip
                 the map to the card's corners, and an absolutely placed panel
                 would be cut off by it. --}}
            <x-dropdown class="p-event__menu">
                <x-slot name="trigger">
                    {{-- type="button": this sits inside the card and, on the
                         details page, inside nothing that submits -- but the
                         Register form is a sibling and a bare <button> defaults
                         to submit. --}}
                    <button type="button" class="action" title="{{ __('More actions') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="5" r="1"/>
                            <circle cx="12" cy="12" r="1"/>
                            <circle cx="12" cy="19" r="1"/>
                        </svg>

                        <span class="u-visually-hidden">{{ __('More actions') }}</span>
                    </button>
                </x-slot>

                <x-slot name="content">
                    {{-- The details page draws this same card and IS the
                         destination; an entry pointing at the page you are on
                         is noise. --}}
                    @if ($details)
                        <x-dropdown-link :href="route('tournaments.show', $tournament)">
                            {{ __('Details') }}
                        </x-dropdown-link>
                    @endif

                    {{-- Signed in only, and not for tidiness: seasons.show is
                         behind the auth middleware, so a guest offered this
                         would be bounced to the login screen by the very next
                         request. --}}
                    @auth
                        <x-dropdown-link :href="route('seasons.show', $tournament->season)">
                            {{ __('Season Standings') }}
                        </x-dropdown-link>

                        {{-- poker.venues.show is inside the admin-only /poker
                             prefix. Offering it to a player is offering a 403. --}}
                        @if (auth()->user()->is_admin && $tournament->venue)
                            <x-dropdown-link :href="route('poker.venues.show', $tournament->venue)">
                                {{ __('Venue Report') }}
                            </x-dropdown-link>
                        @endif
                    @endauth
                </x-slot>
            </x-dropdown>
        </div>

        {{-- The date as a calendar leaf, beside the name it belongs to. It was
             a line of text in a two-column fact grid, indistinguishable from
             the registration cutoff next to it -- and the one date a player
             actually needs is when play starts. --}}
        {{-- The name spans the card on its own line. Beside the calendar leaf
             it had only the width left over, so a long tournament name wrapped
             to two or three lines against a leaf that is four rem wide. --}}
        <h2 class="p-event__title">{{ $tournament->name }}</h2>

        <div class="p-event__head">
            <div class="p-event__when" aria-hidden="true">
                <span class="p-event__month">{{ $tournament->start_time->format('M') }}</span>
                <span class="p-event__day">{{ $tournament->start_time->format('j') }}</span>
                <span class="p-event__weekday">{{ $tournament->start_time->format('D') }}</span>
            </div>

            {{-- Where and when, and nothing else: the two things that go with
                 a date. --}}
            <div>
                {{-- The same pin the map overlay uses, so the two marks agree.
                     aria-hidden: it is a picture of the word beside it. --}}
                <p class="p-event__venue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>

                    {{ $tournament->venue->name ?? __('Location TBD') }}
                </p>

                {{-- The calendar leaf is aria-hidden, so this line carries the
                     whole date for a screen reader rather than the time alone. --}}
                <p class="p-event__time">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>

                    <span class="u-visually-hidden">{{ __('Starts') }}:</span>
                    <span aria-hidden="true">{{ $tournament->start_time->format('g:i A') }}</span>
                    <span class="u-visually-hidden">{{ $tournament->start_time->format('l j F Y, g:i A') }}</span>
                </p>
            </div>
        </div>

        {{-- Anything a consumer wants between the facts and the actions -- the
             tournament's own description, contextual links. Empty on the events
             and home pages, which pass no slot. --}}
        @if (trim($slot) !== '')
            {{ $slot }}
        @endif

        @php
            // Where the viewer stands, shown beside the control that changes
            // it. The controller rejects a second registration anyway, so
            // saying so is kinder than offering a button that fails.
            $isRegistered = auth()->check() && ($tournament->viewer_registered ?? false);

            // The two states of one control. A player either joins from here or
            // leaves from here, and the row holds whichever applies.
            // No deadline condition. Entering late is safe -- the shift hook
            // moves recorded finishes down to match the bigger field -- so the
            // only things stopping a player entering are being in already and
            // not being approved yet.
            $canRegister = auth()->check()
                && ! $isRegistered
                && auth()->user()->isApproved();

            // The controller's one rule, restated. Withdrawing is refused only
            // once a finish is on record, because a place is a position in a
            // field and taking a player out of a settled one makes every
            // recorded place describe a tournament that never happened.
            //
            // Asymmetric with $canRegister above, deliberately: joining a field
            // of ten makes it a field of eleven, unambiguously, while leaving
            // one leaves the question of whether you played at all.
            //
            // This used to arrive through an `actions` slot that only the
            // details page filled, on the reasoning that leaving a tournament
            // was something you did from its own page. It is not -- wherever a
            // card is willing to tell you that you are registered, it should be
            // willing to let you undo it. The slot had no other caller, so it
            // is gone rather than left as an extension point nothing extends.
            $canUnregister = $isRegistered && ! $tournament->hasRecordedResults();
        @endphp

        @if ($isRegistered || $canRegister || $canUnregister)
            <div class="p-event__actions">
                {{-- At the start of the row; the buttons take the end. --}}
                @if ($isRegistered)
                    <x-badge variant="open">{{ __('Registered') }}</x-badge>
                @endif

                @if ($canRegister || $canUnregister)
                <div class="p-event__actions-end">
                @if ($canRegister)
                    {{-- Primary: it is the thing the card wants you to do. --}}
                    <form action="{{ route('tournaments.register', $tournament) }}" method="POST">
                        @csrf
                        <x-btn variant="primary" type="submit">{{ __('Register') }}</x-btn>
                    </form>
                @endif

                {{-- Standing where Register would. The two are mutually
                     exclusive: nobody can both join and leave the same
                     tournament.

                     Ghost, not danger. Leaving is not what the card is asking
                     you to do, and a red button alone in that row would read as
                     the call to action.

                     The message names the tournament because the events page
                     draws a column of these and "this tournament" would be
                     whichever one you happened to click. --}}
                @if ($canUnregister)
                    <form action="{{ route('tournaments.unregister', $tournament) }}" method="POST"
                          data-confirm="{{ __('Unregister from :tournament? You can enter again any time before results are recorded.', [
                              'tournament' => $tournament->name,
                          ]) }}">
                        @csrf
                        @method('DELETE')

                        <x-btn variant="ghost" type="submit">{{ __('Unregister') }}</x-btn>
                    </form>
                @endif
                </div>
                @endif
            </div>
        @endif
    </div>
</article>
