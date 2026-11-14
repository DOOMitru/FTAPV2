<script setup lang="ts">
import Navigation from './Navigation.vue';
import SiteFooter from '../Components/SiteFooter.vue';
import ConfirmDialog from '../Components/ConfirmDialog.vue';
import FlashToasts from '../Components/FlashToasts.vue';

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

// Passed straight through. Reading the flash is FlashToasts' job, and doing it
// here as well would be two definitions of which key is which variant.
const props = withDefaults(defineProps<Props>(), { showFlash: true });
</script>

<template>
    <div class="shell">
        <Navigation />

        <header v-if="$slots.header" class="shell__header">
            <div class="shell__header-inner"><slot name="header" /></div>
        </header>

        <!--
            No .l-container here at all. Every page supplies its own, and one in
            the layout nested inside it -- .l-container sets padding-inline, so
            two of them applied the gutter twice, 32px a side on a phone where
            there was least room to give. DashboardGutterTest holds it.
        -->
        <main class="shell__content"><slot /></main>

        <!--
            .shell is a flex column with min-height: 100vh and .shell__content
            takes flex: 1, so this sits at the bottom of the viewport on a short
            page rather than floating mid-screen.
        -->
        <SiteFooter />

        <!-- One per page. Everything that asks before acting asks through this. -->
        <ConfirmDialog />

        <!--
            The shell owns the flash, so a page that is redirected to with one
            does not have to remember to draw it. Eleven pages each kept their
            own copy, and the three that forgot swallowed every confirmation the
            app sent them.

            Last in the shell and fixed to the bottom of the viewport, so it
            passes over the footer rather than pushing the page around.
        -->
        <FlashToasts :show="props.showFlash" />
    </div>
</template>
