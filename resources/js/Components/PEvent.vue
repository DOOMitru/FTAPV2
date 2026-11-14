<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import Badge from './Badge.vue';
import Btn from './Btn.vue';
import Dropdown from './Dropdown.vue';
import DropdownLink from './DropdownLink.vue';
import { useConfirm } from '../composables/useConfirm';

/**
 * One upcoming-event card, as the tournament details page draws it.
 *
 * The Blade twin is components/p-event.blade.php, which the events page and
 * the home page still render -- both public, both staying Blade, so this card
 * exists twice for good rather than for the length of the conversion.
 * ComponentParityTest pairs the two by name and holds them to the same class
 * hooks.
 *
 * Every value arrives finished. The Blade computes eleven of them in the
 * template from a model; here they are props, because a card that formats a
 * date is a second definition of how this app formats dates (spec §5a).
 */
export interface EventCard {
    id: string;
    name: string;
    season_label: string;
    month_label: string;
    day_label: string;
    weekday_label: string;
    /** "Sat, Sep 20" -- the date line drawn where the calendar leaf is not. */
    date_label: string;
    time_label: string;
    /** "Saturday 20 September 2026, 7:00 PM", for a screen reader. */
    full_date_label: string;
    venue_label: string;
    /** Null unless a map was asked for AND the venue has an address. */
    map_src: string | null;
    map_title: string;
    address: string | null;
    href: string;
    season_href: string;
    /** Admin only: poker.venues.show is inside the admin-only prefix. */
    venue_href: string | null;
    is_registered: boolean;
    can_register: boolean;
    can_unregister: boolean;
    awaiting_approval: boolean;
    /** Carries U+2063 emphasis markers; the dialog renders them. */
    unregister_confirm: string;
}

interface Props {
    tournament: EventCard;
    /** The details page turns this off: it IS the details page. */
    details?: boolean;
}

const props = withDefaults(defineProps<Props>(), { details: true });

const { ask } = useConfirm();

/*
 * router calls, not forms. The Blade card posts both, and a Vue card cannot --
 * and the two are not one URL under two verbs: POST .../register and
 * DELETE .../unregister. Dashboard.vue calls the same pair.
 */
const register = (): void => {
    router.post(`/tournaments/${props.tournament.id}/register`, {}, { preserveScroll: true });
};

const unregister = async (): Promise<void> => {
    if (await ask(props.tournament.unregister_confirm)) {
        router.delete(`/tournaments/${props.tournament.id}/unregister`, { preserveScroll: true });
    }
};
</script>

<template>
    <!--
        p-event--mapless keys on the rendered map rather than on the prop: the
        card's min-height exists for the map, and a venue with no address has
        never drawn one.
    -->
    <article class="p-event p-raised" :class="{ 'p-event--mapless': ! tournament.map_src }">
        <!--
            The map is the public card's opening image. The details page turns
            it off: that page is reached from inside the dashboard by people who
            already know where the league plays, and a third of the card spent
            on an embedded map pushes the panels that page exists for below the
            fold.
        -->
        <div v-if="tournament.map_src" class="map">
            <iframe :title="tournament.map_title" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade" :src="tournament.map_src"></iframe>

            <div class="map__pin">
                <span class="p-icon-tile">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>

                <div>
                    <span class="p-contact__label">Venue Address</span>
                    <span class="p-contact__value">{{ tournament.address }}</span>
                </div>
            </div>
        </div>

        <div class="p-event__body">
            <!--
                Every piece of STATUS sits here, together. "Awaiting approval"
                used to live down in the action row, where a badge shared a line
                with buttons and had to be excluded from their layout. It is a
                state, like the one beside it.

                The badges wrap inside their own group; the menu is a separate
                item pinned to the end. Sharing one cluster, the menu's auto
                margin put it at the end of whatever line it happened to land
                on -- on a phone the badges wrap and it slid down to the second.
            -->
            <div class="p-event__status">
                <div class="l-cluster">
                    <!--
                        No Registration Open / Closed badge. Its only input was
                        the deadline, and there is no deadline -- a badge
                        reading "Registration Open" on every card forever tells
                        a reader nothing they could act on.
                    -->
                    <Badge>{{ tournament.season_label }}</Badge>

                    <!--
                        The controller refuses this case too, and a button that
                        vanishes with no explanation reads as a bug rather than
                        as a decision pending on you.
                    -->
                    <Badge v-if="tournament.awaiting_approval">Awaiting approval</Badge>
                </div>

                <!--
                    In the card's top-end corner, in flow beside the badges
                    rather than positioned over them -- .p-event has
                    overflow:hidden to clip the map to the card's corners, and
                    an absolutely placed panel would be cut off by it.
                -->
                <Dropdown class="p-event__menu">
                    <template #trigger>
                        <button type="button" class="action" title="More actions">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="5" r="1"/>
                                <circle cx="12" cy="12" r="1"/>
                                <circle cx="12" cy="19" r="1"/>
                            </svg>
                            <span class="u-visually-hidden">More actions</span>
                        </button>
                    </template>

                    <template #content>
                        <DropdownLink :href="tournament.season_href">Season Standings</DropdownLink>

                        <!--
                            poker.venues.show is inside the admin-only /poker
                            prefix. Offering it to a player is offering a 403,
                            so the href is null for one.
                        -->
                        <DropdownLink v-if="tournament.venue_href" :href="tournament.venue_href">
                            Venue Report
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>

            <!--
                The name spans the card on its own line. Beside the calendar
                leaf it had only the width left over, so a long tournament name
                wrapped to two or three lines against a leaf four rem wide.
            -->
            <h2 class="p-event__title">{{ tournament.name }}</h2>

            <div class="p-event__head">
                <div class="p-event__when" aria-hidden="true">
                    <span class="p-event__month">{{ tournament.month_label }}</span>
                    <span class="p-event__day">{{ tournament.day_label }}</span>
                    <span class="p-event__weekday">{{ tournament.weekday_label }}</span>
                </div>

                <!-- Where and when, and nothing else: the two things that go
                     with a date. -->
                <div>
                    <!-- The same pin the map overlay uses, so the two marks
                         agree. aria-hidden: it is a picture of the word beside
                         it. -->
                    <p class="p-event__venue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>

                        {{ tournament.venue_label }}
                    </p>

                    <!-- The date as a line, drawn only on a narrow card where
                         the calendar leaf is not. aria-hidden like the leaf it
                         stands in for: the time line below carries the whole
                         date for a screen reader, so the date is never said
                         twice. -->
                    <p class="p-event__date" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="16" rx="2"/>
                            <path d="M16 3v4M8 3v4M3 11h18"/>
                        </svg>

                        {{ tournament.date_label }}
                    </p>

                    <p class="p-event__time">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>

                        <span class="u-visually-hidden">Starts:</span>
                        <span aria-hidden="true">{{ tournament.time_label }}</span>
                        <span class="u-visually-hidden">{{ tournament.full_date_label }}</span>
                    </p>
                </div>
            </div>

            <!-- Anything a consumer wants between the facts and the actions --
                 the tournament's own description. -->
            <slot />

            <div v-if="tournament.is_registered || tournament.can_register
                     || tournament.can_unregister || details"
                 class="p-event__actions">
                <!-- At the start of the row; the buttons take the end. -->
                <Badge v-if="tournament.is_registered" variant="open">Registered</Badge>

                <!-- Only when it holds something. Unconditional it rendered an
                     empty div on a settled tournament. -->
                <div v-if="details || tournament.can_register || tournament.can_unregister"
                     class="p-event__actions-end">
                    <!-- First, so it sits to the LEFT of Register -- and alone
                         in the row when there is nothing to register for.
                         Ghost, because reading about the night is not what the
                         card is asking you to do. -->
                    <Btn v-if="details" variant="ghost" :href="tournament.href">Details</Btn>

                    <!-- Primary: it is the thing the card wants you to do. -->
                    <Btn v-if="tournament.can_register" variant="primary" type="button"
                         @click="register">Register</Btn>

                    <!-- Standing where Register would. The two are mutually
                         exclusive: nobody can both join and leave the same
                         tournament.

                         Ghost, not danger. Leaving is not what the card is
                         asking you to do, and a red button alone in that row
                         would read as the call to action. -->
                    <Btn v-if="tournament.can_unregister" variant="ghost" type="button"
                         @click="unregister">Unregister</Btn>
                </div>
            </div>
        </div>
    </article>
</template>
