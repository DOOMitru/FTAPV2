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
        <x-card flush>
            <div class="l-sidebar">
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

                <div class="l-stack">
                    <h2 class="entry__title">{{ $venue->name }}</h2>

                    @if ($venue->description)
                        <p class="field__hint">{{ $venue->description }}</p>
                    @endif

                    <div class="l-grid">
                        <x-stat :label="__('Tournaments')" :value="$totalTournaments" />
                        <x-stat :label="__('Point Earners')" :value="$uniqueVenuePointPlayers" />
                        <x-stat :label="__('Venue Points')" :value="number_format($totalVenuePoints)" />
                        <x-stat :label="__('Tournament Points')" :value="number_format($totalTournamentPoints)" />
                    </div>
                </div>
            </div>
        </x-card>

        <div class="l-sidebar">
            <x-card :title="__('Venue Points Leaderboard')" flush>
                <x-table>
                    <x-slot name="head">
                        <th scope="col">{{ __('Rank') }}</th>
                        <th scope="col">{{ __('Player') }}</th>
                        <th scope="col" class="table__num">{{ __('Earned Count') }}</th>
                        <th scope="col" class="table__num">{{ __('Total Points') }}</th>
                    </x-slot>

                    @forelse ($venueLeaderboard as $index => $entry)
                        <tr>
                            <td><x-rank :place="$index + 1" /></td>

                            <td>
                                <div class="entry__title">{{ $entry['user_name'] }}</div>

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
                    <a class="entry" href="{{ route('tournaments.show', $tournament) }}">
                        <div class="entry__body">
                            <div class="entry__title">{{ $tournament->name }}</div>

                            <div class="entry__meta">
                                <span>{{ \Illuminate\Support\Carbon::parse($tournament->start_time)->format('M d, Y') }}</span>
                                <span>{{ $tournament->season->name ?? '' }}</span>
                            </div>
                        </div>
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
