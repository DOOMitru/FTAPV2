<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Alert from './Alert.vue';

interface Props {
    variant?: 'info' | 'success' | 'danger';
    /** The message, which may carry emphasis markers. <Alert> renders them. */
    message: string;
}

const props = withDefaults(defineProps<Props>(), { variant: 'info' });

const shown = ref(true);
let timer: ReturnType<typeof setTimeout> | null = null;

/*
 * Success goes; a refusal stays.
 *
 * A confirmation has done its job the moment it is read, and a page that
 * flashes on every save -- the points-structure form adds a place and returns
 * to itself -- would otherwise stack them up. A danger message is the one you
 * most need time with: "has already started, so you can no longer enter it" is
 * the sentence a timer is most likely to take away from somebody who looked
 * down at the table.
 *
 * Five seconds, and it is not a prop: a per-call-site duration is a decision
 * every future caller has to make again, and they would not all make it the
 * same way.
 */
const DISMISS_AFTER_MS = 5000;

onMounted(() => {
    if (props.variant !== 'danger') {
        timer = setTimeout(() => { shown.value = false; }, DISMISS_AFTER_MS);
    }
});

// The timer outlives the component otherwise: this unmounts on navigation, and
// a callback firing into a dead component is how a stray Vue warning starts.
onBeforeUnmount(() => {
    if (timer !== null) {
        clearTimeout(timer);
    }
});
</script>

<template>
    <!--
        A toast is an alert in a different place, so <Alert> stays in charge of
        the variant colours and the emphasis markers. This adds the elevation
        and the way out. The twin is toast.blade.php; the two agree about class
        hooks, and ComponentParityTest says so.

        :live="false" -- the alert announces nothing itself. It is inserted into
        one of the two live regions FlashToasts keeps permanently in the
        document, because a region created together with its message is
        unreliably announced.

        No positioning here either: the host and the regions belong to the
        shell, which is what makes them permanent.
    -->
    <Alert v-if="shown" class="toast" :variant="variant" :message="message" :live="false">
        <button type="button" class="toast__dismiss" @click="shown = false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>

            <!-- From content rather than aria-label, so it is translated with
                 the rest of the page -- as Action.vue does. -->
            <span class="u-visually-hidden">Dismiss</span>
        </button>
    </Alert>
</template>
