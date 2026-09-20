<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import Alert from '../../Components/Alert.vue';
import Btn from '../../Components/Btn.vue';

interface Props {
    /**
     * A boolean, not the session value.
     *
     * session('status') holds the sentinel 'verification-link-sent' here, so
     * passing it to an alert would print that word on the screen. The sentence
     * below is the message; the session value only says whether to show it.
     */
    link_sent: boolean;
}

defineProps<Props>();

const resend = (): void => { router.post('/email/verification-notification'); };
const logout = (): void => { router.post('/logout'); };
</script>

<template>
    <GuestLayout>
        <p class="u-muted">
            Thanks for signing up! Before getting started, could you verify your email address
            by clicking on the link we just emailed to you? If you didn't receive the email, we
            will gladly send you another.
        </p>

        <!-- Was text-green-600 with no dark variant. Alert carries its own role
             and token colours, and reads on both grounds. -->
        <Alert v-if="link_sent" variant="success"
               message="A new verification link has been sent to the email address you provided during registration." />

        <div class="l-cluster l-cluster--between">
            <Btn variant="primary" type="button" @click="resend">Resend Verification Email</Btn>

            <!-- Ghost, not an underlined bare button: logging out is a
                 secondary action here, and the system already has a variant
                 for that. -->
            <Btn variant="ghost" type="button" @click="logout">Log Out</Btn>
        </div>
    </GuestLayout>
</template>
