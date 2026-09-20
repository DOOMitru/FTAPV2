<script setup lang="ts">
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import SponsorForm, { type Sponsor } from './Partials/SponsorForm.vue';

interface Props {
    sponsor: Sponsor;
}

defineProps<Props>();
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Setup" title="Edit Sponsor" />
        </template>

        <div class="l-container l-stack">
            <Card title="Current Logo">
                <img class="sponsor-preview" :src="sponsor.logo_url" :alt="sponsor.name">
            </Card>

            <Card>
                <SponsorForm :sponsor="sponsor" />
            </Card>
        </div>
    </AppLayout>
</template>
