<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Field from '../../../Components/Field.vue';
import Btn from '../../../Components/Btn.vue';

interface Props {
    venues: { id: string; name: string }[];
    /** The current season's name, or the wording for having none. */
    season_label: string;
}

defineProps<Props>();

const form = useForm({
    name: '',
    description: '',
    start_time: '',
    venue_id: '',
});

const submit = (): void => { form.post('/poker/tournaments'); };
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" title="Schedule Tournament" />
        </template>

        <div class="l-container">
            <Card>
                <form class="l-stack" @submit.prevent="submit">
                    <Field v-model="form.name" name="name" label="Name"
                           required autofocus :error="form.errors.name" />

                    <Field name="description" label="Description" :error="form.errors.description">
                        <textarea v-model="form.description" class="field__control"
                                  name="description" id="description" rows="3"></textarea>
                    </Field>

                    <Field v-model="form.start_time" name="start_time" type="datetime-local"
                           label="Start Date &amp; Time" required :error="form.errors.start_time" />

                    <div class="l-grid">
                        <!-- Read-only on create: the controller assigns the current
                             season, so offering a choice would imply one exists.

                             Shaped like the control beside it rather than like a
                             value in a list -- see .field__control--static, and the
                             alignment it was drawn with before it. -->
                        <div>
                            <span class="field__label">Season</span>

                            <p class="field__control field__control--static">{{ season_label }}</p>
                        </div>

                        <Field name="venue_id" label="Venue" :error="form.errors.venue_id">
                            <select v-model="form.venue_id" class="field__control"
                                    name="venue_id" id="venue_id" required>
                                <option value="">Select Venue</option>

                                <option v-for="venue in venues" :key="venue.id" :value="venue.id">
                                    {{ venue.name }}
                                </option>
                            </select>
                        </Field>
                    </div>

                    <div class="l-cluster l-cluster--end">
                        <Btn variant="ghost" href="/poker/tournaments">Cancel</Btn>
                        <Btn variant="primary" :disabled="form.processing">Save</Btn>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
