<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Field from '../../../Components/Field.vue';
import Btn from '../../../Components/Btn.vue';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = (): void => {
    form.put('/password', {
        preserveScroll: true,
        // Clearing only on failure, and only the fields that failed: leaving a
        // wrong current password in the box is how somebody submits it twice.
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
            }

            if (form.errors.current_password) {
                form.reset('current_password');
            }
        },
        onSuccess: () => form.reset(),
    });
};

/*
 * "Saved." is this FORM's business, not the page's.
 *
 * It used to watch a page-wide flash for the sentinel 'password-updated',
 * which is how one of three forms on this screen learned that it -- and not
 * one of the other two -- had just saved. useForm already knows: it sets
 * recentlySuccessful on success and clears it 2000ms later, the same window
 * the hand-rolled setTimeout used.
 */
</script>

<template>
    <section class="l-stack">
        <header>
            <h2 class="card__title">Update Password</h2>
            <p class="field__hint">Ensure your account is using a long, random password to stay secure.</p>
        </header>

        <!--
            Inertia scopes validation errors to the request, so the three forms
            on this page cannot light each other's fields up. That is what the
            Blade partial's bag="updatePassword" buys, and it is why this needs
            no equivalent.
        -->
        <form class="l-stack" @submit.prevent="submit">
            <Field v-model="form.current_password" name="current_password" type="password"
                   label="Current Password" autocomplete="current-password"
                   :error="form.errors.current_password" />

            <Field v-model="form.password" name="password" type="password"
                   label="New Password" autocomplete="new-password"
                   :error="form.errors.password" />

            <Field v-model="form.password_confirmation" name="password_confirmation" type="password"
                   label="Confirm Password" autocomplete="new-password"
                   :error="form.errors.password_confirmation" />

            <div class="l-cluster l-cluster--end">
                <Btn variant="primary">Save</Btn>

                <Transition>
                    <p v-if="form.recentlySuccessful" class="field__hint">Saved.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
