<script setup lang="ts">
import { useSlots } from 'vue';

interface Props {
    title: string;
    eyebrow?: string | null;
}

withDefaults(defineProps<Props>(), { eyebrow: null });

const slots = useSlots();
</script>

<template>
    <div class="l-cluster l-cluster--between">
        <div>
            <p v-if="eyebrow" class="u-eyebrow">{{ eyebrow }}</p>
            <h1 class="page-header__title">{{ title }}</h1>
        </div>

        <div v-if="slots.actions" class="l-cluster"><slot name="actions" /></div>
    </div>
</template>
