<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import Field from '../../Components/Field.vue';
import Btn from '../../Components/Btn.vue';

const form = useForm({ password: '' });

const submit = (): void => {
    form.post('/confirm-password', {
        // The field is cleared whichever way it goes: a password left in the
        // box after a wrong guess is one a shoulder can read.
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <p class="u-muted">
            This is a secure area of the application. Please confirm your password before
            continuing.
        </p>

        <form class="l-stack" @submit.prevent="submit">
            <Field v-model="form.password" name="password" type="password" label="Password"
                   required autocomplete="current-password" :error="form.errors.password" />

            <div class="l-cluster l-cluster--end">
                <Btn variant="primary" :disabled="form.processing">Confirm</Btn>
            </div>
        </form>
    </GuestLayout>
</template>
