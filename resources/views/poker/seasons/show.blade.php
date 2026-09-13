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

    <div class="l-container l-stack">
        {{-- stat-rows: on a phone these stop being three tiles side by side
             and become three labelled lines, the way the dashboard's season
             panel lists its own figures. Three thirds of a 375px screen leave
             each number about 100px, which is why the trio already had to shrink
             its type to fit -- a row gives the label and the figure the whole
             width and needs no shrinking at all.

             --boxed because this group stands on the page rather than inside a
             card, so it has to draw its own edge. The finale trio below is
             already inside one. --}}
        <div class="l-grid l-grid--trio stat-rows stat-rows--boxed">
            <x-stat :label="__('Tournaments')" :value="number_format($totalTournaments)" />
            <x-stat :label="__('Points awarded')" :value="number_format($totalPoints)" />
            <x-stat :label="__('Players')" :value="number_format($uniquePlayersCount)" />
        </div>

        {{-- Rendered either way. A season with no thresholds must SAY so: an
             absent panel is indistinguishable from a rendering failure, and a
             reader cannot tell "not decided yet" from "something broke". --}}
        <x-card :title="__('Finale Qualification')">
            @if ($season->hasThresholds())
                <p class="field__hint">{{ __('A player must meet all three to reach the finale.') }}</p>

                <div class="l-grid l-grid--trio stat-rows">
                    {{-- Each figure guarded on its own, not just the block.
                         hasThresholds() is true when ANY one is set, so a
                         partly decided season lands here -- and
                         number_format(null) renders 0, stating a target nobody
                         chose and everybody has already met. --}}
                    <x-stat :label="__('Tournament Wins')"
                            :value="$season->finale_wins_required !== null
                                ? (string) $season->finale_wins_required
                                : __('Not set')" />

                    <x-stat :label="__('Season points')"
                            :value="$season->finale_points_required !== null
                                ? number_format($season->finale_points_required)
                                : __('Not set')" />

                    <x-stat :label="__('Venue points')"
                            :value="$season->finale_venue_points_required !== null
                                ? number_format($season->finale_venue_points_required)
                                : __('Not set')" />
                </div>
            @else
                <x-empty-state :title="__('No thresholds yet')">
                    {{ __('The qualification thresholds for this season have not been set. Until they are, no player is measured against them.') }}
                </x-empty-state>
            @endif
        </x-card>

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
                    @php
                        $leaderPoints = $leaderboard->first()['points'];

                        // Venue points are a back-office tally: they are
                        // awarded by hand at the bar, corrected by hand, and a
                        // player's own figure is already on their dashboard.
                        // What the column adds is everyone ELSE's, which is
                        // admin business. The finale mark stays for everyone,
                        // because the thresholds it is measured against are
                        // published in the panel above this table.
                        $showsVenuePoints = auth()->user()->is_admin;
                    @endphp

                    {{-- The mobile layout is CSS only: same markup, same
                         cells, reflowed by _season-show.css below 48rem. The
                         house .table--stacked modifier was the obvious answer
                         and the wrong one -- it turns each row into seven
                         label/value lines, so twenty players become a hundred
                         and forty, and the ranking disappears into a list. --}}
                    <x-table :caption="__('Season standings')" class="season-show__standings">
                        <x-slot name="head">
                            <th scope="col">{{ __('Rank') }}</th>
                            <th scope="col">{{ __('Player') }}</th>
                            <th scope="col">{{ __('Pts') }}</th>
                            <th scope="col" class="table__num">{{ __('Played') }}</th>
                            <th scope="col" class="table__num">{{ __('Won') }}</th>
                            @if ($showsVenuePoints)
                                <th scope="col" class="table__num">{{ __('Venue pts') }}</th>
                            @endif
                            <th scope="col">{{ __('Finale') }}</th>
                        </x-slot>

                        @foreach ($leaderboard as $index => $row)
                            @php
                                // The viewer's own row. A result can carry no
                                // linked account at all, so both sides must
                                // have one before the ids are compared.
                                //
                                // Defensive, and deliberately untested: this
                                // route is inside the auth group, so
                                // auth()->id() is never null and no assertion
                                // here can fail without the guard. Move the
                                // route out of that group -- the archive pages
                                // are already public, so season standings
                                // going public is not far-fetched -- and every
                                // accountless result would be marked "you",
                                // silently and for everyone.
                                $isSelf = $row['user'] !== null && $row['user']->id === auth()->id();
                            @endphp
                            <tr class="{{ $isSelf ? 'season-show__self-row' : '' }}">
                                <td class="season-show__rank"><x-rank :place="$index + 1" /></td>
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
                                <td class="season-show__player">
                                    @if (filled($nickname))
                                        {{-- Full name on hover, since the visible text is a nickname. --}}
                                        <span title="{{ $row['player_name'] }}">{{ $shownName }}</span>
                                    @else
                                        {{ $shownName }}
                                    @endif

                                    {{-- The tint says "this is you" to everyone
                                         who can see it. This says it to everyone
                                         else, and is the only reason the row is
                                         not carrying the information in colour
                                         alone. --}}
                                    @if ($isSelf)
                                        <span class="u-visually-hidden">{{ __('(you)') }}</span>
                                    @endif
                                </td>
                                <td class="season-show__meter-cell">
                                    {{-- Deliberately still the whole word. This
                                         is the meter's accessible name, read
                                         aloud rather than seen, and "PTS for
                                         Wanda Reeve" is worse to hear than
                                         "Points for Wanda Reeve". --}}
                                    <x-meter :value="$row['points']" :max="$leaderPoints"
                                             :label="__('Points for :name', ['name' => $shownName])" />
                                </td>
                                {{-- data-label feeds the mobile stat run through
                                     ::after -- "12 played", not "played 12",
                                     because a stat line reads value first. --}}
                                <td class="table__num season-show__stat season-show__stat--played" data-label="{{ __('played') }}">{{ $row['played'] }}</td>
                                <td class="table__num season-show__stat season-show__stat--won" data-label="{{ __('won') }}">{{ $row['wins'] }}</td>

                                @if ($showsVenuePoints)
                                    <td class="table__num season-show__stat season-show__stat--venue" data-label="{{ __('venue pts') }}">{{ $row['venue_points'] }}</td>
                                @endif

                                {{-- A mark when they are in, and nothing when they are
                                     not. This column used to name what a player was
                                     short on; the thresholds are published on this same
                                     page, in the panel above, so the standings can just
                                     answer the question they are asked.

                                     Neither branch is silent to a screen reader: an
                                     empty cell reads as "blank", which does not say
                                     whether the row failed or the column is not in
                                     use. --}}
                                <td class="season-show__finale">
                                    @if (! $season->hasThresholds())
                                        &mdash;
                                    @elseif ($row['qualified'])
                                        <span class="season-show__qualified" title="{{ __('Qualified') }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="3" stroke-linecap="round"
                                                 stroke-linejoin="round" aria-hidden="true">
                                                <path d="M20 6L9 17l-5-5"/>
                                            </svg>

                                            <span class="u-visually-hidden">{{ __('Qualified') }}</span>
                                        </span>
                                    @else
                                        <span class="u-visually-hidden">{{ __('Not qualified') }}</span>
                                    @endif
                                </td>
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
