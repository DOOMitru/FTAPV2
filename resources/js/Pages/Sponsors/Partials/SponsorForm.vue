<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Field from '../../../Components/Field.vue';
import Btn from '../../../Components/Btn.vue';

export interface Sponsor {
    id: string;
    name: string;
    website_url: string | null;
    tier: string;
    sort_order: number;
    logo_url: string;
}

interface Props {
    /** The sponsor being edited, or null when one is being added. */
    sponsor: Sponsor | null;
}

const props = defineProps<Props>();

const form = useForm({
    name: props.sponsor?.name ?? '',
    logo: null as File | null,
    website_url: props.sponsor?.website_url ?? '',
    tier: props.sponsor?.tier ?? 'regular',
    sort_order: (props.sponsor?.sort_order ?? 0) as number | string,
});

/*
 * The upload is what shapes this.
 *
 * The Blade form's own comment calls enctype "load-bearing": without it the
 * browser sends the field names and no file, so validation reports a missing
 * logo and the form gives no clue why. Inertia's version of that is a File in
 * the form data, which it sends as multipart on its own.
 *
 * Which is also why an edit POSTs rather than PUTs. PHP does not parse a
 * multipart body on PUT or PATCH -- the file would simply not arrive -- so the
 * method is spoofed in the body, exactly as @method('PUT') does.
 */
const submit = (): void => {
    const sponsor = props.sponsor;

    if (sponsor === null) {
        form.post('/sponsors');

        return;
    }

    form.transform((data) => ({ ...data, _method: 'put' })).post(`/sponsors/${sponsor.id}`);
};

const takeFile = (event: Event): void => {
    form.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
};
</script>

<template>
    <form class="l-stack" @submit.prevent="submit">
        <Field v-model="form.name" name="name" label="Sponsor Name"
               required autofocus :error="form.errors.name" />

        <!-- Through the slot: a file input cannot carry a value, so it is
             neither v-model-able nor renderable by Field's own <input>. -->
        <Field name="logo" :label="sponsor ? 'Replace Logo' : 'Logo'" :error="form.errors.logo">
            <input id="logo" name="logo" type="file" class="field__control"
                   :required="sponsor === null" @input="takeFile">
        </Field>

        <p v-if="sponsor" class="field__hint">Leave this empty to keep the current logo.</p>

        <Field v-model="form.website_url" name="website_url" type="url" label="Website (optional)"
               placeholder="https://example.com" :error="form.errors.website_url" />

        <Field name="tier" label="Tier" :error="form.errors.tier">
            <select v-model="form.tier" class="field__control" name="tier" id="tier" required>
                <option value="regular">Regular Sponsor</option>
                <option value="premium">Premium Sponsor</option>
            </select>
        </Field>

        <Field v-model="form.sort_order" name="sort_order" type="number" label="Sort Order"
               :error="form.errors.sort_order" />

        <p class="field__hint">
            Lower numbers appear first, within a tier. Premium sponsors always come before
            regular ones.
        </p>

        <div class="l-cluster l-cluster--end">
            <Btn variant="ghost" href="/sponsors">Cancel</Btn>
            <Btn variant="primary" :disabled="form.processing">{{ sponsor ? 'Save' : 'Add Sponsor' }}</Btn>
        </div>
    </form>
</template>
