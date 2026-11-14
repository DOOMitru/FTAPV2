<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    /**
     * No modifier by default, unlike the Blade twin.
     *
     * That component defaults to `neutral` and emits `badge--neutral`, which
     * the stylesheet does not declare -- a plain <x-badge> has been carrying a
     * phantom class. Nothing catches it: UnusedCssClassTest finds classes
     * DECLARED and unused, not classes USED and undeclared, which is the
     * opposite direction.
     *
     * The three below are every variant the CSS defines and every one any
     * caller passes.
     */
    variant?: 'accent' | 'open' | 'primary' | null;
}

const props = withDefaults(defineProps<Props>(), { variant: null });

// Written out rather than interpolated, so both guards can see the names.
const VARIANT: Record<string, string> = {
    accent: 'badge--accent',
    open: 'badge--open',
    primary: 'badge--primary',
};

const classes = computed(() =>
    ['badge', props.variant === null ? '' : VARIANT[props.variant]].filter(Boolean).join(' '));
</script>

<template>
    <span :class="classes"><slot /></span>
</template>
