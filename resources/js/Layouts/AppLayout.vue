<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Navigation from './Navigation.vue';
import SiteFooter from '../Components/SiteFooter.vue';
import ConfirmDialog from '../Components/ConfirmDialog.vue';
import Alert from '../Components/Alert.vue';
import type { PageProps } from '../types/inertia';

interface Props {
    /**
     * Whether this page wants the shell's flash.
     *
     * True for every page but one. The tournament page opens a modal <dialog>,
     * which sits in the top layer over a backdrop and makes the rest of the
     * page inert -- a banner announced behind it is announced to somebody who
     * cannot see or reach it, so that page turns this off while its dialog is
     * open and the dialog carries its own copy of the message.
     */
    showFlash?: boolean;
}

const props = withDefaults(defineProps<Props>(), { showFlash: true });

/*
 * computed, not a plain const.
 *
 * usePage().props.flash read once at setup is the flash that ARRIVED WITH THIS
 * COMPONENT. Inertia reuses a component when a visit renders the same page, so
 * every redirect back to the page you are already on -- a delete returning to
 * its listing, a form returning to itself -- would leave the old value in place
 * and show nothing. The layout persists across far more visits than any page
 * did, so it is the worst possible place to get this wrong;
 * FlashMessageParityTest holds it.
 */
const flash = computed(() => usePage<PageProps>().props.flash);

const hasFlash = computed(() => props.showFlash && (flash.value.status || flash.value.error));
</script>

<template>
    <div class="shell">
        <Navigation />

        <header v-if="$slots.header" class="shell__header">
            <div class="shell__header-inner"><slot name="header" /></div>
        </header>

        <!--
            No .l-container on the slot. Every page supplies its own;
            .l-container sets padding-inline, so two of them applied the gutter
            twice -- 32px a side on a phone, where there was least room to give.
            The flash below brings one as the page's SIBLING, not its parent,
            which is the arrangement DashboardGutterTest holds to.
        -->
        <main class="shell__content">
            <!--
                The shell owns the flash, so a page that is redirected to with
                one does not have to remember to draw it. Eleven pages each kept
                their own copy of this, and the three that forgot swallowed
                every confirmation the app sent them.
            -->
            <div v-if="hasFlash" class="l-container l-stack shell__flash">
                <Alert v-if="flash.status" variant="success" :message="flash.status" />
                <Alert v-if="flash.error" variant="danger" :message="flash.error" />
            </div>

            <slot />
        </main>

        <!--
            .shell is a flex column with min-height: 100vh and .shell__content
            takes flex: 1, so this sits at the bottom of the viewport on a short
            page rather than floating mid-screen.
        -->
        <SiteFooter />

        <!-- One per page. Everything that asks before acting asks through this. -->
        <ConfirmDialog />
    </div>
</template>
