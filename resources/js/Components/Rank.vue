<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    place: number;
    /**
     * What the badge READS as, when that is not the bare place. The season
     * standings in rank order show "#2" here, because in that order the
     * position is the figure rather than a row number beside one.
     */
    label?: string | null;
}

const props = withDefaults(defineProps<Props>(), { label: null });

/*
 * Places 1-3 carry a medal; everything below is the quiet default. The modifier
 * is per-place rather than one shared --podium, because a podium whose three
 * seats are the same colour states no order.
 *
 * Written out rather than interpolated as `rank--${place}`: a composed class
 * name is invisible to both UnusedCssClassTest and ComponentParityTest, which
 * is how four notification tier classes went missing in Plan 1.
 */
const MEDAL: Record<number, string> = {
    1: 'rank--1',
    2: 'rank--2',
    3: 'rank--3',
};

const classes = computed(() => ['rank', MEDAL[props.place] ?? ''].filter(Boolean).join(' '));
</script>

<template>
    <span :class="classes">{{ label ?? place }}</span>
</template>
