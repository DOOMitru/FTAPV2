<script setup lang="ts">
// A plain <a>, not an Inertia <Link>. The mark is the way back OUT of the
// admin area to the public site, and the public site is Blade -- so this is a
// real navigation, not a client-side visit.
</script>

<template>
    <a class="brand" href="/">
        <img class="brand__logo" src="/images/header_logo.png" alt="">
        <span class="brand__text">
            <span class="brand__name">First to Act Poker</span>
            <span class="brand__location">Regina, SK</span>
        </span>
    </a>
</template>
