<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

interface Props {
    variant?: 'primary' | 'danger' | 'ghost';
    size?: 'md' | 'sm';
    href?: string | null;
    type?: 'submit' | 'button' | null;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    size: 'md',
    href: null,
    type: null,
});

// ghost is the Cancel/dismiss variant: it must not submit a form by accident,
// so it defaults to type="button" while primary and danger -- the "do the
// thing" buttons -- default to submit. An explicit type from the caller wins.
const resolvedType = computed(() => props.type ?? (props.variant === 'ghost' ? 'button' : 'submit'));

const classes = computed(() => `btn btn--${props.variant}${props.size === 'sm' ? ' btn--sm' : ''}`);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes"><slot /></Link>
    <button v-else :type="resolvedType" :class="classes"><slot /></button>
</template>
