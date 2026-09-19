<script setup lang="ts">
import { ref } from 'vue';
import { onClickOutside } from '../composables/onClickOutside';
import Brand from './Brand.vue';

const menuOpen = ref(false);
const header = ref<HTMLElement | null>(null);

// Bound to the HEADER, not the panel. topbar.blade.php records why: the burger
// sits outside the panel, so a handler on the panel alone fires on the very
// click that opens the menu -- the button sets it true, the document listener
// sets it false, and the menu can never stay open. The header contains both.
onClickOutside(header, () => { menuOpen.value = false; });

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        menuOpen.value = false;
    }
};
</script>

<template>
    <header ref="header" class="topbar" @keydown="onEscape">
        <div class="topbar__inner">
            <Brand />

            <!--
                Anything that belongs beside the burger rather than inside the
                collapsing panel. The authenticated shell puts the notification
                bell here; the public shell passes nothing.
            -->
            <slot name="leading" />

            <button type="button" class="topbar__burger" :aria-expanded="menuOpen"
                    aria-controls="topbar-menu" @click="menuOpen = ! menuOpen">
                <svg class="topbar__burger-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path v-if="! menuOpen" d="M4 7h16M4 12h16M4 17h16"/>
                    <path v-else d="M6 6l12 12M18 6L6 18"/>
                </svg>
                <span class="u-visually-hidden">Menu</span>
            </button>

            <div class="topbar__panel" id="topbar-menu"
                 :class="{ 'topbar__panel--open': menuOpen }">
                <nav class="topbar__nav" aria-label="Main"><slot name="links" /></nav>
                <div class="topbar__actions"><slot name="actions" /></div>
            </div>
        </div>
    </header>
</template>
