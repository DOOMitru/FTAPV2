<script setup lang="ts">
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import SponsorForm from './Partials/SponsorForm.vue';
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Setup" title="Add Sponsor" />
        </template>

        <div class="l-container">
            <Card>
                <SponsorForm :sponsor="null" />
            </Card>
        </div>
    </AppLayout>
</template>
