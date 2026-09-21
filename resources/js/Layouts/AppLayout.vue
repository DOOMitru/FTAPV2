<script setup lang="ts">
import Navigation from './Navigation.vue';
import SiteFooter from '../Components/SiteFooter.vue';
import ConfirmDialog from '../Components/ConfirmDialog.vue';
</script>

<template>
    <div class="shell">
        <Navigation />

        <!--
            Flash is shared on every page and still drawn by each page that
            wants it. What kept it out of here was sentinel flashing -- strings
            a page matched on rather than displayed -- and no controller does
            that any more, so this layout can take ownership of it.
        -->
        <header v-if="$slots.header" class="shell__header">
            <div class="shell__header-inner"><slot name="header" /></div>
        </header>

        <!--
            No .l-container here. Every page supplies its own; .l-container sets
            padding-inline, so two of them applied the gutter twice -- 32px a
            side on a phone, where there was least room to give.
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
    </div>
</template>
