<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import type { PageProps } from '../../types/inertia';

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

// The only thing that flashes to this page is the resend-verification link,
// and it flashes a sentence. Saving a form no longer flashes at all -- each
// form's "Saved." comes from its own useForm.
//
// computed, not a plain const: usePage().props.flash read once at setup is
// the flash that ARRIVED WITH THIS COMPONENT. Inertia reuses a component when
// a visit renders the same page, so every redirect back to the page you are on
// -- a delete returning to its listing, a form returning to itself -- left the
// old value in place and showed nothing.
const flash = computed(() => usePage<PageProps>().props.flash);
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
            <Card><UpdateProfileInformationForm :user="props.user" :status="flash.status" /></Card>
            <Card><UpdatePasswordForm /></Card>
            <Card><DeleteUserForm /></Card>
        </div>
    </AppLayout>
</template>
