<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    /** One message or several. Inertia gives one per field; Blade gives a bag. */
    messages?: string | string[] | null;
}

const props = withDefaults(defineProps<Props>(), { messages: null });

const list = computed<string[]>(() => {
    if (props.messages === null || props.messages === '') {
        return [];
    }

    return Array.isArray(props.messages) ? props.messages : [props.messages];
});
</script>

<template>
    <!--
        .field__error, not a generic red. What distinguishes an error here is
        not hue but position and conditionality: bold small text sitting
        directly beneath the field it belongs to, rendered only when that field
        failed. The brand red never appears in that shape.
    -->
    <ul v-if="list.length > 0" class="field__error">
        <li v-for="(message, index) in list" :key="index">{{ message }}</li>
    </ul>
</template>
