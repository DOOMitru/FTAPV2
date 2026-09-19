<script setup lang="ts">
interface Props {
    label: string;
    /** Already formatted -- spec §5a. This places a value, it does not format one. */
    value: string | number;
}

defineProps<Props>();
</script>

<template>
    <div class="stat">
        <span class="stat__label">{{ label }}</span>
        <span class="stat__value">{{ value }}</span>
    </div>
</template>
