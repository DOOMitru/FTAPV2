<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import PageHeader from '../Components/PageHeader.vue';
import Card from '../Components/Card.vue';
import Table from '../Components/Table.vue';
import Rank from '../Components/Rank.vue';
import Badge from '../Components/Badge.vue';
import Btn from '../Components/Btn.vue';
import EmptyState from '../Components/EmptyState.vue';
import SeasonPanel from '../Components/SeasonPanel.vue';
import { useConfirm } from '../composables/useConfirm';
import type { SeasonPanelProps } from '../types/inertia';

interface Tournament {
    id: string;
    name: string;
    month_label: string;
    day_label: string;
    time_label: string;
    venue_label: string;
    href: string;
    is_registered: boolean;
    can_unregister: boolean;
    /** Carries U+2063 emphasis markers; the dialog renders them. */
    unregister_confirm: string;
    can_register: boolean;
}

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
    upcoming: Tournament[];
    upcoming_note: string | null;
    results: Result[];
    has_more_results: boolean;
}

defineProps<Props>();

const { ask } = useConfirm();

/*
 * preserveScroll on both: the list sits below the season panel, and on a phone
 * a registration that jumps the page back to the top loses the reader's place
 * in the very list they were working down.
 */
const register = (tournament: Tournament): void => {
    router.post(`/tournaments/${tournament.id}/register`, {}, { preserveScroll: true });
};

const unregister = async (tournament: Tournament): Promise<void> => {
    if (await ask(tournament.unregister_confirm)) {
        // DELETE /unregister, not DELETE /register. The two actions are two
        // URLs rather than one under two verbs, which the route list says and
        // the obvious guess does not.
        router.delete(`/tournaments/${tournament.id}/unregister`, { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader :eyebrow="eyebrow" title="Dashboard" />
        </template>

        <div class="l-container l-stack">
            <!-- One panel, not seven tiles. Seven figures about one season, and
                 three of them -- firsts, seconds, thirds -- are one idea. Split
                 across a four-wide grid they were three cards each holding a
                 small integer, usually 0, with an orphan row underneath.

                 Rank leads because it is the question ("how am I doing"), and it
                 shows the division it comes from: it is points per event
                 entered, so a bare #4 is a number the reader cannot check. -->
            <SeasonPanel :season-name="season_name" :season-href="season_href"
                         :season="season" :self="self" />

            <!-- One column. The side column held Active Season alone, and that
                 is folded into the panel above.

                 No actions in the header: it carried a count of every scheduled
                 event and a button to the public schedule, and the note under
                 the list already says how many there are and links to the same
                 page. -->
            <Card title="Upcoming Tournaments" flush>
                <div v-for="tournament in upcoming" :key="tournament.id" class="entry">
                    <span class="date-chip">
                        <span class="date-chip__month">{{ tournament.month_label }}</span>
                        <span class="date-chip__day">{{ tournament.day_label }}</span>
                    </span>

                    <div class="entry__body">
                        <div class="entry__title">{{ tournament.name }}</div>

                        <div class="entry__meta">
                            <!-- The time play STARTS. It read the registration
                                 deadline until that went; with the deadline
                                 gone, a "Closes" label over the start time is
                                 one that actively misleads. -->
                            <span>{{ tournament.time_label }}</span>
                            <span>{{ tournament.venue_label }}</span>
                        </div>
                    </div>

                    <div class="entry__actions">
                        <template v-if="tournament.is_registered">
                            <Badge variant="open">Registered</Badge>

                            <!-- The badge says where you stand; the button is
                                 how you change it. Saying the first without
                                 offering the second left the details page as
                                 the only way out of a tournament.

                                 Ghost, not danger: leaving is not what this row
                                 is asking you to do. -->
                            <Btn v-if="tournament.can_unregister" variant="ghost" size="sm"
                                 type="button" @click="unregister(tournament)">Unregister</Btn>
                        </template>

                        <template v-else>
                            <Btn v-if="tournament.can_register" variant="primary" size="sm"
                                 type="button" @click="register(tournament)">Register</Btn>

                            <Badge v-else>Awaiting approval</Badge>
                        </template>

                        <Btn variant="ghost" size="sm" :href="tournament.href">View</Btn>
                    </div>
                </div>

                <EmptyState v-if="upcoming.length === 0" title="No upcoming tournaments scheduled." />

                <!-- Two parts in their own elements, so they can take a line
                     each when there is not room for both. The spacing between
                     them is the flex gap rather than a trailing space inside a
                     translatable string. -->
                <p v-if="upcoming_note" class="card__note card__note--split">
                    <span>{{ upcoming_note }}</span>

                    <a class="link" href="/events">See the full schedule</a>
                </p>
            </Card>

            <Card title="Recent Results" flush>
                <Table>
                    <template #head>
                        <th scope="col">Tournament</th>
                        <th scope="col">Place</th>
                        <th scope="col" class="table__num">Points</th>
                    </template>

                    <!-- The whole row is the link, not the name in it. One
                         anchor stretched over the row by .table__link, so the
                         place and the points are part of the target too -- a
                         row of three short cells offers a poor one otherwise,
                         especially on a phone.

                         One anchor also because three would be three: the name,
                         the date and the tournament are the same destination,
                         and a list of five rows would read as fifteen links. -->
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
                            <EmptyState title="No result data recorded yet." />
                        </td>
                    </tr>
                </Table>

                <p v-if="has_more_results" class="card__note">Showing latest 5 career results</p>
            </Card>
        </div>
    </AppLayout>
</template>
