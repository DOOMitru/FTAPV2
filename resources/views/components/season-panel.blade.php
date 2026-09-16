@props(['season', 'currentSeason' => null, 'self' => true])

{{--
    One season's figures for one player.

    Shared by the dashboard and a player's profile, which show the same panel
    about different people -- as a copy it would have been a hundred lines free
    to drift, and the venue-points rule below is not something to enforce twice.

    self: whether the viewer is the player. It changes the copy only. A person
    reading their own figures is told what to DO about an empty one; a person
    reading somebody else's is told what is true.

    A null venuePoints means the viewer may not read them -- see
    VenuePoints::readableBy(), which the controller applies. The row is absent
    rather than blank or zero: a zero is a fact about the player, and this is a
    fact about the reader.
--}}
<x-card :title="$currentSeason?->name ?? __('Current Season')" class="season">
    @if ($currentSeason)
        <x-slot name="actions">
            <x-btn variant="ghost" size="sm" :href="route('seasons.show', $currentSeason)">
                {{ __('Full Season Stats') }}
            </x-btn>
        </x-slot>
    @endif

    @if (! $currentSeason)
        <x-empty-state :title="__('No season is running.')">
            {{ __('Season figures appear here once a season is current.') }}
        </x-empty-state>
    @else
        {{-- Three blocks, side by side when there is room and
             stacked when there is not: where you stand, what you won,
             and the totals behind it.

             They were one column down the left of a full-width card,
             which left two thirds of it empty and crowded the three
             groups into each other -- the rank, the medals and the
             totals all inside about 400px with hairlines between
             them. --}}
        <div class="season__grid">
            <div class="season__block season__standing">
                {{-- Label above the figure, and the figure's reasoning
                     pushed to the far side -- the shape every other row
                     in this panel has: what it is on the left, the
                     number on the right. --}}
                <p class="season__rank-label">{{ __('Season Rank') }}</p>

                <div class="season__standing-row">
                    <span class="season__rank-value">{{ $season['rank'] ? '#'.$season['rank'] : '—' }}</span>

                    <div class="season__ratio">
                    @if ($season['perEvent'] === null)
                        <p class="season__ratio-main">{{ __('Not entered yet') }}</p>
                        <p class="season__ratio-sub">
                            {{-- Addressed to the reader on their own
                                 dashboard, and about a third party on
                                 somebody else's page. --}}
                            {{ $self
                                ? __('Enter a tournament to be ranked.')
                                : __('A player is ranked once they enter a tournament.') }}
                        </p>
                    @else
                        {{-- The rank spelled out. One decimal: these run to
                             a couple of hundred points over a couple of
                             dozen events, so the second decimal is noise
                             that changes every night. --}}
                        <p class="season__ratio-main">
                            {{ number_format($season['perEvent'], 1) }} {{ __('pts per event') }}
                        </p>
                        <p class="season__ratio-sub">
                            {{ trans_choice('{1}:points pts over 1 event|[2,*]:points pts over :events events', $season['events'], [
                                'points' => number_format($season['points']),
                                'events' => number_format($season['events']),
                            ]) }}
                            @if ($season['rank'])
                                <br>{{ trans_choice('{1}of 1 ranked player|[2,*]of :count ranked players', $season['ranked']) }}
                            @endif
                        </p>
                    @endif
                    </div>
                </div>
            </div>

            {{-- Read the same way as the totals beside them: what it
                 is on the left, how many on the right. They were a row
                 of medal-and-count pairs, which made three figures of
                 the same kind look like a different kind of thing.

                 The badge carries no numeral now. It had one, and the
                 label beside it said the same thing again -- a gold 1
                 next to the words "Tournament Wins". What the badge is
                 for is the colour, which is the one thing the words
                 cannot say quickly. --}}
            <dl class="season__block season__podium">
                @foreach ([1 => __('Tournament Wins'), 2 => __('2nd Place Finishes'), 3 => __('3rd Place Finishes')] as $place => $label)
                    <div class="season__total">
                        <dt class="season__total-label">
                            {{-- .rank without content: the shape and
                                 the medal colour come from the same
                                 rules the standings use, so gold means
                                 here what it means there. aria-hidden
                                 because the label says it in words. --}}
                            <span class="rank rank--{{ $place }}" aria-hidden="true"></span>

                            {{ $label }}
                        </dt>

                        <dd class="season__total-value">{{ $season[[1 => 'first', 2 => 'second', 3 => 'third'][$place]] }}</dd>
                    </div>
                @endforeach
            </dl>

            <dl class="season__block season__totals">
                <div class="season__total">
                    <dt class="season__total-label">{{ __('Events Played') }}</dt>
                    <dd class="season__total-value">{{ number_format($season['events']) }}</dd>
                </div>

                <div class="season__total">
                    <dt class="season__total-label">{{ __('Season Points') }}</dt>
                    <dd class="season__total-value">{{ number_format($season['points']) }}</dd>
                </div>

                @if ($season['venuePoints'] !== null)
                    <div class="season__total">
                        <dt class="season__total-label">{{ __('Venue Points') }}</dt>
                        <dd class="season__total-value">{{ number_format($season['venuePoints']) }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    @endif
</x-card>
