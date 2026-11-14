<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PageLink } from '../types/inertia';

interface Props {
    links: PageLink[];
    /** "Showing 1 to 10 of 43". Null when there is only one page. */
    statusLabel?: string | null;
}

withDefaults(defineProps<Props>(), { statusLabel: null });
</script>

<template>
    <!--
        statusLabel is the signal, not a count of links. Paginate::props sets it
        only when hasPages() is true, which is the same condition the Blade view
        opens with. Guessing from links.length would be a second definition of
        "has pages" and a wrong one: a two-page list has four links.
    -->
    <nav v-if="statusLabel" class="pager" role="navigation" aria-label="Pagination Navigation">
        <span class="pager__status">{{ statusLabel }}</span>

        <ul class="pager__list">
            <li v-for="(link, index) in links" :key="index">
                <!--
                    Word and arrow both, always in the DOM. On a phone the words
                    do not fit -- PREVIOUS, five page numbers and NEXT need about
                    400px against 343 of usable width -- so below 48rem the
                    label is CLIPPED rather than removed. The link stays an
                    arrow to look at and still reads as "Previous" to a screen
                    reader; display:none would take the name with it.
                -->
                <component
                    :is="link.url === null ? 'span' : Link"
                    v-if="link.kind === 'previous' || link.kind === 'next'"
                    :href="link.url ?? undefined"
                    :rel="link.kind === 'previous' ? 'prev' : 'next'"
                    class="pager__link pager__link--edge"
                    :class="{ 'pager__link--disabled': link.url === null }"
                    :aria-disabled="link.url === null ? 'true' : undefined"
                >
                    <svg v-if="link.kind === 'previous'" class="pager__arrow" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true"><path d="M15 19l-7-7 7-7"/></svg>

                    <span class="pager__label">{{ link.label }}</span>

                    <svg v-if="link.kind === 'next'" class="pager__arrow" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
                </component>

                <span v-else-if="link.kind === 'gap'" class="pager__gap" aria-hidden="true">{{ link.label }}</span>

                <span v-else-if="link.active" class="pager__link pager__link--current"
                      aria-current="page">{{ link.label }}</span>

                <Link v-else-if="link.url" class="pager__link" :href="link.url"
                      :aria-label="link.aria_label ?? undefined">{{ link.label }}</Link>
            </li>
        </ul>
    </nav>
</template>
