<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Field from '../../../Components/Field.vue';
import Btn from '../../../Components/Btn.vue';

interface Props {
    venue: {
        id: string;
        name: string;
        description: string | null;
        address: string | null;
    };
}

const props = defineProps<Props>();

const form = useForm({
    name: props.venue.name,
    description: props.venue.description ?? '',
    address: props.venue.address ?? '',
});

const submit = (): void => { form.patch(`/poker/venues/${props.venue.id}`); };
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" title="Edit Venue" />
        </template>

        <div class="l-container">
            <Card>
                <form class="l-stack" @submit.prevent="submit">
                    <Field v-model="form.name" name="name" label="Name"
                           required autofocus :error="form.errors.name" />

                    <!--
                        A textarea, through the slot: Field renders a text input
                        of its own only when given no slot. Three rows, as the
                        Blade has it.
                    -->
                    <Field name="description" label="Description" :error="form.errors.description">
                        <textarea v-model="form.description" class="field__control"
                                  name="description" id="description" rows="3"></textarea>
                    </Field>

                    <Field v-model="form.address" name="address" label="Physical Address"
                           placeholder="e.g. 123 Poker Street, Las Vegas, NV"
                           hint="Optional: Providing an address will enable a Google Maps preview."
                           :error="form.errors.address" />

                    <div class="l-cluster l-cluster--end">
                        <Btn variant="ghost" href="/poker/venues">Cancel</Btn>
                        <Btn variant="primary" :disabled="form.processing">Save</Btn>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
