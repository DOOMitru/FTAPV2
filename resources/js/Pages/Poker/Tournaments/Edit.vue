<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Field from '../../../Components/Field.vue';
import Btn from '../../../Components/Btn.vue';

interface Props {
    tournament: {
        id: string;
        name: string;
        description: string | null;
        venue_id: string | null;
        season_id: string | null;
        start_time: string | null;
    };
    venues: { id: string; name: string }[];
    seasons: { id: string; name: string }[];
}

const props = defineProps<Props>();

const form = useForm({
    name: props.tournament.name,
    description: props.tournament.description ?? '',
    start_time: props.tournament.start_time ?? '',
    season_id: props.tournament.season_id ?? '',
    venue_id: props.tournament.venue_id ?? '',
});

const submit = (): void => { form.patch(`/poker/tournaments/${props.tournament.id}`); };
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" title="Edit Tournament" />
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
                        <!-- The season IS chosen here, unlike create: a tournament
                             entered against the wrong season is moved from this
                             page. No empty option -- it already has one. -->
                        <Field name="season_id" label="Season" :error="form.errors.season_id">
                            <select v-model="form.season_id" class="field__control"
                                    name="season_id" id="season_id" required>
                                <option v-for="season in seasons" :key="season.id" :value="season.id">
                                    {{ season.name }}
                                </option>
                            </select>
                        </Field>

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
