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

                    <div class="venue-show__stats">
                        <x-stat :label="__('Tournaments')" :value="$totalTournaments" />
                        <x-stat :label="__('Point Earners')" :value="$uniqueVenuePointPlayers" />
                        <x-stat :label="__('Venue pts')" :value="number_format($totalVenuePoints)" />
                        <x-stat :label="__('Tournament pts')" :value="number_format($totalTournamentPoints)" />
                    </div>
                </div>
            </div>
        </x-card>

        {{-- align-items: start, so an empty leaderboard does not stretch to
             match a list of nineteen tournaments beside it. --}}
        <div class="l-sidebar venue-show__panels">
            <x-card :title="__('Venue pts leaderboard')" flush>
                <x-table>
                    <x-slot name="head">
                        <th scope="col">{{ __('Rank') }}</th>
                        <th scope="col">{{ __('Player') }}</th>
                        <th scope="col" class="table__num">{{ __('Earned Count') }}</th>
                        <th scope="col" class="table__num">{{ __('Total pts') }}</th>
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

                            <td class="table__num">{{ $entry['count'] }}</td>

                            <td class="table__num">{{ number_format($entry['total_amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                {{-- Still the whole word: this is a sentence, not a label. "No venue
                                     pts awarded here yet" is an abbreviation
                                     read as prose. --}}
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
