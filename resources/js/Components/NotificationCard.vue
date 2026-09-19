<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Action from './Action.vue';
import type { Notification } from '../types/inertia';

interface Props {
    notification: Notification;
}

const props = defineProps<Props>();

// The tier arrives semantic -- "gold", not "notification--gold" -- so the rule
// about which places take medals lives once in PHP and the stylesheet stays
// this template's own business. See spec §5a.
//
// Written out rather than interpolated as `notification--${tier}`. Two reasons:
// a tier that never matches would otherwise compose a class that does not
// exist and fail silently, and a composed name is invisible to both
// UnusedCssClassTest and ComponentParityTest -- which reported these four as
// missing from this file, correctly, when they were interpolated.
const TIER_CLASS: Record<Notification['tier'], string> = {
    gold: 'notification--gold',
    silver: 'notification--silver',
    bronze: 'notification--bronze',
    scored: 'notification--scored',
};

const classes = computed(() => [
    'notification',
    TIER_CLASS[props.notification.tier],
    props.notification.is_read ? '' : 'notification--unread',
].filter(Boolean).join(' '));

const mark = useForm({});

// One route for both directions; the label says which way this click goes,
// because the row already knows where it stands.
const toggleRead = (): void => {
    mark.patch(`/notifications/${props.notification.id}`, { preserveScroll: true });
};

/*
 * Deleting is ASKED FOR, not done.
 *
 * The Blade card carries data-confirm and confirm.ts puts the question --
 * carrying the emphasised tournament name -- in front of the person first. A
 * first draft of this component called remove.delete() straight from the
 * button, which quietly turned a confirmed destructive action into an
 * unconfirmed one.
 *
 * The dialog is Vue's in Task 4c, so this emits the intent and the shell
 * decides. notification.delete_confirm is the sentence to ask, already
 * carrying its U+2063 emphasis markers from PHP.
 */
const emit = defineEmits<{ requestDelete: [notification: Notification] }>();
</script>

<template>
    <article :class="classes">
        <span class="notification__place">{{ notification.place_label }}</span>

        <div class="notification__body">
            <p class="notification__title">{{ notification.title }}</p>
            <p class="notification__meta">{{ notification.meta }}</p>
        </div>

        <div class="notification__actions">
            <form @submit.prevent="toggleRead">
                <Action :icon="notification.is_read ? 'undo' : 'approve'"
                        :label="notification.is_read ? 'Mark as unread' : 'Mark as read'" />
            </form>

            <!--
                Only once read. Deleting something you have not looked at is how
                a player loses a result they never saw, and the controller
                refuses it too -- this hides a control that would fail rather
                than inventing a second rule.
            -->
            <form v-if="notification.is_read" @submit.prevent="emit('requestDelete', notification)">
                <Action icon="delete" label="Delete notification" danger />
            </form>
        </div>
    </article>
</template>
