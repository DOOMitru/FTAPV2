<script setup lang="ts">
import Emph from './Emph.vue';

interface Props {
    variant?: 'info' | 'success' | 'danger';
    /**
     * The message, which may carry emphasis markers.
     *
     * A prop rather than a slot, unlike the Blade component. Blade can run
     * emph_split() over its rendered slot because the slot arrives as an
     * escaped string; Vue slots are compiled nodes, and reaching into them to
     * re-render text would be exactly the parsing step this marker system
     * exists to avoid. So the message comes in as a value and <Emph> renders
     * it -- the guarantee is the same, expressed in the direction Vue works.
     */
    message: string;
    /**
     * Whether this alert announces itself.
     *
     * True for an alert sitting in the page, which is its own live region.
     * FALSE inside a toast: the toast is inserted into a region that was
     * already in the document, because a live region created together with its
     * message is unreliably announced -- and two nested regions risk saying
     * the same sentence twice.
     */
    live?: boolean;
}

withDefaults(defineProps<Props>(), { variant: 'info', live: true });
</script>

<template>
    <div class="alert" :class="`alert--${variant}`"
         :role="live ? (variant === 'danger' ? 'alert' : 'status') : undefined">
        <Emph :message="message" />

        <!--
            An optional trailing control, which only Toast uses: a dismiss
            button has to sit INSIDE the alert to share its surface, and an
            alert with no way out passes nothing here. The twin takes the same
            thing as a named `control` slot -- Blade's default slot is already
            the message.
        -->
        <slot />
    </div>
</template>
