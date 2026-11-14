<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    href: string;
}

defineProps<Props>();
</script>

<template>
    <!--
        .dropdown__item carries the same type treatment as .nav-link, so a menu
        item and a top-level link read as one vocabulary.
    -->
    <Link class="dropdown__item" :href="href"><slot /></Link>
</template>
