<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="$venue->name">
            <x-slot name="actions">
                <x-btn variant="ghost" :href="route('poker.venues.index')">{{ __('Back') }}</x-btn>
                <x-btn variant="primary" :href="route('poker.venues.edit', $venue)">{{ __('Edit') }}</x-btn>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="l-container l-stack">
        {{-- Equal halves, and the body brings back the padding the flush card
             gives up for the map. This was .l-sidebar inside a flush card:
             2fr 1fr, so the map took two thirds and the name rendered hard
             against the card's top border with the stats in a single tall
             column beside it. --}}
        <x-card flush>
            <div class="venue-show__split">
                @if ($venue->address)
                    <div class="map">
                        <iframe title="{{ __('Map of :venue', ['venue' => $venue->name]) }}"
                                loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                                src="https://maps.google.com/maps?q={{ urlencode($venue->address) }}&t=&z=13&ie=UTF8&iwloc=&output=embed"></iframe>

                        <div class="map__pin">
                            <span class="p-contact__label">{{ __('Venue Address') }}</span>
                            <span class="p-contact__value">{{ $venue->address }}</span>
                        </div>
                    </div>
                @endif

                <div class="venue-show__body">
                    <h2 class="venue-show__name">{{ $venue->name }}</h2>

                    @if ($venue->description)
                        <p class="venue-show__lede">{{ $venue->description }}</p>
                    @endif

                    {{-- Two figures, not four. Point Earners and Tournament
                         pts both answered questions the panels below answer
                         better: the venue leaderboard IS the point earners,
                         named and counted, and tournament points are a league
                         total that says nothing about this venue in
                         particular. --}}
                    {{-- stat-rows: below 48rem these stop being tiles and
                         become labelled lines, the same treatment the season
                         page gives its figures. Not --boxed: they sit inside a
                         card, which already draws the edge, and the body
                         around them supplies the gutter. --}}
                    <div class="venue-show__stats stat-rows">
                        <x-stat :label="__('Tournaments')" :value="$totalTournaments" />
                        <x-stat :label="__('Venue points')" :value="number_format($totalVenuePoints)" />
                    </div>
                </div>
            </div>
        </x-card>

        {{-- align-items: start, so an empty leaderboard does not stretch to
             match a list of nineteen tournaments beside it. --}}
        <div class="l-sidebar venue-show__panels">
            <x-card :title="__('Venue points leaderboard')" flush>
                {{-- The form opens with this venue already chosen, which is
                     the whole reason the action lives here rather than on a
                     listing: an administrator entering a night's points is
                     standing on the venue they were earned at.

                     venue_id is the same query parameter store() hands back
                     between entries, so arriving from here and arriving from
                     the previous save are the same path. --}}
                <x-slot name="actions">
                    <x-btn variant="primary" size="sm"
                           :href="route('poker.venue-points.create', ['venue_id' => $venue->id])">
                        {{ __('Add points') }}
                    </x-btn>
                </x-slot>

                <x-table>
                    <x-slot name="head">
                        <th scope="col">{{ __('Rank') }}</th>
                        <th scope="col">{{ __('Player') }}</th>
                        <th scope="col" class="table__num">{{ __('Total points') }}</th>
                    </x-slot>

                    @forelse ($venueLeaderboard as $index => $entry)
                        <tr>
                            <td><x-rank :place="$index + 1" /></td>

                            <td>
                                <div class="entry__title">
                                    <x-player-link :user="$entry['user']">{{ $entry['user_name'] }}</x-player-link>
                                </div>

                                @if ($entry['last_earned'])
                                    <div class="entry__meta">
                                        <span>
                                            {{ __('Last earned') }}:
                                            {{ \Illuminate\Support\Carbon::parse($entry['last_earned'])->format('M d, Y') }}
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <td class="table__num">{{ number_format($entry['total_amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                {{-- The whole word, as everywhere else on this
                                     page now: the tile above and the panel
                                     heading were abbreviated and are not. --}}
                                <x-empty-state :title="__('No venue points awarded here yet.')" />
                            </td>
                        </tr>
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('Recent Tournaments')" flush>
                <x-slot name="actions">
                    <x-badge>{{ $totalTournaments }}</x-badge>
                </x-slot>

                @forelse ($venue->tournaments->sortByDesc('start_time')->take(10) as $tournament)
                    {{-- entry--link, because the whole row is the link. Without
                         it every piece of text in the row -- title, date and
                         season -- carries the browser's default underline, and
                         ten rows read as thirty links rather than ten. --}}
                    <a class="entry entry--link" href="{{ route('tournaments.show', $tournament) }}">
                        <div class="entry__body">
                            <div class="entry__title">{{ $tournament->name }}</div>

                            <div class="entry__meta">
                                <span>{{ $tournament->start_time->format('M d, Y') }}</span>
                                <span>{{ $tournament->season->name ?? '' }}</span>
                            </div>
                        </div>

                        {{-- The affordance the underlines were doing badly. A
                             mark that is always there, rather than a hover
                             state a touch screen never shows. --}}
                        <svg class="entry__go" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </a>
                @empty
                    <x-empty-state :title="__('No tournaments held here yet.')" />
                @endforelse

                @if ($totalTournaments > 10)
                    <p class="card__note">{{ __('Showing the 10 most recent') }}</p>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
