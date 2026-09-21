<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Btn from '../../../../Components/Btn.vue';
import Emph from '../../../../Components/Emph.vue';

export interface Candidate {
    id: string;
    name: string;
    /** The name with a parenthesised nickname, where there is one. */
    label: string;
    /** From the full name, not the label: "W(" is not a monogram. */
    initials: string;
    email: string;
    /** How many tournaments they have entered. */
    played: number;
    played_label: string;
    registered: boolean;
    approved: boolean;
    /** Lowercased name, nickname and email in one string. */
    search: string;
}

interface Props {
    tournamentId: string;
    candidates: Candidate[];
    /** Who was registered by the last request, if anybody. */
    registeredName: string | null;
    /** A refusal from the last request. Carries emphasis markers. */
    error: string | null;
}

const props = defineProps<Props>();

const dialog = ref<HTMLDialogElement | null>(null);
const q = ref('');

/*
 * Two modes, and both matter.
 *
 * With no term the list leaves out anybody who cannot be picked -- already in
 * this tournament, or still waiting for approval. The list is capped at ten,
 * and a league's most frequent entrants sort to the top and are exactly the
 * people most likely to be entered already, so the default view was spending
 * its ten slots on rows that did nothing.
 *
 * With a term it returns everyone matching, pickable or not: that is when an
 * administrator is hunting one named person, and "they are already in" or
 * "they are waiting for approval" is the answer they came for.
 */
const matches = computed<Candidate[]>(() => {
    const term = q.value.trim().toLowerCase();

    return term === ''
        ? props.candidates.filter((p) => ! p.registered && p.approved)
        : props.candidates.filter((p) => p.search.includes(term));
});

const shown = computed<Candidate[]>(() => matches.value.slice(0, 10));

const open = (): void => {
    dialog.value?.showModal();
};

/*
 * The dialog stays open across registrations, and now does so by itself.
 *
 * In Blade each row posted a form and the page reloaded, so "stays open" was a
 * SERVER fact: register() flashed register_open and x-init reopened it on the
 * way back. The visit is client-side here and the component is reused, so the
 * dialog is never closed in the first place -- and the list, the counts and
 * the already-registered marks all come back correct because the props do.
 *
 * No confirmation on a row: the point of the dialog is to add several players
 * in a row, and asking on each defeats it. Registration is reversible from the
 * same page while no result exists, which is what makes that safe.
 */
const register = (player: Candidate): void => {
    router.post(
        `/tournaments/${props.tournamentId}/register`,
        { user_id: player.id },
        { preserveScroll: true, preserveState: true }
    );
};

defineExpose({ open });
</script>

<template>
    <!--
        A native <dialog> opened with showModal(): focus moves in and is
        trapped, Escape closes, the page behind goes inert, and it renders in
        the top layer.
    -->
    <dialog ref="dialog" class="register" aria-labelledby="register-dialog-title">
        <div class="register__head">
            <!-- Not "Register players for <tournament>": a league's tournament
                 names run to "The Main Event Season 4", which wrapped the
                 heading onto two lines and pushed it into the Done button. The
                 dialog sits on that tournament's own page. -->
            <h2 class="register__title" id="register-dialog-title">Register players</h2>

            <Btn variant="ghost" size="sm" type="button" @click="dialog?.close()">Done</Btn>
        </div>

        <!-- What just happened, said where the administrator is looking. The
             page-level alert is behind the backdrop while the dialog is open,
             so on its own it announced each registration to nobody -- and
             registering a dozen players in a row is exactly when you want to
             see that the last one landed.

             The name is set apart rather than left inside the sentence: after
             eight of these the sentence is wallpaper and the name is the only
             part being read. -->
        <p v-if="registeredName" class="register__flash register__flash--done">
            <span class="register__flash-name">{{ registeredName }}</span>
            has been registered.
        </p>

        <p v-if="error" class="register__flash register__flash--error">
            <Emph :message="error" />
        </p>

        <label class="u-visually-hidden" for="register-search">Search players</label>

        <input id="register-search" v-model="q" type="search"
               class="field__control register__search" placeholder="Name, nickname or email">

        <ul class="picker register__list">
            <li v-for="player in shown" :key="player.id" class="picker__item">
                <!-- Already in this tournament: named, so the administrator can
                     see why they are not on offer, and inert, because
                     register() refuses them. Absence would leave "already
                     entered" and "not approved" looking identical. -->
                <div v-if="player.registered" class="picker__btn picker__btn--inert" aria-disabled="true">
                    <span class="monogram monogram--sm picker__face" aria-hidden="true">{{ player.initials }}</span>
                    <span class="picker__name">{{ player.label }}</span>
                    <span class="picker__meta">Already registered for this tournament</span>
                </div>

                <!-- Approved is a different gate from entered, and so is the
                     remedy: this administrator can approve the account, and
                     telling them which gate is shut is the whole point of
                     listing a player they cannot pick. -->
                <div v-else-if="! player.approved" class="picker__btn picker__btn--inert" aria-disabled="true">
                    <span class="monogram monogram--sm picker__face" aria-hidden="true">{{ player.initials }}</span>
                    <span class="picker__name">{{ player.label }}</span>
                    <span class="picker__meta">Waiting for approval — approve the account first</span>
                </div>

                <button v-else type="button" class="picker__btn" @click="register(player)">
                    <span class="monogram monogram--sm picker__face" aria-hidden="true">{{ player.initials }}</span>
                    <span class="picker__name">{{ player.label }}</span>
                    <span class="picker__meta">{{ player.email }}</span>
                    <span class="picker__count">{{ player.played_label }}</span>
                </button>
            </li>
        </ul>

        <p v-if="matches.length === 0" class="picker__empty">No players match that search.</p>

        <!-- Only when the cap actually hides somebody. A standing note that ten
             are shown, on a list of four, is noise. -->
        <p v-if="matches.length > 10" class="register__note">
            <span>{{ matches.length }}</span>
            players match. Showing the ten who have entered the most tournaments — search to narrow it.
        </p>
    </dialog>
</template>
