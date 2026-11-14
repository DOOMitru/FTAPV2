<script setup lang="ts">
import { useSlots } from 'vue';

interface Props {
    title: string;
}

defineProps<Props>();

const slots = useSlots();
</script>

<template>
    <div class="empty">
        <p class="empty__title">{{ title }}</p>

        <p v-if="slots.default" class="empty__body"><slot /></p>

        <div v-if="slots.action" class="empty__action"><slot name="action" /></div>
    </div>
</template>
