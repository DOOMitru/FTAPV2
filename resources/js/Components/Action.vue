<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

type Icon = 'view' | 'stats' | 'edit' | 'delete' | 'approve' | 'reject' | 'undo';

interface Props {
    icon: Icon;
    /**
     * Not decoration. An icon-only control has no accessible name of its own,
     * so every one of these carries the word in a visually-hidden span -- from
     * content, not aria-label, so it is translated with the rest of the page --
     * and repeats it in title for a sighted mouse user.
     */
    label: string;
    href?: string | null;
    danger?: boolean;
}

const props = withDefaults(defineProps<Props>(), { href: null, danger: false });

const classes = `action${props.danger ? ' action--danger' : ''}`;
</script>

<template>
    <!--
        An <a> when given an href, a submit button otherwise. The surrounding
        form, its CSRF token and its confirmation stay at the call site: those
        differ per action and this component has no business knowing them.
    -->
    <component :is="href ? Link : 'button'" v-bind="href ? { href } : { type: 'submit' }"
               :class="classes" :title="label">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <template v-if="icon === 'view'">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </template>
            <template v-else-if="icon === 'stats'">
                <line x1="18" y1="20" x2="18" y2="10"/>
                <line x1="12" y1="20" x2="12" y2="4"/>
                <line x1="6" y1="20" x2="6" y2="14"/>
            </template>
            <template v-else-if="icon === 'edit'">
                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>
            </template>
            <template v-else-if="icon === 'delete'">
                <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                <line x1="10" y1="11" x2="10" y2="17"/>
                <line x1="14" y1="11" x2="14" y2="17"/>
            </template>
            <template v-else-if="icon === 'approve'">
                <path d="M20 6L9 17l-5-5"/>
            </template>
            <template v-else-if="icon === 'reject'">
                <path d="M18 6L6 18M6 6l12 12"/>
            </template>
            <template v-else-if="icon === 'undo'">
                <path d="M3 7v6h6"/>
                <path d="M3 13a9 9 0 1 0 3-7.7L3 8"/>
            </template>
        </svg>

        <span class="u-visually-hidden">{{ label }}</span>
    </component>
</template>
