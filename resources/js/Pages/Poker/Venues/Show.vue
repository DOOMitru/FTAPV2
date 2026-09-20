<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Table from '../../../Components/Table.vue';
import Rank from '../../../Components/Rank.vue';
import Badge from '../../../Components/Badge.vue';
import Btn from '../../../Components/Btn.vue';
import Stat from '../../../Components/Stat.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import PlayerLink from '../../../Components/PlayerLink.vue';
import { useConfirm } from '../../../composables/useConfirm';

interface LeaderboardRow {
    place: number;
    /** Null when the account is gone; the name still shows, unlinked. */
    player_id: string | null;
    player_name: string;
    last_earned_label: string | null;
    total_label: string;
}

interface TournamentRow {
    id: string;
    name: string;
    href: string;
    date_label: string;
    season_label: string;
}

interface Props {
    venue: {
        id: string;
        name: string;
        description: string | null;
        address: string | null;
        /** Null when there is no address to map. */
        map_src: string | null;
        map_title: string;
        /** Carries U+2063 emphasis markers; the dialog renders them. */
        delete_confirm: string;
    };
    tournaments_label: string;
    venue_points_label: string;
    leaderboard: LeaderboardRow[];
    tournaments: TournamentRow[];
    has_more_tournaments: boolean;
    add_points_href: string;
}

const props = defineProps<Props>();

const { ask } = useConfirm();

const destroy = async (): Promise<void> => {
    if (await ask(props.venue.delete_confirm)) {
        router.delete(`/poker/venues/${props.venue.id}`);
    }
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" :title="venue.name">
                <template #actions>
                    <Btn variant="ghost" href="/poker/venues">Back</Btn>
                    <Btn variant="primary" :href="`/poker/venues/${venue.id}/edit`">Edit</Btn>

                    <!-- Deleting a venue is offered where it is read. An
                         administrator removing one has its takings and its
                         leaderboard in front of them, which a row in a list
                         cannot show -- and this page is admin-only already, so
                         the control needs no gate of its own. -->
                    <Btn variant="danger" type="button" @click="destroy">Delete</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <!-- Equal halves, and the body brings back the padding the flush
                 card gives up for the map. This was .l-sidebar inside a flush
                 card: 2fr 1fr, so the map took two thirds and the name rendered
                 hard against the card's top border. -->
            <Card flush>
                <div class="venue-show__split">
                    <div v-if="venue.map_src" class="map">
                        <iframe :title="venue.map_title" loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                :src="venue.map_src"></iframe>

                        <div class="map__pin">
                            <span class="p-contact__label">Venue Address</span>
                            <span class="p-contact__value">{{ venue.address }}</span>
                        </div>
                    </div>

                    <div class="venue-show__body">
                        <h2 class="venue-show__name">{{ venue.name }}</h2>

                        <p v-if="venue.description" class="venue-show__lede">{{ venue.description }}</p>

                        <!-- stat-rows: below 48rem these stop being tiles and
                             become labelled lines, the same treatment the season
                             page gives its figures. Not --boxed: they sit inside
                             a card, which already draws the edge. -->
                        <div class="venue-show__stats stat-rows">
                            <Stat label="Tournaments" :value="tournaments_label" />
                            <Stat label="Venue points" :value="venue_points_label" />
                        </div>
                    </div>
                </div>
            </Card>

            <!-- align-items: start, so an empty leaderboard does not stretch to
                 match a list of nineteen tournaments beside it. -->
            <div class="l-sidebar venue-show__panels">
                <Card title="Venue points leaderboard" flush>
                    <!-- The form opens with this venue already chosen, which is
                         the whole reason the action lives here rather than on a
                         listing. -->
                    <template #actions>
                        <Btn variant="primary" size="sm" :href="add_points_href">Add points</Btn>
                    </template>

                    <Table>
                        <template #head>
                            <th scope="col">Rank</th>
                            <th scope="col">Player</th>
                            <th scope="col" class="table__num">Total points</th>
                        </template>

                        <tr v-for="row in leaderboard" :key="row.player_name">
                            <td><Rank :place="row.place" /></td>

                            <td>
                                <div class="entry__title">
                                    <PlayerLink :player-id="row.player_id">{{ row.player_name }}</PlayerLink>
                                </div>

                                <div v-if="row.last_earned_label" class="entry__meta">
                                    <span>{{ row.last_earned_label }}</span>
                                </div>
                            </td>

                            <td class="table__num">{{ row.total_label }}</td>
                        </tr>

                        <tr v-if="leaderboard.length === 0">
                            <td colspan="3">
                                <EmptyState title="No venue points awarded here yet." />
                            </td>
                        </tr>
                    </Table>
                </Card>

                <Card title="Recent Tournaments" flush>
                    <template #actions>
                        <Badge>{{ tournaments_label }}</Badge>
                    </template>

                    <!-- entry--link, because the whole row is the link. Without
                         it every piece of text in the row -- title, date and
                         season -- carries the browser's default underline, and
                         ten rows read as thirty links rather than ten. Nothing
                         inside may be another link: NestedAnchorTest holds
                         that, and it is the fault that spilled 45 archive cards
                         into 135 grid children. -->
                    <Link v-for="tournament in tournaments" :key="tournament.id"
                          class="entry entry--link" :href="tournament.href">
                        <div class="entry__body">
                            <div class="entry__title">{{ tournament.name }}</div>

                            <div class="entry__meta">
                                <span>{{ tournament.date_label }}</span>
                                <span>{{ tournament.season_label }}</span>
                            </div>
                        </div>

                        <!-- The affordance the underlines were doing badly. A
                             mark that is always there, rather than a hover
                             state a touch screen never shows. -->
                        <svg class="entry__go" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </Link>

                    <EmptyState v-if="tournaments.length === 0" title="No tournaments held here yet." />

                    <p v-if="has_more_tournaments" class="card__note">Showing the 10 most recent</p>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
