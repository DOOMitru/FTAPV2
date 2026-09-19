<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '../../../Components/Modal.vue';
import Field from '../../../Components/Field.vue';
import Btn from '../../../Components/Btn.vue';
import type { PageProps } from '../../../types/inertia';

const page = usePage<PageProps>();
const open = ref(false);

const form = useForm({ password: '' });

/*
 * Open when the last attempt failed.
 *
 * The dialog has to reopen so the person can try again -- otherwise a typo
 * silently dismisses the whole thing and looks like it worked. ProfileTest
 * asserts the server side of this: a wrong password comes back carrying the
 * userDeletion error bag. This is the half that reads it.
 */
const failed = computed(() => {
    const errors = page.props.errors as Record<string, unknown> | undefined;
    const bag = errors?.userDeletion as Record<string, string> | undefined;

    return bag?.password !== undefined;
});

watch(failed, (value) => { if (value) { open.value = true; } }, { immediate: true });

const close = (): void => {
    open.value = false;
    form.reset();
};

const destroy = (): void => {
    form.delete('/profile', { preserveScroll: true });
};
</script>

<template>
    <section class="l-stack">
        <header>
            <h2 class="card__title">Delete Account</h2>
            <p class="field__hint">
                Once your account is deleted, all of its resources and data will be permanently
                deleted. Before deleting your account, please download any data or information
                that you wish to retain.
            </p>
        </header>

        <div>
            <Btn variant="danger" type="button" @click="open = true">Delete Account</Btn>
        </div>

        <Modal :show="open" focusable @close="close">
            <form @submit.prevent="destroy">
                <div class="modal__body l-stack">
                    <h2 class="card__title">Are you sure you want to delete your account?</h2>

                    <p class="field__hint">
                        Once your account is deleted, all of its resources and data will be
                        permanently deleted. Please enter your password to confirm you would like
                        to permanently delete your account.
                    </p>

                    <!--
                        id, because the Update Password form above also has a
                        field named `password`. Two inputs with the same id make
                        a label point at whichever comes first, and this one
                        focused the New Password box in the other form.
                        DuplicateElementIdTest guards it.
                    -->
                    <Field v-model="form.password" name="password" id="delete-account-password"
                           type="password" label="Password"
                           :error="(page.props.errors as Record<string, Record<string, string>>)?.userDeletion?.password" />
                </div>

                <div class="modal__footer">
                    <Btn variant="ghost" @click="close">Cancel</Btn>
                    <Btn variant="danger">Delete Account</Btn>
                </div>
            </form>
        </Modal>
    </section>
</template>
