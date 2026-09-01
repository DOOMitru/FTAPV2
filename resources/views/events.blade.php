<x-public-layout>
    <x-p-hero suit="diamond" :eyebrow="__('League Schedule')"
              :title="__('Upcoming Events')"
              :highlight="__('Events')">
        {{ __('Every league night, in one place. Each card carries its venue, its start time, and the deadline to register.') }}
    </x-p-hero>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert variant="danger">{{ session('error') }}</x-alert>
    @endif

    <div class="l-stack">
        @forelse ($upcomingTournaments as $tournament)
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


                    <div class="p-event__actions">
                        <x-btn variant="primary" :href="route('tournaments.show', $tournament)">
                            {{ __('Details') }}
                        </x-btn>

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
                    </div>
                </div>
            </article>
        @empty
            <x-empty-state :title="__('No Scheduled Events')">
                {{ __('No events are scheduled yet. Dates for the next season go up here first.') }}
            </x-empty-state>
        @endforelse
    </div>

    {{ $upcomingTournaments->links() }}

    @if ($pastTournaments->isNotEmpty())
        <section>
            <div class="p-part">
                <h2 class="p-part__label">{{ __('Tournament Archives') }}</h2>
                <span class="p-part__line" aria-hidden="true"></span>
            </div>

            <div class="p-archive">
                @foreach ($pastTournaments as $tournament)
                    <a class="p-archive__card p-raised p-lift" href="{{ route('tournaments.show', $tournament) }}">
                        <div class="l-cluster l-cluster--between">
                            <span class="u-eyebrow">{{ $tournament->start_time->format('M Y') }}</span>
                            <x-badge>{{ __('Completed') }}</x-badge>
                        </div>

                        <h3 class="p-archive__title">{{ $tournament->name }}</h3>

                        <span class="p-leader__nickname">{{ $tournament->venue->name ?? __('Location TBD') }}</span>

                        {{-- sortBy('place'), not the collection's own order: the
                             results relation is unordered, so take(3) alone gave
                             three arbitrary players rather than the podium. --}}
                        @php $podium = $tournament->results->sortBy('place')->take(3); @endphp

                        @if ($podium->isNotEmpty())
                            <ol class="p-podium">
                                @foreach ($podium as $result)
                                    <li class="p-podium__row">
                                        <x-rank :place="$result->place" />
                                        <span class="p-podium__name">{{ $result->player_name }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        <div class="p-archive__foot">
                            <span class="p-archive__more">{{ __('Full results') }}</span>

                            <svg class="p-archive__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-public-layout>
