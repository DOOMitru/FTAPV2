<script setup lang="ts">
import { computed, nextTick } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Alert from '../../../Components/Alert.vue';
import Field from '../../../Components/Field.vue';
import Btn from '../../../Components/Btn.vue';
import type { PageProps } from '../../../types/inertia';

interface Props {
    /** The place this entry will take, as an ordinal: "3rd". */
    next_place_label: string;
}

defineProps<Props>();

// computed, not a plain const: usePage().props.flash read once at setup is
// the flash that ARRIVED WITH THIS COMPONENT. Inertia reuses a component when
// a visit renders the same page, so every redirect back to the page you are on
// -- a delete returning to its listing, this form returning to itself -- left
// the old value in place and showed nothing. Measured here: the save worked,
// the place advanced, and the confirmation never appeared.
const flash = computed(() => usePage<PageProps>().props.flash);

const form = useForm({
    points: null as number | string | null,
});

/*
 * Both halves of "ready for the next place".
 *
 * reset(), because this form returns to itself: store() redirects back to
 * create with the next place already computed, and the points just entered
 * would otherwise still be sitting in the box ready to be saved twice. Blade
 * got that for free -- old() is only refilled after a FAILED submission.
 *
 * And the focus, by hand. autofocus focuses an input when it enters the
 * document, which happens once here: what comes back from a save is the same
 * component re-rendered, and the attribute does nothing the second time. So
 * the loop would have lost the thing it exists for -- typing the next figure
 * without reaching for the mouse -- on every entry after the first.
 */
const submit = (): void => {
    form.post('/poker/points-structure', {
        onSuccess: () => {
            form.reset();
            nextTick(() => document.getElementById('points')?.focus());
        },
    });
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="Setup" title="Add Place to Points Structure">
                <!-- In the header, not beside Save. This form returns to itself
                     after every save, so leaving is a different kind of act from
                     the entry being typed -- and the header is where this dashboard
                     already puts a page's own actions.

                     "Back", not "Cancel": by the second entry there is nothing left
                     to cancel, because the work is already saved. -->
                <template #actions>
                    <Btn variant="ghost" href="/poker/points-structure">Back</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <!-- The form comes back to itself after every save, so this is the only
                 place the confirmation can land. Without it a successful entry and
                 a form that did nothing look identical. -->
            <Alert v-if="flash.status" variant="success" :message="flash.status" />

            <Card>
                <form class="l-stack" @submit.prevent="submit">
                    <!-- Inlined: a fixed figure beside the one field there is
                         to fill in. The structure is a ladder from first place
                         down, so the next entry is always one deeper than the
                         deepest -- there is nothing here to decide. -->
                    <div class="field-row">
                        <div>
                            <span class="field__label">Place</span>
                            <p class="field__static">{{ next_place_label }}</p>
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
                                <Btn variant="primary" :disabled="form.processing">Save</Btn>
                            </div>
                        </div>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
