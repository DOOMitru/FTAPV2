@props(['tournament', 'details' => true])

{{-- One upcoming-event card, shared by the events page and the home page.
     Extracted rather than copied: the two would drift, and this card already
     carries four conditional branches -- registration open, already
     registered, awaiting approval, closed -- that must agree with the
     controller wherever it is drawn. --}}
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
        <div class="l-cluster">
            {{-- Conditional, deliberately. This badge used to render
                 unconditionally on every upcoming tournament, so a
                 tournament whose registration had closed still
                 announced "Registration Open" while showing no
                 register button. registration_open is true only when
                 scheduled_at is set AND not past -- which is not the
                 same as "play has not started". --}}
            @if ($tournament->registration_open)
                <x-badge variant="open">{{ __('Registration Open') }}</x-badge>
            @else
                <x-badge>{{ __('Registration Closed') }}</x-badge>
            @endif

            <x-badge>{{ $tournament->season->name }}</x-badge>
        </div>

        <div>
            <h2 class="p-event__title">{{ $tournament->name }}</h2>

            {{-- Directly under the name: the venue is part of what the
                 event IS, not one of the two dates beside it. --}}
            <p class="p-event__venue">{{ $tournament->venue->name ?? __('Location TBD') }}</p>
        </div>

        <div class="p-event__facts">
            <div>
                <span class="p-contact__label">{{ __('Registration Closes') }}</span>
                <span class="p-contact__value">
                    {{ ($tournament->scheduled_at ?? $tournament->start_time)->format('M d, Y') }}
                    &middot; {{ ($tournament->scheduled_at ?? $tournament->start_time)->format('h:i A') }}
                </span>
            </div>

            <div>
                <span class="p-contact__label">{{ __('Starts') }}</span>
                <span class="p-contact__value">
                    {{ $tournament->start_time->format('M d, Y') }}
                    &middot; {{ $tournament->start_time->format('h:i A') }}
                </span>
            </div>
        </div>


        {{-- Anything a consumer wants between the facts and the actions --
             the tournament's own description, contextual links. Empty on the
             events and home pages, which pass no slot. --}}
        @if (trim($slot) !== '')
            {{ $slot }}
        @endif

        <div class="p-event__actions">
            {{-- The details page draws this same card and IS the destination;
                 a button linking to the page you are on is noise. --}}
            @if ($details)
                <x-btn variant="primary" :href="route('tournaments.show', $tournament)">
                    {{ __('Details') }}
                </x-btn>
            @endif

            @auth
                @if ($tournament->viewer_registered ?? false)
                    {{-- The controller rejects a second registration
                         anyway; saying so here is kinder than
                         offering a button that will fail. --}}
                    <x-badge variant="open">{{ __("You're registered") }}</x-badge>
                @elseif (! auth()->user()->isApproved())
                    {{-- Same reasoning as the branch above: the
                         controller refuses this case anyway, and a
                         button that vanishes with no explanation
                         reads as a bug rather than as a decision
                         pending on you. Both this and the guard
                         call isApproved(), so what is offered and
                         what is refused cannot disagree. --}}
                    <x-badge>{{ __('Awaiting approval') }}</x-badge>
                @elseif ($tournament->registration_open)
                    <form action="{{ route('tournaments.register', $tournament) }}" method="POST">
                        @csrf
                        <x-btn variant="ghost" type="submit">{{ __('Register') }}</x-btn>
                    </form>
                @endif
            @endauth

            {{-- Straight after the state it belongs with, so a consumer can add
                 a control the shared card has no business knowing about -- the
                 details page's Unregister, which exists nowhere else. --}}
            @isset($actions)
                {{ $actions }}
            @endisset

            {{-- Last in the cluster, and that is the whole reason they are here
                 rather than beside Details. The row is justify-content: flex-end
                 and wraps; whatever comes last is what drops to a second line,
                 and it should be a way of navigating away, never Register.

                 Signed in only, and not for tidiness: seasons.show is behind the
                 auth middleware, so a guest offered this would be bounced to the
                 login screen by the very next request. --}}
            @auth
                <x-btn variant="ghost" :href="route('seasons.show', $tournament->season)">
                    {{ __('Season Standings') }}
                </x-btn>

                {{-- poker.venues.show is inside the admin-only /poker prefix.
                     Offering it to a player is offering a 403. --}}
                @if (auth()->user()->is_admin && $tournament->venue)
                    <x-btn variant="ghost" :href="route('poker.venues.show', $tournament->venue)">
                        {{ __('Venue Report') }}
                    </x-btn>
                @endif
            @endauth
        </div>
    </div>
</article>
