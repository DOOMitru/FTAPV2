<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Field from '../../../Components/Field.vue';
import Btn from '../../../Components/Btn.vue';

interface Props {
    structure: {
        id: string;
        /** The place, as an ordinal: "3rd". It is shown, never edited. */
        place_label: string;
        points: number;
    };
}

const props = defineProps<Props>();

const form = useForm({
    points: props.structure.points as number | string,
});

const submit = (): void => { form.patch(`/poker/points-structure/${props.structure.id}`); };
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Setup" title="Edit Points Structure Entry" />
        </template>

        <div class="l-container">
            <Card>
                <form class="l-stack" @submit.prevent="submit">
                    <!-- Inlined: a fixed figure beside the one field there is
                         to fill in. Moving a place would collide with another or
                         leave a hole in the ladder; what an entry is worth is the
                         only thing about it that changes. -->
                    <div class="field-row">
                        <div>
                            <span class="field__label">Place</span>
                            <p class="field__static">{{ structure.place_label }}</p>
                        </div>

                        <div class="field-row__grow">
                            <Field v-model="form.points" name="points" label="Points"
                                   type="number" min="1"
                                   hint="Must be at least 1. A place worth nothing is a place left out of the structure."
                                   required autofocus :error="form.errors.points" />
                        </div>

                        <!-- On the row, not under it: two controls and their
                             actions are one line's worth of form. The empty label
                             reserves the space the labels beside it take, so the
                             buttons line up with the inputs rather than with the
                             words above them. -->
                        <div class="field-row__actions">
                            <span class="field__label" aria-hidden="true">&nbsp;</span>

                            <div class="l-cluster l-cluster--end">
                                <Btn variant="ghost" href="/poker/points-structure">Cancel</Btn>
                                <Btn variant="primary" :disabled="form.processing">Save</Btn>
                            </div>
                        </div>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
