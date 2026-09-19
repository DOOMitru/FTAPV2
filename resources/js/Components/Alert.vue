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
}

withDefaults(defineProps<Props>(), { variant: 'info' });
</script>

<template>
    <div class="alert" :class="`alert--${variant}`" :role="variant === 'danger' ? 'alert' : 'status'">
        <Emph :message="message" />
    </div>
</template>
