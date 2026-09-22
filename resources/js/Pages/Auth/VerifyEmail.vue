<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import Btn from '../../Components/Btn.vue';

/*
 * No flash here.
 *
 * This page used to be handed a link_sent boolean and keep the sentence
 * itself, because session('status') held the sentinel 'verification-link-sent'
 * and printing it would have shown a reader that word. The controller flashes
 * the sentence now, and GuestLayout toasts it -- so there is nothing for this
 * page to read or to word.
 */
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

        <div class="l-cluster l-cluster--between">
            <Btn variant="primary" type="button" @click="resend">Resend Verification Email</Btn>

            <!-- Ghost, not an underlined bare button: logging out is a
                 secondary action here, and the system already has a variant
                 for that. -->
            <Btn variant="ghost" type="button" @click="logout">Log Out</Btn>
        </div>
    </GuestLayout>
</template>
