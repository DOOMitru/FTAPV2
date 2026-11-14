<script setup lang="ts">
import { computed, useSlots } from 'vue';

interface Props {
    caption?: string | null;
    /** Below 48rem the rows reflow into label/value blocks. */
    stacked?: boolean;
    /**
     * Below 48rem each row becomes a card whose cells are PLACED on a grid the
     * page defines, rather than stacked into labelled lines. Use it where a row
     * has a clear primary identifier and a handful of supporting facts --
     * stacking a five-column admin list prints five labelled lines per record
     * and buries the thing you came to find.
     */
    cards?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    caption: null,
    stacked: false,
    cards: false,
});

const slots = useSlots();

// Both modifiers belong on the <table>, not on the scroll wrapper, which is
// where a caller's own class lands.
const classes = computed(() => [
    'table',
    props.stacked ? 'table--stacked' : '',
    props.cards ? 'table--cards' : '',
].filter(Boolean).join(' '));
</script>

<template>
    <div class="table-scroll">
        <table :class="classes">
            <caption v-if="caption" class="table__caption">{{ caption }}</caption>

            <thead v-if="slots.head"><tr><slot name="head" /></tr></thead>

            <tbody><slot /></tbody>
        </table>
    </div>
</template>
