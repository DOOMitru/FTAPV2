<script setup lang="ts">
import { computed, ref } from 'vue';
import { onClickOutside } from '../composables/onClickOutside';

interface Props {
    align?: 'left' | 'right';
    /**
     * Makes the panel flow in the document below 48rem instead of floating
     * over it -- an absolutely-positioned popup inside a stacked mobile menu
     * reads as a detached overlay rather than a disclosure.
     */
    inlineMobile?: boolean;
}

const props = withDefaults(defineProps<Props>(), { align: 'right', inlineMobile: false });

const open = ref(false);
const root = ref<HTMLElement | null>(null);

onClickOutside(root, () => { open.value = false; });

const menuClasses = computed(() => 'dropdown__menu'
    + (props.align === 'left' ? ' dropdown__menu--left' : '')
    + (props.inlineMobile ? ' dropdown__menu--inline-mobile' : ''));
</script>

<template>
    <div ref="root" class="dropdown">
        <div class="dropdown__trigger" aria-haspopup="menu" :aria-expanded="open"
             @click="open = ! open">
            <slot name="trigger" />
        </div>

        <!--
            The motion classes map one-for-one onto Alpine's x-transition, which
            is what this replaces: enter-active is the shared `dropdown__motion`,
            the from/to pair are the start and end states, and leaving adds the
            modifier. Dropping them was the first thing ComponentParityTest
            caught -- the panel still worked, it just snapped open with no
            animation, which is not something a reviewer would notice.

            Closing on any click inside is right for a menu of links and fatal
            for an input placed in one -- a search box would shut the moment it
            was clicked. A caller putting a field in here stops the event at
            the field, which contains the exception rather than changing this.
        -->
        <Transition
            enter-active-class="dropdown__motion"
            enter-from-class="dropdown__motion-from"
            enter-to-class="dropdown__motion-to"
            leave-active-class="dropdown__motion dropdown__motion--leaving"
            leave-from-class="dropdown__motion-to"
            leave-to-class="dropdown__motion-from"
        >
            <div v-show="open" :class="menuClasses" @click="open = false">
                <slot name="content" />
            </div>
        </Transition>
    </div>
</template>
