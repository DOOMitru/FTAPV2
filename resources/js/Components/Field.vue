<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    name: string;
    label: string;
    /**
     * A number field's value is a number: the seasons form's three thresholds
     * are ints, and Blade put them in the input as ints. The emit stays a
     * string, because that is what an <input> gives back.
     */
    modelValue?: string | number | null;
    type?: string;
    required?: boolean;
    hint?: string | null;
    /** Put the hint beside the label rather than under the control. */
    hintInline?: boolean;
    /** Overrides the id, which follows the name otherwise. */
    id?: string | null;
    error?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    type: 'text',
    required: false,
    hint: null,
    hintInline: false,
    id: null,
    error: null,
});

defineEmits<{ 'update:modelValue': [value: string] }>();

// Anything the caller passes that is not a prop above -- autocomplete,
// autofocus, placeholder -- belongs on the control, not on the wrapper <div>
// Vue would otherwise put it on. This is the Blade twin's
// $attributes->merge(['class' => 'field__control']), which lands on the input
// for the same reason; it is the one component in the set whose attributes
// have somewhere to go other than its root.
defineOptions({ inheritAttrs: false });

// The id follows the name unless a caller overrides it, and a caller has to
// when the same field name appears twice on one page -- /profile has two
// `password` fields, and two inputs with one id make a label point at
// whichever comes first. DuplicateElementIdTest guards it.
const controlId = computed(() => props.id ?? props.name);

// A note beside the label is still the control's description, so it is pointed
// at rather than merely placed next to it.
const noted = computed(() => props.hint !== null && props.hintInline);
const noteId = computed(() => (noted.value ? `${controlId.value}-note` : undefined));
</script>

<template>
    <div>
        <div v-if="noted" class="field__label-row">
            <label class="field__label" :for="controlId">{{ label }}</label>
            <span class="field__note" :id="noteId">{{ hint }}</span>
        </div>
        <label v-else class="field__label" :for="controlId">{{ label }}</label>

        <!-- Non-text controls go through the slot, styled by the caller. -->
        <slot>
            <input
                v-bind="$attrs"
                :id="controlId"
                :name="name"
                :type="type"
                :value="modelValue"
                :required="required"
                :aria-describedby="noteId"
                class="field__control"
                @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            >
        </slot>

        <!-- Under the control unless asked for beside the label; both would say
             the same thing twice. -->
        <p v-if="hint && ! hintInline" class="field__hint">{{ hint }}</p>

        <!--
            Rendered here rather than delegated to InputError. The two are not
            interchangeable: this is a <p> and InputError is a <ul><li>, both
            styled .field__error. Composing the other one changed the element a
            field's error arrives in, which ComponentParityTest caught by
            noticing the hook had left this file.
        -->
        <p v-if="error" class="field__error">{{ error }}</p>
    </div>
</template>
