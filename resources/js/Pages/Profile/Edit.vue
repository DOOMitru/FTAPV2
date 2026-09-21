<script setup lang="ts">
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';

interface Props {
    user: {
        first_name: string;
        last_name: string;
        nickname: string | null;
        email: string;
        /** The decision, not the ingredients -- see spec §5a. */
        needs_email_verification: boolean;
    };
}

const props = defineProps<Props>();
</script>

<template>
    <!--
        The layout is wrapped here rather than assigned in inertia.ts. Inertia's
        persistent layout makes the page the layout's DEFAULT slot, which leaves
        no way to fill `header` -- the full-width band this shell puts above the
        content. See the note in inertia.ts.
    -->
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Account" title="Profile" />
        </template>

        <div class="l-container l-stack">
            <Card><UpdateProfileInformationForm :user="props.user" /></Card>
            <Card><UpdatePasswordForm /></Card>
            <Card><DeleteUserForm /></Card>
        </div>
    </AppLayout>
</template>
