<script setup lang="ts">
import { computed } from 'vue';
import { emphParts } from '../emph';

interface Props {
    /** A message carrying U+2063 markers, as PHP's emph() writes them. */
    message: string;
}

const props = defineProps<Props>();

const parts = computed(() => emphParts(props.message));
</script>

<template>
    <!--
        Interpolation, never v-html. A venue name is user input: the moment it
        is parsed as markup instead of carried as a value, the escaping argument
        this whole marker system exists for stops being true. Vue interpolates
        as text, which is the same guarantee confirm.ts gets from textContent.
    -->
    <span>
        <template v-for="(part, index) in parts" :key="index">
            <strong v-if="part.marked" class="emph">{{ part.text }}</strong>
            <template v-else>{{ part.text }}</template>
        </template>
    </span>
</template>
