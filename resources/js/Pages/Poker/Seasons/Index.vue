<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Table from '../../../Components/Table.vue';
import Badge from '../../../Components/Badge.vue';
import Btn from '../../../Components/Btn.vue';
import Alert from '../../../Components/Alert.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import type { PageProps, Paginated } from '../../../types/inertia';

interface Season {
    id: string;
    name: string;
    is_current: boolean;
    start_label: string;
    end_label: string;
}

interface Props {
    seasons: Paginated<Season>;
    can_create: boolean;
}

const props = defineProps<Props>();

// "Season X created." and the rest of what the season controllers flash.
// Shared by the middleware, so the page declares no prop for it.
const flash = usePage<PageProps>().props.flash;
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" title="Poker Seasons">
                <template v-if="props.can_create" #actions>
                    <Btn variant="primary" href="/poker/seasons/create">Create Season</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <Alert v-if="flash.status" variant="success" :message="flash.status" />

            <Card flush>
                <!--
                    The mobile layout is CSS only: same cells, reflowed by
                    _seasons-index.css below 48rem. Start and end are two
                    columns holding one fact -- a season's span -- so on a phone
                    they read as the range they are.
                -->
                <Table cards class="seasons-index__table">
                    <template #head>
                        <th scope="col">Name</th>
                        <th scope="col">Current</th>
                        <!--
                            table__num: right-aligned and tabular, so the two
                            date columns line up digit under digit. The phone
                            layout is unaffected -- its cells are inline, and
                            text-align does not reach an inline box.
                        -->
                        <th scope="col" class="table__num">Start Date</th>
                        <th scope="col" class="table__num">End Date</th>
                    </template>

                    <!--
                        The whole row is the link, and the only control on it.
                        Editing and deleting a season are offered on the
                        season's own page: an administrator changing one is
                        reading it first, and a row of three icons beside every
                        name is a column of decisions on a page whose job is to
                        list.
                    -->
                    <tr v-for="season in props.seasons.data" :key="season.id" class="table__row--link">
                        <td class="seasons-index__name">
                            <Link class="table__link" :href="`/seasons/${season.id}`">{{ season.name }}</Link>
                        </td>

                        <td class="seasons-index__current">
                            <Badge v-if="season.is_current" variant="open">Current</Badge>
                        </td>

                        <td class="table__num seasons-index__start">{{ season.start_label }}</td>
                        <td class="table__num seasons-index__end">{{ season.end_label }}</td>
                    </tr>

                    <tr v-if="props.seasons.data.length === 0">
                        <td colspan="4"><EmptyState title="No seasons found." /></td>
                    </tr>
                </Table>

                <div v-if="props.seasons.status_label" class="card__pager">
                    <Pagination :links="props.seasons.links" :status-label="props.seasons.status_label" />
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
