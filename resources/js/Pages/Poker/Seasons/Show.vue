<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Table from '../../../Components/Table.vue';
import Stat from '../../../Components/Stat.vue';
import Rank from '../../../Components/Rank.vue';
import Meter from '../../../Components/Meter.vue';
import Btn from '../../../Components/Btn.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Monogram from '../../../Components/Monogram.vue';
import PlayerLink from '../../../Components/PlayerLink.vue';
import { useConfirm } from '../../../composables/useConfirm';

interface SeasonRow {
    key: string;
    is_self: boolean;
    /** The place on the season's board -- shared on a tie, so two rows can both be 3. */
    rank: number;
    shown_name: string;
    /** Only when shown_name is a nickname; it becomes the link's title. */
    full_name: string | null;
    player_id: string | null;
    initials: string;
    meter_value: number;
    meter_max: number;
    meter_label: string;
    meter_value_label: string;
    played_label: string;
    wins_label: string;
    /** Null means withheld -- the figure never left the controller. */
    venue_points_label: string | null;
    finale_state: 'none' | 'qualified' | 'not-qualified';
}

interface Props {
    season: { id: string; name: string; delete_confirm: string };
    can_manage: boolean;
    tournaments_label: string;
    points_label: string;
    players_label: string;
    /** Null when no threshold is set at all. */
    finale: null | { wins_label: string; points_label: string; venue_points_label: string };
    shows_venue_points: boolean;
    standings: SeasonRow[];
    venues: Array<{ name: string; count: number; count_label: string; max: number; label: string }>;
    tournaments: Array<{ id: string; name: string; date_label: string | null; href: string }>;
}

const props = defineProps<Props>();

const { ask } = useConfirm();

const destroy = async (): Promise<void> => {
    if (await ask(props.season.delete_confirm)) {
        router.delete(`/poker/seasons/${props.season.id}`);
    }
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" :title="season.name">
                <template v-if="can_manage" #actions>
                    <Btn variant="primary" :href="`/poker/seasons/${season.id}/edit`">Edit season</Btn>

                    <!-- Deleting a season is offered where it is read, not from
                         a list. An administrator removing one has the thing in
                         front of them -- how many tournaments it holds, who is
                         leading it -- which is exactly what a row in a listing
                         cannot show. -->
                    <Btn variant="danger" type="button" @click="destroy">Delete season</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <!-- stat-rows: on a phone these stop being three tiles side by side
                 and become three labelled lines, the way the dashboard's season
                 panel lists its own figures. Three thirds of a 375px screen
                 leave each number about 100px.

                 --boxed because this group stands on the page rather than
                 inside a card, so it has to draw its own edge. The finale trio
                 below is already inside one. -->
            <div class="l-grid l-grid--trio stat-rows stat-rows--boxed">
                <Stat label="Tournaments" :value="tournaments_label" />
                <Stat label="Points awarded" :value="points_label" />
                <Stat label="Players" :value="players_label" />
            </div>

            <!-- Rendered either way. A season with no thresholds must SAY so:
                 an absent panel is indistinguishable from a rendering failure,
                 and a reader cannot tell "not decided yet" from "something
                 broke". -->
            <Card title="Finale Qualification">
                <template v-if="finale">
                    <p class="field__hint">A player must meet all three to reach the finale.</p>

                    <div class="l-grid l-grid--trio stat-rows">
                        <Stat label="Tournament Wins" :value="finale.wins_label" />
                        <Stat label="Season points" :value="finale.points_label" />
                        <Stat label="Venue points" :value="finale.venue_points_label" />
                    </div>
                </template>

                <EmptyState v-else title="No thresholds yet">
                    The qualification thresholds for this season have not been set. Until they
                    are, no player is measured against them.
                </EmptyState>
            </Card>

            <div class="l-sidebar">
                <Card title="Standings" flush class="season-show__standings-card">
                    <!-- No action any more. It offered "Record a result", which
                         went to a form that no longer exists: a result is
                         recorded by eliminating a player on the tournament
                         being played. The sentence says where instead. -->
                    <!-- True whether no tournament has been played or every
                         finish so far is worth nothing: players on 0 points
                         are not listed, so an empty table can sit beside a
                         night that is plainly under way. -->
                    <EmptyState v-if="standings.length === 0" title="Nobody has scored yet">
                        Standings appear once a player scores points this season.
                    </EmptyState>

                    <!-- The mobile layout is CSS only: same markup, same cells,
                         reflowed by _season-show.css below 48rem. The house
                         .table--stacked modifier was the obvious answer and the
                         wrong one -- it turns each row into seven label/value
                         lines, so twenty players become a hundred and forty. -->
                    <Table v-else caption="Season standings" class="season-show__standings">
                        <template #head>
                            <th scope="col">Rank</th>
                            <th scope="col">Player</th>
                            <th scope="col">Points</th>
                            <th scope="col" class="table__num">Played</th>
                            <th scope="col" class="table__num">Wins</th>
                            <th v-if="shows_venue_points" scope="col" class="table__num">Venue points</th>
                            <th scope="col">Finale</th>
                        </template>

                        <tr v-for="row in standings" :key="row.key"
                            :class="{ 'season-show__self-row': row.is_self }">
                            <td class="season-show__rank">
                                <Rank :place="row.rank" />
                            </td>

                            <td class="season-show__player">
                                <!-- An inner row, so the name is a block BESIDE
                                     the initials rather than text flowing around
                                     them. In the flow, a squeezed column broke
                                     "Wanda Reeve" after the first word and left
                                     "Reeve" under the disc.

                                     Not display:flex on the <td> itself: a
                                     flexed cell stops being a table-cell and
                                     leaves the column it was aligned in. -->
                                <span class="season-show__player-row">
                                    <!-- decorative: the name it stands for is
                                         right beside it, and announcing both
                                         reads the row twice. -->
                                    <Monogram :initials="row.initials" size="sm" decorative />

                                    <span class="season-show__player-name">
                                        <!-- The title carries the full name when
                                             the visible text is a nickname. -->
                                        <PlayerLink :player-id="row.player_id"
                                                    :title="row.full_name ?? undefined">{{ row.shown_name }}</PlayerLink>
                                    </span>
                                </span>

                                <!-- The tint says "this is you" to everyone who
                                     can see it. This says it to everyone else,
                                     and is the only reason the row is not
                                     carrying the information in colour alone. -->
                                <span v-if="row.is_self" class="u-visually-hidden">(you)</span>
                            </td>

                            <td class="season-show__meter-cell">
                                <Meter :value="row.meter_value" :max="row.meter_max"
                                       :label="row.meter_label" :value-label="row.meter_value_label" />
                            </td>

                            <!-- data-label feeds the mobile stat run through
                                 ::after -- "12 played", not "played 12",
                                 because a stat line reads value first. -->
                            <td class="table__num season-show__stat season-show__stat--played"
                                data-label="played">{{ row.played_label }}</td>
                            <td class="table__num season-show__stat season-show__stat--won"
                                data-label="won">{{ row.wins_label }}</td>

                            <td v-if="shows_venue_points"
                                class="table__num season-show__stat season-show__stat--venue"
                                data-label="venue points">{{ row.venue_points_label }}</td>

                            <!-- A mark when they are in, and nothing when they
                                 are not. The thresholds are published on this
                                 same page, so the standings can just answer the
                                 question they are asked.

                                 Neither branch is silent to a screen reader: an
                                 empty cell reads as "blank", which does not say
                                 whether the row failed or the column is not in
                                 use. -->
                            <td class="season-show__finale">
                                <template v-if="row.finale_state === 'none'">&mdash;</template>

                                <span v-else-if="row.finale_state === 'qualified'"
                                      class="season-show__qualified" title="Qualified">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="3" stroke-linecap="round"
                                         stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>

                                    <span class="u-visually-hidden">Qualified</span>
                                </span>

                                <span v-else class="u-visually-hidden">Not qualified</span>
                            </td>
                        </tr>
                    </Table>
                </Card>

                <div class="l-stack">
                    <Card title="Venues">
                        <div v-for="venue in venues" :key="venue.name" class="l-stack l-stack--tight">
                            <div class="l-cluster l-cluster--between">
                                <span>{{ venue.name }}</span>
                                <span class="u-mono u-muted">{{ venue.count_label }}</span>
                            </div>
                            <Meter :value="venue.count" :max="venue.max" :show-value="false"
                                   :label="venue.label" value-label="" />
                        </div>

                        <EmptyState v-if="venues.length === 0" title="No venues yet">
                            Venue usage appears once tournaments are scheduled.
                        </EmptyState>
                    </Card>

                    <Card title="Tournaments" flush>
                        <Link v-for="tournament in tournaments" :key="tournament.id"
                              class="season-show__tournament" :href="tournament.href">
                            <span>{{ tournament.name }}</span>
                            <span class="u-mono u-muted">{{ tournament.date_label }}</span>
                        </Link>

                        <EmptyState v-if="tournaments.length === 0" title="Nothing scheduled">
                            Add a tournament to start this season.
                        </EmptyState>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
