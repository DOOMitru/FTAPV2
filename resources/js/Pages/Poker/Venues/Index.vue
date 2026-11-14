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

interface Venue {
    id: string;
    name: string;
    description: string | null;
}

interface Props {
    venues: Paginated<Venue>;
    /**
     * Whether a row is a link. The venue detail page is admin-only, so a row a
     * player could follow leads to a 403 -- the same reason this table gave a
     * player no View Stats control when it had one.
     */
    rows_link: boolean;
    can_create: boolean;
}

const props = defineProps<Props>();
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" title="Venues">
                <template v-if="props.can_create" #actions>
                    <Btn variant="primary" href="/poker/venues/create">Add Venue</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <Card flush>
                <!--
                    No `cards` prop, and the wrapper class is venues-table.
                    This list does not use the shared card layout: _venues.css
                    gives it its own narrow treatment, keyed on that class.
                -->
                <Table class="venues-table">
                    <template #head>
                        <th scope="col">Name</th>
                        <th scope="col">Description</th>
                    </template>

                    <!--
                        The row carries the link and nothing else: editing and
                        deleting a venue are offered on the venue's own page,
                        where the takings and the leaderboard are in front of
                        whoever is about to change it.
                    -->
                    <tr v-for="venue in props.venues.data" :key="venue.id"
                        class="venue-row" :class="{ 'table__row--link': props.rows_link }">
                        <td class="venue-row__name">
                            <Link v-if="props.rows_link" class="table__link"
                                  :href="`/poker/venues/${venue.id}`">{{ venue.name }}</Link>
                            <template v-else>{{ venue.name }}</template>
                        </td>

                        <!-- No whitespace inside: the mobile rule hides this cell with
                             :empty, and a stray newline would make it a cell with
                             content as far as CSS is concerned. -->
                        <td class="venue-row__desc">{{ venue.description }}</td>
                    </tr>

                    <tr v-if="props.venues.data.length === 0">
                        <td colspan="2"><EmptyState title="No venues found." /></td>
                    </tr>
                </Table>

                <div v-if="props.venues.status_label" class="card__pager">
                    <Pagination :links="props.venues.links" :status-label="props.venues.status_label" />
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
