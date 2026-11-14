<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import Field from '../../Components/Field.vue';
import InputError from '../../Components/InputError.vue';
import Btn from '../../Components/Btn.vue';

interface Props {
    /** "Edit User: Dana Whitlock", composed in PHP. */
    title: string;
    user: {
        id: string;
        first_name: string;
        last_name: string;
        nickname: string | null;
        email: string;
        is_admin: boolean;
    };
}

const props = defineProps<Props>();

/*
 * No hidden `is_admin` field, unlike the Blade form.
 *
 * That hidden 0 existed because an unchecked checkbox submits nothing, so
 * without it clearing the box left is_admin untouched rather than false.
 * useForm sends the value it holds either way, so false arrives as false and
 * a second field posting the same name would be one more thing to keep in
 * step.
 */
const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    nickname: props.user.nickname ?? '',
    email: props.user.email,
    is_admin: props.user.is_admin,
});

const submit = (): void => { form.patch(`/users/${props.user.id}`); };
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Setup" :title="title" />
        </template>

        <div class="l-container">
            <Card>
                <form class="l-stack" @submit.prevent="submit">
                    <div class="l-grid">
                        <Field v-model="form.first_name" name="first_name" label="First Name"
                               required autofocus autocomplete="given-name"
                               :error="form.errors.first_name" />

                        <Field v-model="form.last_name" name="last_name" label="Last Name"
                               required autocomplete="family-name"
                               :error="form.errors.last_name" />
                    </div>

                    <Field v-model="form.nickname" name="nickname" label="Nickname"
                           autocomplete="nickname" :error="form.errors.nickname" />

                    <Field v-model="form.email" name="email" type="email" label="Email"
                           required autocomplete="username" :error="form.errors.email" />

                    <div>
                        <label class="field__check" for="is_admin">
                            <input v-model="form.is_admin" id="is_admin" type="checkbox"
                                   name="is_admin" value="1">
                            <span>Administrator Access</span>
                        </label>

                        <InputError :messages="form.errors.is_admin" />
                    </div>

                    <div class="l-cluster l-cluster--end">
                        <Btn variant="ghost" href="/users">Cancel</Btn>
                        <Btn variant="primary" :disabled="form.processing">Update User</Btn>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
