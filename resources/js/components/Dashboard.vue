<template>
    <div class="p-6">
        <Card>
            <template #title>Welcome to Your Dashboard</template>
            <template #content>
                <p class="mb-4">You are logged in via the new PrimeVue interface!</p>
                <div class="flex gap-4 items-center">
                    <Button label="Click Me" icon="pi pi-check" @click="count++" />
                    <Tag :value="'Count: ' + count" severity="info" />
                </div>
            </template>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';

const count = ref(0);
</script>
