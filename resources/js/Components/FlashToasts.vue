<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Toast from './Toast.vue';
import type { PageProps } from '../types/inertia';

interface Props {
    /**
     * Whether to draw anything.
     *
     * AppLayout passes its own showFlash through. The tournament page turns it
     * off while its register dialog is open: the dialog is modal, so a toast
     * behind its backdrop is a message to somebody who can neither see nor
     * reach it, and the dialog is already showing them the same words.
     */
    show?: boolean;
}

const props = withDefaults(defineProps<Props>(), { show: true });

/*
 * computed, not a plain const.
 *
 * usePage().props.flash read once at setup is the flash that ARRIVED WITH THIS
 * COMPONENT, and this one lives in a layout -- it outlasts more visits than
 * any page does. FlashMessageParityTest holds it.
 */
const flash = computed(() => usePage<PageProps>().props.flash);

/*
 * Why a sequence number and not just the message.
 *
 * A toast dismisses itself after five seconds, so a repeat has to RE-ARM it.
 * Keying the toast on its own text does not: the points-structure form adds a
 * place and returns to itself, and saving the same figure twice flashes the
 * identical sentence -- same key, no remount, no second toast, and the person
 * who pressed Save twice is told once.
 *
 * Inertia replaces page.props wholesale on every visit, so the flash OBJECT is
 * new each time even when the string inside it is not. A shallow watch
 * compares by reference and therefore fires exactly once per visit; the number
 * it bumps goes into :key, which remounts the toast and restarts its timer.
 *
 * Verified in a browser -- three consecutive saves, three toasts -- because
 * nothing on the server can see a component fail to remount.
 */
const seq = ref(0);

watch(flash, () => { seq.value++; });
</script>

<template>
    <template v-if="props.show">
        <!-- Danger first, so a refusal is the one nearest the eye if an action
             ever manages to send both. -->
        <Toast v-if="flash.error" :key="`error-${seq}`" variant="danger" :message="flash.error" />
        <Toast v-if="flash.status" :key="`status-${seq}`" variant="success" :message="flash.status" />
    </template>
</template>
