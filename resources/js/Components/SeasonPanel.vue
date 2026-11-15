<script setup lang="ts">
import Card from './Card.vue';
import Btn from './Btn.vue';
import EmptyState from './EmptyState.vue';
import type { SeasonFigures } from '../types/inertia';

/**
 * One season's figures for one player.
 *
 * Shared by the dashboard and a player's profile, which show the same panel
 * about different people -- as a copy it would be a hundred lines free to
 * drift, and the venue-points rule is not something to enforce twice.
 */

interface Props {
    /** The card's title: the season's name, or a generic heading. */
    seasonName: string;
    /** Where "Full Season Stats" goes, or null when no season is running. */
    seasonHref?: string | null;
    /** Null when no season is running. */
    season?: SeasonFigures | null;
    /**
     * Whether the viewer is the player. Changes the COPY only -- a person
     * reading their own figures is told what to DO about an empty one, a
     * person reading somebody else's is told what is true. Never a permission
     * flag: what may be seen is decided in PHP before it reaches here.
     */
    self?: boolean;
}

withDefaults(defineProps<Props>(), {
    seasonHref: null,
    season: null,
    self: true,
});

// Written out rather than interpolated as `rank--${place}`: a composed class
// name is invisible to UnusedCssClassTest and to ComponentParityTest.
const PODIUM = [
    { medal: 'rank rank--1', label: 'Tournament Wins', key: 'first' },
    { medal: 'rank rank--2', label: '2nd Place Finishes', key: 'second' },
    { medal: 'rank rank--3', label: '3rd Place Finishes', key: 'third' },
] as const;
</script>

<template>
    <Card :title="seasonName" class="season">
        <template v-if="seasonHref" #actions>
            <Btn variant="ghost" size="sm" :href="seasonHref">Full Season Stats</Btn>
        </template>

        <EmptyState v-if="! season" title="No season is running.">
            Season figures appear here once a season is current.
        </EmptyState>

        <!--
            Three blocks, side by side when there is room and stacked when there
            is not: where you stand, what you won, and the totals behind it.

            They were one column down the left of a full-width card, which left
            two thirds of it empty and crowded the three groups into each other.
        -->
        <div v-else class="season__grid">
            <div class="season__block season__standing">
                <!--
                    Label above the figure, and the figure's reasoning pushed to
                    the far side -- the shape every other row in this panel has:
                    what it is on the left, the number on the right.
                -->
                <p class="season__rank-label">Season Rank</p>

                <div class="season__standing-row">
                    <span class="season__rank-value">{{ season.rank_label }}</span>

                    <div class="season__ratio">
                        <template v-if="! season.is_ranked">
                            <p class="season__ratio-main">No points yet</p>
                            <!--
                                Addressed to the reader on their own dashboard,
                                and about a third party on somebody else's page.
                            -->
                            <p class="season__ratio-sub">
                                {{ self
                                    ? 'Score points to be ranked.'
                                    : 'A player is ranked once they score points.' }}
                            </p>
                        </template>

                        <!--
                            The rank is the season's points, so the points are
                            its reasoning, and the field is what it is out of.
                        -->
                        <template v-else>
                            <p class="season__ratio-main">{{ season.totals_label }}</p>
                            <p class="season__ratio-sub">{{ season.ranked_label }}</p>
                        </template>
                    </div>
                </div>
            </div>

            <!--
                Read the same way as the totals beside them: what it is on the
                left, how many on the right.

                The badge carries no numeral. It had one, and the label beside
                it said the same thing again -- a gold 1 next to the words
                "Tournament Wins". What the badge is for is the colour, which is
                the one thing the words cannot say quickly.
            -->
            <dl class="season__block season__podium">
                <div v-for="row in PODIUM" :key="row.key" class="season__total">
                    <dt class="season__total-label">
                        <!--
                            .rank without content: the shape and the medal
                            colour come from the same rules the standings use,
                            so gold means here what it means there. aria-hidden
                            because the label says it in words.
                        -->
                        <span :class="row.medal" aria-hidden="true"></span>

                        {{ row.label }}
                    </dt>

                    <dd class="season__total-value">{{ season[row.key] }}</dd>
                </div>
            </dl>

            <dl class="season__block season__totals">
                <div class="season__total">
                    <dt class="season__total-label">Events Played</dt>
                    <dd class="season__total-value">{{ season.events_label }}</dd>
                </div>

                <div class="season__total">
                    <dt class="season__total-label">Season Points</dt>
                    <dd class="season__total-value">{{ season.points_label }}</dd>
                </div>

                <div v-if="season.venue_points_label !== null" class="season__total">
                    <dt class="season__total-label">Venue Points</dt>
                    <dd class="season__total-value">{{ season.venue_points_label }}</dd>
                </div>
            </dl>
        </div>
    </Card>
</template>
