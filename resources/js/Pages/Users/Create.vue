<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import Field from '../../Components/Field.vue';
import InputError from '../../Components/InputError.vue';
import Btn from '../../Components/Btn.vue';

const form = useForm({
    first_name: '',
    last_name: '',
    nickname: '',
    email: '',
    is_admin: false,
});

const submit = (): void => { form.post('/users'); };
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Setup" title="Register Player" />
        </template>

        <div class="l-container">
            <Card>
                <!-- No password field, deliberately. The account is created with an
                     unusable random password and the player sets their own through
                     a reset link, so no password ever passes through an
                     administrator's hands or their notes. -->
                <p class="field__hint">
                    The player is approved immediately and receives a link to set their own
                    password. They still verify their own email address.
                </p>

                <form class="l-stack" @submit.prevent="submit">
                    <Field v-model="form.first_name" name="first_name" label="First Name"
                           required autofocus :error="form.errors.first_name" />

                    <Field v-model="form.last_name" name="last_name" label="Last Name"
                           required :error="form.errors.last_name" />

                    <Field v-model="form.nickname" name="nickname" label="Nickname"
                           :error="form.errors.nickname" />

                    <Field v-model="form.email" name="email" type="email" label="Email Address"
                           required :error="form.errors.email" />

                    <div>
                        <label class="field__check" for="is_admin">
                            <input v-model="form.is_admin" id="is_admin" type="checkbox"
                                   name="is_admin" value="1">
                            <span>Administrator</span>
                        </label>

                        <InputError :messages="form.errors.is_admin" />
                    </div>

                    <div class="l-cluster l-cluster--end">
                        <Btn variant="ghost" href="/users">Cancel</Btn>
                        <Btn variant="primary" :disabled="form.processing">Register Player</Btn>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
