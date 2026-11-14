<script setup lang="ts">
interface Props {
    /**
     * The letters, already reduced. Never a name to reduce here: initials()
     * is one definition in PHP, and its call site in monogram.blade.php says
     * why -- the register dialog builds a player list in the browser from
     * letters PHP computed, because two copies would be free to disagree.
     */
    initials: string;
    /** What a screen reader announces. Ignored when decorative. */
    label?: string;
    size?: 'md' | 'sm' | 'lg';
    /**
     * True whenever this sits beside an already-visible name, so the name is
     * not announced twice.
     */
    decorative?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Player',
    size: 'md',
    decorative: false,
});

const sizeClass = props.size === 'lg' ? ' monogram--lg' : (props.size === 'sm' ? ' monogram--sm' : '');
</script>

<template>
    <!--
        A span, not an image: the initials are text, so they scale with the
        page, restyle with the theme and cost no request.

        role/aria-label rather than alt, and aria-hidden when decorative --
        without one of the two this reads out as a stray letter pair beside
        every name.
    -->
    <span
        :class="`monogram${sizeClass}`"
        :aria-hidden="decorative ? 'true' : undefined"
        :role="decorative ? undefined : 'img'"
        :aria-label="decorative ? undefined : label"
    >{{ initials }}</span>
</template>
