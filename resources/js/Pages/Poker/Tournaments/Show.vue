<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Badge from '../../../Components/Badge.vue';
import Btn from '../../../Components/Btn.vue';
import Rank from '../../../Components/Rank.vue';
import Monogram from '../../../Components/Monogram.vue';
import PlayerLink from '../../../Components/PlayerLink.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Action from '../../../Components/Action.vue';
import PEvent, { type EventCard } from '../../../Components/PEvent.vue';
import RegisterDialog, { type Candidate } from './Partials/RegisterDialog.vue';
import { useConfirm } from '../../../composables/useConfirm';
import type { PageProps } from '../../../types/inertia';

interface StandingsRow {
    key: string;
    name: string;
    nickname: string | null;
    player_id: string | null;
    initials: string;
    /** Null while the player is still in. */
    place: number | null;
    points_label: string | null;
    can_eliminate: boolean;
    user_id: string | null;
    eliminate_confirm: string;
    /** True on exactly one row: the finish handed out most recently. */
    can_undo: boolean;
    /** Null while the player is still in. */
    undo_confirm: string | null;
    can_remove: boolean;
    registrant_id: string | null;
    remove_confirm: string;
}

interface Props {
    tournament: EventCard & {
        description: string | null;
        is_published: boolean;
        publish_confirm: string;
        unpublish_confirm: string;
        delete_confirm: string;
    };
    can_manage: boolean;
    can_publish: boolean;
    standings_title: string;
    registrants_label: string;
    standings: StandingsRow[];
    offer_publish: boolean;
    offer_publish_message: string;
    field_locked_note: string | null;
    register_candidates: Candidate[];
    registered_name: string | null;
}

const props = defineProps<Props>();

const { ask } = useConfirm();

const base = `/poker/tournaments/${props.tournament.id}`;

const publish = async (): Promise<void> => {
    // primary, not danger: publishing is the end of the tournament rather
    // than the undoing of one.
    if (await ask(props.tournament.publish_confirm, 'primary')) {
        router.post(`${base}/publish`);
    }
};

const unpublish = async (): Promise<void> => {
    // Reopening retracts what publishing said, so the confirmation says so
    // rather than only asking.
    if (await ask(props.tournament.unpublish_confirm)) {
        router.delete(`${base}/publish`);
    }
};

const destroy = async (): Promise<void> => {
    if (await ask(props.tournament.delete_confirm)) {
        router.delete(base);
    }
};

const eliminate = async (row: StandingsRow): Promise<void> => {
    if (await ask(row.eliminate_confirm)) {
        router.post(`${base}/eliminate`, { user_id: row.user_id }, { preserveScroll: true });
    }
};

/*
 * Taking back the last knockout.
 *
 * Danger, not primary, the way eliminating is: this throws a recorded finish
 * away, and the administrator should read it before it goes.
 *
 * The row names itself in the request. The server refuses if that player is no
 * longer the most recent elimination, which is what stops a page left open
 * while the night moved on from deleting a finish nobody was looking at.
 */
const undo = async (row: StandingsRow): Promise<void> => {
    if (row.undo_confirm && await ask(row.undo_confirm)) {
        router.delete(`${base}/eliminate`, {
            data: { user_id: row.user_id },
            preserveScroll: true,
        });
    }
};

const remove = async (row: StandingsRow): Promise<void> => {
    if (await ask(row.remove_confirm)) {
        router.delete(`/poker/registrants/${row.registrant_id}`, { preserveScroll: true });
    }
};

/*
 * Asked the moment the field is settled, and only on the request that flashed
 * the offer -- so it appears once, right after the last elimination, and never
 * again on a reload.
 *
 * A native <dialog> for the same reasons as the confirmation beside it: focus
 * moves in and is trapped, Escape closes, the page behind goes inert. Declining
 * posts nothing, because the offer is flashed: the next request simply will not
 * ask.
 */
const offer = ref<HTMLDialogElement | null>(null);

watch(
    () => [props.offer_publish, offer.value] as const,
    ([wanted, el]) => {
        if (wanted && el && ! el.open) {
            el.showModal();
        }
    },
    { immediate: true }
);

const registerDialog = ref<InstanceType<typeof RegisterDialog> | null>(null);

/*
 * Read for the DIALOG, which carries its own copy of the error. The shell
 * draws the flash for every other page and for this one too -- until the
 * dialog opens.
 *
 * computed, not a plain const: Inertia reuses a component when a visit renders
 * the same page, and registering a player lands back here, so a value read
 * once at setup would be the flash from before the registration.
 */
const flash = computed(() => usePage<PageProps>().props.flash);

/*
 * While this is true the shell holds its flash back. The dialog sits in the
 * top layer with a backdrop over the page and makes the rest of it inert, so a
 * banner announced behind it is announced to an administrator who can neither
 * see nor reach it -- and the dialog is already showing them the same words.
 */
const dialogOpen = ref(false);

const openRegister = (): void => {
    dialogOpen.value = true;
    registerDialog.value?.open();
};

const publishFromOffer = (): void => {
    offer.value?.close();
    router.post(`${base}/publish`);
};
</script>

<template>
    <AppLayout :show-flash="! dialogOpen">
        <template #header>
            <PageHeader eyebrow="League" :title="tournament.name">
                <template v-if="can_manage" #actions>
                    <Btn variant="ghost" href="/poker/tournaments">Back</Btn>

                    <!-- Publishing is the end of the tournament: it tells the
                         players who scored and locks the record. Offered only
                         when it can act, so it is never a click that fails. -->
                    <Btn v-if="can_publish" variant="primary" type="button"
                         @click="publish">Publish results</Btn>

                    <Btn v-if="tournament.is_published" variant="ghost" type="button"
                         @click="unpublish">Unpublish</Btn>

                    <!-- Edit steps down while Publish is on offer. Two primary
                         buttons side by side is two calls to action and
                         therefore none; when a tournament is finished, the
                         thing to do with it is publish it. -->
                    <Btn :variant="can_publish ? 'ghost' : 'primary'"
                         :href="`/poker/tournaments/${tournament.id}/edit`">Edit</Btn>

                    <!-- The confirmation names what goes with it. A row in a
                         list could say "this cannot be undone" and mean the
                         tournament; here it can say what else the cascade
                         takes. -->
                    <Btn variant="danger" type="button" @click="destroy">Delete</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <!-- The same card the events and home pages draw, so the three
                 cannot drift. No Details button -- this IS the details page --
                 and no map: the venue name is in the card and the address is
                 one click away. -->
            <PEvent :tournament="tournament" :details="false">
                <p v-if="tournament.description" class="u-muted">{{ tournament.description }}</p>
            </PEvent>

            <!-- One panel, not two. Final Standings and Registered Players
                 listed the same people in the same order and differed only in
                 what they put beside a name. A player appeared twice, and an
                 administrator read down one list to find a name and across to
                 the other to act on it.

                 The leading glyph carries the difference the two lists used to
                 carry between them: a player still in is their initials; once
                 they have a place, they are that place, medalled for the top
                 three. -->
            <Card :title="standings_title" flush class="tshow__players">
                <template #actions>
                    <Badge>{{ registrants_label }}</Badge>

                    <!-- On the list rather than in the page header, which
                         already carries Back, Publish, Unpublish and Edit.
                         This adds a player to this list, so it belongs on it.

                         Hidden once published for the same reason register()
                         refuses then: a published tournament's field is
                         settled.

                         The plus is doing real work. Without it this was a
                         small ghost button beside a count badge in a card
                         header, and it read as a second label rather than as
                         the control that adds a player -- it was missed
                         entirely. The words are clipped on a phone rather than
                         removed, so the button still reads as "Register
                         players" to a screen reader. -->
                    <Btn v-if="can_manage && ! tournament.is_published"
                         variant="ghost" size="sm" type="button"
                         class="btn--icon-lead tshow__register-btn" @click="openRegister">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>

                        <span class="tshow__register-label">Register players</span>
                    </Btn>
                </template>

                <div v-for="row in standings" :key="row.key" class="entry">
                    <Rank v-if="row.place !== null" :place="row.place" />
                    <Monogram v-else :initials="row.initials" decorative />

                    <div class="entry__body">
                        <div class="entry__title">
                            <PlayerLink :player-id="row.player_id">{{ row.name }}</PlayerLink>
                        </div>

                        <div v-if="row.nickname" class="entry__meta"><span>{{ row.nickname }}</span></div>
                    </div>

                    <div class="entry__actions">
                        <!-- Its own condition rather than a branch of the chain
                             below: an undoable row shows this AND its points
                             badge, the button first so the points stay at the
                             row's edge, aligned with every other row's. Only
                             one row in the tournament ever carries it -- the
                             finish handed out most recently -- so there is
                             never a choice of which to press. -->
                        <Btn v-if="row.can_undo" variant="ghost" size="sm" type="button"
                             @click="undo(row)">Undo</Btn>

                        <!-- Points only. The place is the glyph at the head of
                             the row, and printing it again here is the one
                             duplication the merge was supposed to remove. -->
                        <Badge v-if="row.points_label">{{ row.points_label }}</Badge>

                        <Btn v-else-if="row.can_eliminate" variant="ghost" size="sm" type="button"
                             @click="eliminate(row)">Eliminate</Btn>

                        <!-- Its own condition rather than a branch of the chain
                             above: what cannot be removed is somebody who has a
                             finish of their own, because their place is a
                             position in the field. -->
                        <form v-if="row.can_remove" @submit.prevent="remove(row)">
                            <Action icon="delete" label="Remove from tournament" danger />
                        </form>
                    </div>
                </div>

                <EmptyState v-if="standings.length === 0" title="No players registered yet." />

                <p v-if="field_locked_note" class="card__note">{{ field_locked_note }}</p>
            </Card>

            <RegisterDialog v-if="can_manage && ! tournament.is_published"
                            ref="registerDialog"
                            :tournament-id="tournament.id"
                            :candidates="register_candidates"
                            :registered-name="registered_name"
                            :error="flash.error" />

            <dialog v-if="can_publish" ref="offer" class="confirm publish-offer"
                    aria-labelledby="publish-offer-title">
                <p class="confirm__message" id="publish-offer-title">{{ offer_publish_message }}</p>

                <p class="publish-offer__note">
                    Every player who scored points is notified, and the tournament is locked.
                </p>

                <div class="confirm__actions">
                    <!-- Declining posts nothing: the offer is flashed, so the
                         next request simply will not ask. -->
                    <Btn variant="ghost" type="button" @click="offer?.close()">Not yet</Btn>
                    <Btn variant="primary" type="button" @click="publishFromOffer">Publish results</Btn>
                </div>
            </dialog>
        </div>
    </AppLayout>
</template>
