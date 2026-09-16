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
            <x-p-event :tournament="$tournament" />
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
                    {{-- A link only for somebody who can follow it.
                         tournaments.show is behind the auth middleware, so for a
                         guest the whole card was a bounce to the login screen --
                         not just the "Full results" line at the foot of it, the
                         entire card, which is one big <a>. Hiding the line alone
                         would take away the signpost and leave the trap.

                         So the tag itself changes: an <a> when it can go
                         somewhere, a <div> when it cannot. p-lift goes with it,
                         because a card that rises to meet the pointer is
                         promising something to click. --}}
                    @php $linked = auth()->check(); @endphp

                    <{{ $linked ? 'a' : 'div' }} class="p-archive__card p-raised{{ $linked ? ' p-lift' : '' }}"
                        @if ($linked) href="{{ route('tournaments.show', $tournament) }}" @endif>
                        <div class="l-cluster l-cluster--between">
                            {{-- The day as well as the month. A league plays
                                 several nights a month, so "Sep 2026" named a
                                 handful of these cards at once and told you
                                 which one you were looking at only by its
                                 title. --}}
                            <span class="u-eyebrow">{{ $tournament->start_time->format('M d, Y') }}</span>
                            <x-badge>{{ __('Completed') }}</x-badge>
                        </div>

                        <h3 class="p-archive__title">{{ $tournament->name }}</h3>

                        <span class="p-leader__nickname">{{ $tournament->venue->name ?? __('Location TBD') }}</span>

                        {{-- The settled places only -- see
                             PokerTournament::podium(). This was
                             sortBy('place')->take(3), which is the current best
                             three: on a tournament still being played out they
                             are the last few knocked out, not the podium. --}}
                        @php $podium = $tournament->podium(); @endphp

                        {{-- Who won is for the league, not for the internet.
                             This was the one place in the app where a guest
                             read a player's name, and a name on a public page
                             is a name a search engine indexes -- which is not
                             what somebody agreed to by turning up to a bar on a
                             Wednesday.

                             The EVENT stays public: the archive is there to
                             show a visitor that the league runs, where it plays
                             and how often. That argument never needed names to
                             make it. --}}
                        @if ($podium->isNotEmpty())
                            @auth
                                <ol class="p-podium">
                                    @foreach ($podium as $result)
                                        <li class="p-podium__row">
                                            <x-rank :place="$result->place" />
                                            <x-monogram :name="$result->player_name" size="sm" decorative />
                                            <span class="p-podium__name">
                                                <x-player-link :user="$result->user">{{ $result->player_name }}</x-player-link>
                                            </span>
                                        </li>
                                    @endforeach
                                </ol>
                            @else
                                {{-- Said, not silently omitted: a card that
                                     simply stopped after the venue would read
                                     as a tournament nobody finished. --}}
                                <p class="p-archive__gated">{{ __('Sign in to see the results.') }}</p>
                            @endauth
                        @endif

                        @if ($linked)
                            <div class="p-archive__foot">
                                <span class="p-archive__more">{{ __('Full results') }}</span>

                                <svg class="p-archive__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                </svg>
                            </div>
                        @endif
                    </{{ $linked ? 'a' : 'div' }}>
                @endforeach
            </div>
        </section>
    @endif
</x-public-layout>
