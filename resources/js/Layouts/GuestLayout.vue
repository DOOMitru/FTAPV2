<script setup lang="ts">
import Brand from '../Components/Brand.vue';
</script>

<template>
    <!--
        The guest shell: no navigation, because a person on these screens has
        somewhere to be and nowhere else to go.

        The Blade twin is layouts/guest.blade.php, which the four public auth
        screens still use -- /login, /register, /forgot-password and
        /reset-password stay Blade, the last two because /register carries the
        reCAPTCHA gate that is verified end to end. ComponentParityTest pairs
        components rather than layouts, so the classes here are copied from it
        by hand and are the only thing holding the two in step.
    -->
    <div class="guest">
        <div class="guest__panel l-stack">
            <div class="guest__brand"><Brand /></div>
            <slot />
        </div>
    </div>
</template>
