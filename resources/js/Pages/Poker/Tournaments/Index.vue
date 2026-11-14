<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Table from '../../../Components/Table.vue';
import Btn from '../../../Components/Btn.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import type { Paginated } from '../../../types/inertia';

interface Tournament {
    id: string;
    name: string;
    venue_name: string;
    start_label: string;
}

interface Props {
    tournaments: Paginated<Tournament>;
    /** Null when no season is running -- the two empty states turn on this. */
    season_name: string | null;
    can_create: boolean;
}

const props = defineProps<Props>();
</script>

<template>
    <AppLayout>
        <template #header>
            <!--
                The season is the eyebrow, because the list is one season's
                nights and a page headed "Poker Tournaments" over them would
                not say which.
            -->
            <PageHeader :eyebrow="props.season_name ?? 'League'" title="Poker Tournaments">
                <template v-if="props.can_create" #actions>
                    <Btn variant="primary" href="/poker/tournaments/create">Schedule Tournament</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <Card flush>
                <Table cards class="tournaments-index__table">
                    <template #head>
                        <th scope="col">Name</th>
                        <th scope="col">Venue</th>
                        <th scope="col" class="table__num">Start Time</th>
                    </template>

                    <!--
                        The whole row is the link, and the only control on it.
                        tournaments.show is open to anyone signed in -- it is
                        where a player registers -- and editing or deleting a
                        tournament is offered there, behind the admin gate the
                        page already applies.

                        There is no Season column: the list is scoped to one
                        season, so every row would carry the same value.
                    -->
                    <tr v-for="tournament in props.tournaments.data" :key="tournament.id" class="table__row--link">
                        <td class="tournaments-index__name">
                            <Link class="table__link" :href="`/tournaments/${tournament.id}`">{{ tournament.name }}</Link>
                        </td>

                        <td class="tournaments-index__venue">{{ tournament.venue_name }}</td>

                        <!--
                            table__num: right-aligned and tabular, so a column
                            of dates lines up digit under digit. The phone
                            layout puts it back to the start edge -- a card
                            places its cells rather than columning them.
                        -->
                        <td class="table__num tournaments-index__start">{{ tournament.start_label }}</td>
                    </tr>

                    <tr v-if="props.tournaments.data.length === 0">
                        <td colspan="3">
                            <!--
                                Two different nothings, and an administrator can
                                act on only one of them. "No tournaments" on a
                                league with years of history reads as a fault;
                                the season being unset is the actual state and
                                is fixable.
                            -->
                            <EmptyState v-if="props.season_name"
                                        :title="`No tournaments in ${props.season_name} yet.`">
                                Tournaments scheduled in this season appear here.
                            </EmptyState>

                            <EmptyState v-else title="No season is running.">
                                This list shows the current season. Mark a season as current to see its tournaments here.
                            </EmptyState>
                        </td>
                    </tr>
                </Table>

                <div v-if="props.tournaments.status_label" class="card__pager">
                    <Pagination :links="props.tournaments.links" :status-label="props.tournaments.status_label" />
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
