<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import Topbar from '../Components/Topbar.vue';
import Dropdown from '../Components/Dropdown.vue';
import DropdownLink from '../Components/DropdownLink.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
import Monogram from '../Components/Monogram.vue';
import NotificationCard from '../Components/NotificationCard.vue';
import Btn from '../Components/Btn.vue';
import { useConfirm } from '../composables/useConfirm';
import type { Notification, PageProps } from '../types/inertia';

const page = usePage<PageProps>();
const { ask } = useConfirm();

// route() is not available client-side: Ziggy is not installed and is out of
// scope, so current state is decided from the page URL. The Blade uses
// routeIs() with the same prefixes.
const isCurrent = (...prefixes: string[]): boolean =>
    prefixes.some((prefix) => page.url.startsWith(prefix));

const hasRead = (): boolean => page.props.notifications.recent.some((n) => n.is_read);

const clearRead = async (): Promise<void> => {
    if (await ask('Delete every notification you have already read?')) {
        router.delete('/notifications/read', { preserveScroll: true });
    }
};

// The card asks rather than acts -- see NotificationCard.vue. The sentence
// comes from PHP already carrying its emphasis markers.
const deleteNotification = async (notification: Notification): Promise<void> => {
    if (await ask(notification.delete_confirm)) {
        router.delete(`/notifications/${notification.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Topbar>
        <template #leading>
            <!--
                Mobile only: on desktop the notifications live in the user menu,
                and two surfaces showing the same list at once is one too many.

                Deliberately NOT dropdown--notifications: that class fixes the
                panel at 22rem, and this one is sized by .topbar__bell-menu to
                span the bar. Both selectors are (0,2,0) and _dropdown.css loads
                after _topbar.css, so carrying both let the fixed width win and
                pushed the panel off screen.
            -->
            <Dropdown align="right" class="topbar__bell-menu">
                <template #trigger>
                    <button type="button" class="topbar__bell">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>

                        <span class="u-visually-hidden">Notifications</span>

                        <!-- Absent at zero. A badge reading 0 says nothing, loudly. -->
                        <span v-if="page.props.notifications.unread_count > 0" class="topbar__bell-badge">
                            {{ page.props.notifications.unread_count }}
                            <span class="u-visually-hidden">unread notifications</span>
                        </span>
                    </button>
                </template>

                <template #content>
                    <p class="dropdown__heading">Notifications</p>

                    <div class="notification-list">
                        <NotificationCard v-for="notification in page.props.notifications.recent"
                                          :key="notification.id" :notification="notification"
                                          @request-delete="deleteNotification" />
                        <p v-if="page.props.notifications.recent.length === 0" class="dropdown__empty">
                            No notifications yet.
                        </p>
                    </div>

                    <form v-if="hasRead()" @submit.prevent="clearRead">
                        <button type="submit" class="dropdown__item">Clear read notifications</button>
                    </form>
                </template>
            </Dropdown>
        </template>

        <template #links>
            <Link class="nav-link" :class="{ 'nav-link--current': isCurrent('/dashboard') }"
                  :aria-current="isCurrent('/dashboard') ? 'page' : undefined"
                  href="/dashboard">Dashboard</Link>

            <!--
                League is open to everyone signed in: the three lists behind it
                are records a player has a reason to read. Setup stays behind
                the admin gate -- it is for running the league, not following it.
            -->
            <Dropdown align="left" :inline-mobile="true">
                <template #trigger>
                    <button type="button" class="nav-link"
                            :class="{ 'nav-link--current': isCurrent('/poker/seasons', '/poker/venues', '/poker/tournaments') }"
                            :aria-current="isCurrent('/poker/seasons', '/poker/venues', '/poker/tournaments') ? 'page' : undefined">
                        League
                    </button>
                </template>

                <template #content>
                    <DropdownLink href="/poker/seasons">Seasons</DropdownLink>
                    <DropdownLink href="/poker/venues">Venues</DropdownLink>
                    <DropdownLink href="/poker/tournaments">Tournaments</DropdownLink>
                </template>
            </Dropdown>

            <Dropdown v-if="page.props.auth.user?.is_admin" align="left" :inline-mobile="true">
                <template #trigger>
                    <button type="button" class="nav-link"
                            :class="{ 'nav-link--current': isCurrent('/poker/points-structure', '/users') }"
                            :aria-current="isCurrent('/poker/points-structure', '/users') ? 'page' : undefined">
                        Setup
                    </button>
                </template>

                <template #content>
                    <DropdownLink href="/poker/points-structure">Points structure</DropdownLink>
                    <DropdownLink href="/users">Players</DropdownLink>
                    <DropdownLink href="/sponsors">Sponsors</DropdownLink>
                </template>
            </Dropdown>
        </template>

        <template #actions>
            <!--
                Two structures rather than one restyled: on desktop the identity
                is a dropdown; on mobile it is a flat row. They differ in shape,
                not just in layout, so CSS alone cannot express both from one
                markup tree.
            -->
            <div class="topbar__actions-desktop">
                <ThemeToggle />

                <Dropdown align="right" class="dropdown--notifications">
                    <template #trigger>
                        <button type="button" class="nav-link nav-link--user">
                            <Monogram :initials="page.props.auth.user?.initials ?? ''" size="sm" decorative />
                            <span>{{ page.props.auth.user?.display_name }}</span>

                            <span v-if="page.props.notifications.unread_count > 0" class="nav-link__badge">
                                {{ page.props.notifications.unread_count }}
                                <span class="u-visually-hidden">unread notifications</span>
                            </span>
                        </button>
                    </template>

                    <template #content>
                        <div class="dropdown__section">
                            <p class="dropdown__heading">Notifications</p>

                            <div class="notification-list">
                                <NotificationCard v-for="notification in page.props.notifications.recent"
                                                  :key="notification.id" :notification="notification"
                                                  @request-delete="deleteNotification" />
                                <p v-if="page.props.notifications.recent.length === 0" class="dropdown__empty">
                                    No notifications yet.
                                </p>
                            </div>

                            <form v-if="hasRead()" @submit.prevent="clearRead">
                                <button type="submit" class="dropdown__item">Clear read notifications</button>
                            </form>
                        </div>

                        <div class="dropdown__section">
                            <DropdownLink href="/profile">Your profile</DropdownLink>

                            <form method="POST" action="/logout">
                                <button type="submit" class="dropdown__item">Log out</button>
                            </form>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <!--
                Mobile: log out sits alone on the left where a destructive action
                is hard to hit by accident; theme and identity group on the
                right. The avatar and name go straight to the profile -- no
                second disclosure.
            -->
            <div class="topbar__actions-mobile">
                <form method="POST" action="/logout">
                    <Btn variant="danger" size="sm">Log out</Btn>
                </form>

                <div class="topbar__identity">
                    <ThemeToggle />

                    <Link class="nav-link nav-link--user" href="/profile">
                        <Monogram :initials="page.props.auth.user?.initials ?? ''" size="sm" decorative />
                        <span>{{ page.props.auth.user?.display_name }}</span>
                    </Link>
                </div>
            </div>
        </template>
    </Topbar>
</template>
