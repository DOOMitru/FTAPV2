<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import Alert from '../../Components/Alert.vue';
import Btn from '../../Components/Btn.vue';
import type { PageProps } from '../../types/inertia';

/*
 * The flash, drawn as written.
 *
 * This page used to be handed a link_sent boolean and keep the sentence
 * itself, because session('status') held the sentinel 'verification-link-sent'
 * and printing it would have shown a reader that word. The controller flashes
 * the sentence now, so there is nothing to translate here.
 *
 * This shell is GuestLayout, which owns no flash -- AppLayout does, and this
 * screen is deliberately outside it. So the alert stays on the page.
 *
 * computed, not a plain const: Inertia reuses a component when a visit renders
 * the same page, and resending lands back here, so a value read once at setup
 * would be the flash from BEFORE the resend.
 */
const flash = computed(() => usePage<PageProps>().props.flash);

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
        <Alert v-if="flash.status" variant="success" :message="flash.status" />

        <div class="l-cluster l-cluster--between">
            <Btn variant="primary" type="button" @click="resend">Resend Verification Email</Btn>

            <!-- Ghost, not an underlined bare button: logging out is a
                 secondary action here, and the system already has a variant
                 for that. -->
            <Btn variant="ghost" type="button" @click="logout">Log Out</Btn>
        </div>
    </GuestLayout>
</template>
