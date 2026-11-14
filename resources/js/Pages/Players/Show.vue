<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import Table from '../../Components/Table.vue';
import Rank from '../../Components/Rank.vue';
import Btn from '../../Components/Btn.vue';
import EmptyState from '../../Components/EmptyState.vue';
import SeasonPanel from '../../Components/SeasonPanel.vue';
import type { SeasonPanelProps } from '../../types/inertia';

interface Result {
    id: string;
    tournament_name: string;
    tournament_href: string;
    date_label: string;
    place: number;
    points_label: string;
}

interface Props extends SeasonPanelProps {
    eyebrow: string;
    title: string;
    is_self: boolean;
    results: Result[];
    has_more_results: boolean;
}

defineProps<Props>();
</script>

<template>
    <AppLayout>
        <template #header>
            <!-- The name is the title. Whose figures these are is the whole
                 question this page answers, so it is not an eyebrow above a
                 heading that would read "Player" on every one of them. -->
            <PageHeader :eyebrow="eyebrow" :title="title">
                <template v-if="is_self" #actions>
                    <Btn variant="ghost" size="sm" href="/dashboard">Your dashboard</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <!-- The same panel the dashboard draws, about somebody else. It
                 takes self so the empty-rank copy can speak about a third
                 party, and it leaves out venue points entirely when the
                 controller has withheld them. -->
            <SeasonPanel :season-name="season_name" :season-href="season_href"
                         :season="season" :self="self" />

            <Card title="Recent Results" flush>
                <Table>
                    <template #head>
                        <th scope="col">Tournament</th>
                        <th scope="col">Place</th>
                        <th scope="col" class="table__num">Points</th>
                    </template>

                    <!-- The whole row is the link, as on the dashboard: three
                         short cells offer a poor target otherwise, and three
                         separate anchors to one tournament would read as
                         fifteen links down a list of five. -->
                    <tr v-for="result in results" :key="result.id" class="table__row--link">
                        <td>
                            <div class="entry__title">
                                <Link class="table__link" :href="result.tournament_href">
                                    {{ result.tournament_name }}
                                </Link>
                            </div>
                            <div class="entry__meta">
                                <span>{{ result.date_label }}</span>
                            </div>
                        </td>

                        <td><Rank :place="result.place" /></td>

                        <td class="table__num">{{ result.points_label }}</td>
                    </tr>

                    <tr v-if="results.length === 0">
                        <td colspan="3">
                            <!-- About them, not to them: this page is read by
                                 somebody else more often than not. -->
                            <EmptyState title="No results recorded yet." />
                        </td>
                    </tr>
                </Table>

                <p v-if="has_more_results" class="card__note">Showing latest 5 career results</p>
            </Card>
        </div>
    </AppLayout>
</template>
