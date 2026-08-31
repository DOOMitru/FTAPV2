<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('League')" :title="$season->name">
            @if (auth()->user()->is_admin)
                <x-slot name="actions">
                    <x-btn variant="primary" :href="route('poker.seasons.edit', $season)">{{ __('Edit season') }}</x-btn>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="l-stack">
        <div class="l-grid">
            <x-stat :label="__('Tournaments')" :value="number_format($totalTournaments)" />
            <x-stat :label="__('Points awarded')" :value="number_format($totalPoints)" />
            <x-stat :label="__('Players')" :value="number_format($uniquePlayersCount)" />
        </div>

        <div class="l-sidebar">
            <x-card :title="__('Standings')" :flush="true">
                @if ($leaderboard->isEmpty())
                    <x-empty-state :title="__('No results yet')">
                        {{ __('Standings appear once the first tournament result is recorded.') }}
                        @if (auth()->user()->is_admin)
                            <x-slot name="action">
                                <x-btn variant="primary" size="sm" :href="route('poker.results.create')">{{ __('Record a result') }}</x-btn>
                            </x-slot>
                        @endif
                    </x-empty-state>
                @else
                    @php $leaderPoints = $leaderboard->first()['points']; @endphp

                    <x-table :caption="__('Season standings')">
                        <x-slot name="head">
                            <th scope="col">{{ __('Rank') }}</th>
                            <th scope="col">{{ __('Player') }}</th>
                            <th scope="col">{{ __('Points') }}</th>
                            <th scope="col" class="table__num">{{ __('Played') }}</th>
                            <th scope="col" class="table__num">{{ __('Won') }}</th>
                        </x-slot>

                        @foreach ($leaderboard as $index => $row)
                            <tr>
                                <td><x-rank :place="$index + 1" /></td>
                                @php
                                    // Standings name a player by nickname when they have one,
                                    // otherwise by their full name — deliberately NOT the
                                    // User::display_name accessor, which falls back to the
                                    // first name alone and would drop surnames here.
                                    // player_name is the full name snapshotted onto the result,
                                    // and is the only name available when a result has no
                                    // linked user account.
                                    $nickname = $row['user']?->nickname;
                                    $shownName = filled($nickname) ? $nickname : $row['player_name'];
                                @endphp
                                <td>
                                    @if (filled($nickname))
                                        {{-- Full name on hover, since the visible text is a nickname. --}}
                                        <span title="{{ $row['player_name'] }}">{{ $shownName }}</span>
                                    @else
                                        {{ $shownName }}
                                    @endif
                                </td>
                                <td class="season-show__meter-cell">
                                    <x-meter :value="$row['points']" :max="$leaderPoints"
                                             :label="__('Points for :name', ['name' => $shownName])" />
                                </td>
                                <td class="table__num">{{ $row['played'] }}</td>
                                <td class="table__num">{{ $row['wins'] }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>

            <div class="l-stack">
                <x-card :title="__('Venues')">
                    @forelse ($venueStats as $venue)
                        <div class="l-stack l-stack--tight">
                            <div class="l-cluster l-cluster--between">
                                <span>{{ $venue['name'] }}</span>
                                <span class="u-mono u-muted">{{ $venue['count'] }}</span>
                            </div>
                            <x-meter :value="$venue['count']" :max="$venueStats->max('count')" :show-value="false"
                                :label="__('Tournaments at :venue', ['venue' => $venue['name']])" />
                        </div>
                    @empty
                        <x-empty-state :title="__('No venues yet')">
                            {{ __('Venue usage appears once tournaments are scheduled.') }}
                        </x-empty-state>
                    @endforelse
                </x-card>

                <x-card :title="__('Tournaments')" :flush="true">
                    @forelse ($season->tournaments as $tournament)
                        <a class="season-show__tournament" href="{{ route('tournaments.show', $tournament) }}">
                            <span>{{ $tournament->name }}</span>
                            <span class="u-mono u-muted">{{ $tournament->start_time?->format('d M Y') }}</span>
                        </a>
                    @empty
                        <x-empty-state :title="__('Nothing scheduled')">
                            {{ __('Add a tournament to start this season.') }}
                        </x-empty-state>
                    @endforelse
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
