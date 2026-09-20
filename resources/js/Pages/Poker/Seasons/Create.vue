<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Field from '../../../Components/Field.vue';
import InputError from '../../../Components/InputError.vue';
import Btn from '../../../Components/Btn.vue';

const form = useForm({
    name: '',
    description: '',
    start_date: '',
    end_date: '',
    finale_points_required: null as number | string | null,
    finale_wins_required: null as number | string | null,
    finale_venue_points_required: null as number | string | null,
    is_current: false,
});

/*
 * is_current is dropped when it is false, which is what an unticked checkbox
 * did: a checkbox is only POSTed when it is ticked, and PokerSeason::booted()
 * defaults an UNSET is_current to true. So creating a season with the box
 * clear makes it current anyway. useForm sends every field every time, and
 * sending `false` here would quietly change that rule while converting the
 * page. The oddity is the model's and is left where it is.
 */
const submit = (): void => {
    form
        .transform((data) => {
            const { is_current: current, ...rest } = data;

            return current ? { ...rest, is_current: true } : rest;
        })
        .post('/poker/seasons');
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" title="Create Poker Season" />
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

                    <!-- One line above a phone, stacked below it. .l-grid rather
                         than a media query: it collapses when two 16rem tracks no
                         longer fit, and it is the same primitive the register form
                         uses to pair first and last name. A start and an end date
                         are one range read together, so a full stack step between
                         them separates two halves of a single fact. -->
                    <div class="l-grid">
                        <Field v-model="form.start_date" name="start_date" label="Start Date"
                               type="date" required :error="form.errors.start_date" />

                        <Field v-model="form.end_date" name="end_date" label="End Date"
                               type="date" required :error="form.errors.end_date" />
                    </div>

                    <!-- The finale rules. Optional on purpose: a season is often
                         created before its thresholds are decided, and an empty
                         field means NOT PUBLISHED rather than a target of zero. -->
                    <div>
                        <h3 class="card__title">Finale Qualification</h3>

                        <p class="field__hint">
                            A player must meet all three to reach the finale. Leave any of them
                            empty while the number is still being decided — an empty field is not
                            published, and is not enforced against anyone.
                        </p>
                    </div>

                    <Field v-model="form.finale_points_required" name="finale_points_required"
                           type="number" min="0" label="Season points required"
                           hint="Points accumulated across the season."
                           :error="form.errors.finale_points_required" />

                    <Field v-model="form.finale_wins_required" name="finale_wins_required"
                           type="number" min="0" label="Tournament wins required"
                           hint="Tournaments finished in first place."
                           :error="form.errors.finale_wins_required" />

                    <Field v-model="form.finale_venue_points_required" name="finale_venue_points_required"
                           type="number" min="0" label="Venue points required"
                           hint="Venue points earned at events dated inside this season."
                           :error="form.errors.finale_venue_points_required" />

                    <div>
                        <label class="field__check" for="is_current">
                            <input v-model="form.is_current" id="is_current" type="checkbox"
                                   name="is_current" value="1">
                            <span>Is Current Season</span>
                        </label>

                        <InputError :messages="form.errors.is_current" />
                    </div>

                    <div class="l-cluster l-cluster--end">
                        <Btn variant="ghost" href="/poker/seasons">Cancel</Btn>
                        <Btn variant="primary" :disabled="form.processing">Save</Btn>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
