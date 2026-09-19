<script setup lang="ts">
import { useSlots } from 'vue';

interface Props {
    title?: string | null;
    flush?: boolean;
}

withDefaults(defineProps<Props>(), { title: null, flush: false });

const slots = useSlots();
</script>

<template>
    <div class="card">
        <div v-if="title || slots.actions" class="card__header">
            <h2 class="card__title">{{ title }}</h2>
            <div v-if="slots.actions" class="l-cluster"><slot name="actions" /></div>
        </div>

        <div class="card__body" :class="{ 'card__body--flush': flush }"><slot /></div>
    </div>
</template>
