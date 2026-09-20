<script setup lang="ts">
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import Table from '../../Components/Table.vue';
import Btn from '../../Components/Btn.vue';
import Badge from '../../Components/Badge.vue';
import Action from '../../Components/Action.vue';
import Alert from '../../Components/Alert.vue';
import Monogram from '../../Components/Monogram.vue';
import PlayerLink from '../../Components/PlayerLink.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import { useConfirm } from '../../composables/useConfirm';
import type { Paginated, PageProps } from '../../types/inertia';

interface Candidate {
    id: string;
    name: string;
    initials: string;
    email: string;
    registered_label: string;
    reject_confirm: string;
}

interface Player {
    id: string;
    name: string;
    initials: string;
    email: string;
    nickname: string;
    is_admin: boolean;
    role_label: string;
    delete_confirm: string;
}

interface Props {
    users: Paginated<Player>;
    /**
     * Not paginated, matching the controller: a queue that needs paging is a
     * queue nobody is working. Oldest first -- the person who has waited
     * longest is the one to deal with.
     */
    pending: Candidate[];
    search: string;
    invite_url: string | null;
}

const props = defineProps<Props>();
const flash = usePage<PageProps>().props.flash;
const { ask } = useConfirm();

const term = ref(props.search);

/*
 * The term lives in the URL, as it did when this was a plain GET form, so a
 * filtered list can still be linked, bookmarked and paged. What changed is
 * that the visit is client-side: `preserveState` keeps what is typed in the
 * box, and the paginator's links already carry the term because the controller
 * calls withQueryString().
 */
const search = (): void => {
    router.get('/users', { search: term.value }, { preserveState: true, replace: true });
};

const clear = (): void => {
    term.value = '';
    router.get('/users', {}, { preserveState: true, replace: true });
};

const approve = (candidate: Candidate): void => {
    router.patch(`/users/${candidate.id}/approve`, {}, { preserveScroll: true });
};

const reject = async (candidate: Candidate): Promise<void> => {
    if (await ask(candidate.reject_confirm)) {
        router.patch(`/users/${candidate.id}/reject`, {}, { preserveScroll: true });
    }
};

const destroy = async (player: Player): Promise<void> => {
    if (await ask(player.delete_confirm)) {
        router.delete(`/users/${player.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Setup" title="User Management">
                <template #actions>
                    <Btn variant="primary" href="/users/create">Register Player</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <Alert v-if="flash.status" variant="success" :message="flash.status" />

            <!--
                Shown as well as emailed. MAIL_MAILER is log, so a link that is
                only sent reaches nobody; this keeps Register Player from
                producing an account no one can get into.
            -->
            <Alert v-if="props.invite_url" variant="info"
                   message="Send this link to the player so they can set a password. It expires and can only be used once." />
            <p v-if="props.invite_url" class="u-mono">{{ props.invite_url }}</p>

            <!--
                Rendered only when non-empty. A heading over an empty table is
                furniture: it costs attention on every visit and says nothing.
            -->
            <Card v-if="props.pending.length > 0" title="Awaiting approval" flush>
                <Table stacked>
                    <template #head>
                        <th scope="col"><span class="u-visually-hidden">Initials</span></th>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Registered</th>
                        <th scope="col" class="table__actions">Actions</th>
                    </template>

                    <tr v-for="candidate in props.pending" :key="candidate.id">
                        <!--
                            The table of approved users below has had a photo
                            column all along. Same page, same entity, and this
                            is the table where knowing who you are admitting
                            matters most.
                        -->
                        <td class="table__thumb">
                            <Monogram :initials="candidate.initials" size="sm" decorative />
                        </td>

                        <td data-label="Name">
                            <PlayerLink :player-id="candidate.id">{{ candidate.name }}</PlayerLink>
                        </td>

                        <td data-label="Email">{{ candidate.email }}</td>
                        <td data-label="Registered">{{ candidate.registered_label }}</td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <Action icon="view" label="View" :href="`/users/${candidate.id}`" />

                                <form @submit.prevent="approve(candidate)">
                                    <Action icon="approve" label="Approve" />
                                </form>

                                <form @submit.prevent="reject(candidate)">
                                    <Action icon="reject" label="Reject" danger />
                                </form>
                            </div>
                        </td>
                    </tr>
                </Table>
            </Card>

            <!--
                The term lives in the URL, so a filtered list can be linked,
                bookmarked and paged. Above the table rather than in the card
                header, because the header is a non-wrapping flex row and a
                16rem control plus two buttons overruns it on a narrow screen.
            -->
            <form class="search" @submit.prevent="search">
                <label class="u-visually-hidden" for="search">Search users</label>

                <input id="search" v-model="term" name="search" type="search"
                       class="field__control field__control--inline"
                       placeholder="Name, nickname or email">

                <!--
                    Icon-only, so each carries its name in a visually-hidden
                    span: the accessible name then comes from content. title
                    gives a sighted mouse user the same word on hover.
                -->
                <Btn variant="ghost" type="submit" class="btn--icon" title="Search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>

                    <span class="u-visually-hidden">Search</span>
                </Btn>

                <!--
                    Only when there is something to clear. A permanent Clear on
                    an unfiltered list is a button that does nothing.
                -->
                <Btn v-if="props.search !== ''" variant="ghost" type="button"
                     class="btn--icon" title="Clear search" @click="clear">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>

                    <span class="u-visually-hidden">Clear search</span>
                </Btn>
            </form>

            <Card flush>
                <Table stacked>
                    <template #head>
                        <th scope="col"><span class="u-visually-hidden">Initials</span></th>
                        <th scope="col">Name</th>
                        <th scope="col">Nickname</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col" class="table__actions">Actions</th>
                    </template>

                    <tr v-for="user in props.users.data" :key="user.id">
                        <td class="table__thumb">
                            <Monogram :initials="user.initials" size="sm" decorative />
                        </td>

                        <!--
                            The name goes to the figures; the View action beside
                            it goes to the account. Two different questions about
                            the same person, and the action column is busy enough
                            without a fourth icon for one of them.
                        -->
                        <td data-label="Name">
                            <PlayerLink :player-id="user.id">{{ user.name }}</PlayerLink>
                        </td>

                        <td data-label="Nickname">{{ user.nickname }}</td>
                        <td data-label="Email">{{ user.email }}</td>

                        <td data-label="Role">
                            <Badge :variant="user.is_admin ? 'primary' : null">{{ user.role_label }}</Badge>
                        </td>

                        <td class="table__actions">
                            <div class="l-cluster l-cluster--end">
                                <Action icon="view" label="View" :href="`/users/${user.id}`" />
                                <Action icon="edit" label="Edit" :href="`/users/${user.id}/edit`" />

                                <form @submit.prevent="destroy(user)">
                                    <Action icon="delete" label="Delete" danger />
                                </form>
                            </div>
                        </td>
                    </tr>

                    <!--
                        An empty state, not a blank table. This page was the
                        only index in the app with none at all.
                    -->
                    <tr v-if="props.users.data.length === 0">
                        <td colspan="6">
                            <EmptyState v-if="props.search !== ''"
                                        :title="`No users match “${props.search}”.`">
                                Searches look at first and last name, nickname and email.
                            </EmptyState>

                            <EmptyState v-else title="No users found." />
                        </td>
                    </tr>
                </Table>

                <div v-if="props.users.status_label" class="card__pager">
                    <Pagination :links="props.users.links" :status-label="props.users.status_label" />
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
