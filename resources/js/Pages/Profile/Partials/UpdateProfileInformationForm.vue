<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import Field from '../../../Components/Field.vue';
import Btn from '../../../Components/Btn.vue';
import Alert from '../../../Components/Alert.vue';
import InputError from '../../../Components/InputError.vue';
import type { PageProps } from '../../../types/inertia';

interface Props {
    user: {
        first_name: string;
        last_name: string;
        nickname: string | null;
        email: string;
        needs_email_verification: boolean;
    };
    status: string | null;
}

const props = defineProps<Props>();
const page = usePage<PageProps>();

const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    nickname: props.user.nickname ?? '',
    email: props.user.email,
    is_admin: page.props.auth.user?.is_admin ?? false,
});

// Literal paths: Ziggy is not installed, so there is no route() client-side.
const submit = (): void => { form.patch('/profile', { preserveScroll: true }); };
const resendVerification = (): void => { router.post('/email/verification-notification'); };

/*
 * "Saved." is this FORM's business -- see UpdatePasswordForm, which says the
 * same thing at more length. useForm sets recentlySuccessful on success and
 * clears it 2000ms later, which is the window the sentinel-watching timeout
 * used to hold open by hand.
 */
</script>

<template>
    <section class="l-stack">
        <header>
            <h2 class="card__title">Profile Information</h2>
            <p class="field__hint">Update your account's profile information and email address.</p>
        </header>

        <form class="l-stack" @submit.prevent="submit">
            <div class="l-grid">
                <Field v-model="form.first_name" name="first_name" label="First Name"
                       required autofocus autocomplete="given-name" :error="form.errors.first_name" />

                <Field v-model="form.last_name" name="last_name" label="Last Name"
                       required autocomplete="family-name" :error="form.errors.last_name" />
            </div>

            <!-- Not decoration: the nickname drives the season standings naming
                 rule and the top bar's display_name. -->
            <Field v-model="form.nickname" name="nickname" label="Nickname"
                   autocomplete="nickname" :error="form.errors.nickname" />

            <div>
                <Field v-model="form.email" name="email" type="email" label="Email"
                       required autocomplete="username" :error="form.errors.email" />

                <p v-if="user.needs_email_verification" class="field__hint">
                    Your email address is unverified.

                    <button type="button" class="link" @click="resendVerification">
                        Click here to re-send the verification email.
                    </button>
                </p>

                <!--
                    The flash is the sentence now, so this draws it rather than
                    matching a sentinel and keeping a copy of the wording. The
                    only thing that flashes to this page is the resend above.
                -->
                <Alert v-if="status" variant="success" :message="status" />
            </div>

            <div v-if="page.props.auth.user?.is_admin">
                <label class="field__check" for="is_admin">
                    <input id="is_admin" v-model="form.is_admin" type="checkbox" name="is_admin" value="1">
                    <span>Is Admin</span>
                </label>

                <InputError :messages="form.errors.is_admin" />
            </div>

            <div class="l-cluster l-cluster--end">
                <Btn variant="primary">Save</Btn>

                <Transition>
                    <p v-if="form.recentlySuccessful" class="field__hint">Saved.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
