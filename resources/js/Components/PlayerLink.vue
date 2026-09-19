<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    /**
     * The account's id, or null when there is none.
     *
     * user_id is nullable with nullOnDelete on results, registrants and venue
     * points, so a departed player leaves a player_name string and nothing
     * behind it -- and a link to a user that no longer exists is a 404 with
     * somebody's name on it. Every call site would otherwise have to remember
     * that itself, which is the sort of thing remembered in nine places out of
     * ten.
     *
     * An id rather than a user object: the page sends what it has, and the only
     * thing this needs is whether there is an account and where it lives.
     */
    playerId?: string | null;
}

withDefaults(defineProps<Props>(), { playerId: null });
</script>

<template>
    <!-- Literal path: Ziggy is not installed, so there is no route() here. -->
    <Link v-if="playerId" class="player-link" :href="`/players/${playerId}`"><slot /></Link>
    <span v-else><slot /></span>
</template>
