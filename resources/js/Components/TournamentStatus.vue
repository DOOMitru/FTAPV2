<script setup lang="ts">
import Badge from './Badge.vue';
import type { TournamentStatus } from '../types/inertia';

/**
 * Where a tournament stands, as a badge: still to come, over and owing
 * results, or done.
 *
 * One component for the tournaments list and a venue's page, so the two cannot
 * word or colour a night differently. Which state a night is in is decided in
 * PHP, by PokerTournament::status(); this only says it.
 *
 *   upcoming  -- green (open), the colour the app gives an open state.
 *   awaiting  -- red (primary), the one that should catch the eye: results
 *                are owed.
 *   published -- plain, because a settled night needs nothing.
 */
defineProps<{ status: TournamentStatus }>();

// Written out rather than composed, so the variant names stay visible.
const BADGE = {
    upcoming: { variant: 'open', label: 'Upcoming' },
    awaiting: { variant: 'primary', label: 'Awaiting results' },
    published: { variant: null, label: 'Results published' },
} as const;
</script>

<template>
    <Badge :variant="BADGE[status].variant">{{ BADGE[status].label }}</Badge>
</template>
