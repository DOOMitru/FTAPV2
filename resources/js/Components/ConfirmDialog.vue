<script setup lang="ts">
import { ref, watch } from 'vue';
import Emph from './Emph.vue';
import Btn from './Btn.vue';
import { useConfirm } from '../composables/useConfirm';

const { pending, answer } = useConfirm();
const dialog = ref<HTMLDialogElement | null>(null);

/*
 * A native <dialog> opened with showModal(), for everything that buys and a
 * hand-rolled overlay has to reimplement and usually gets subtly wrong: focus
 * moves in and is trapped, Escape closes it, the rest of the page goes inert to
 * the mouse and to assistive technology, and it renders in the top layer so
 * nothing on the page can stack above it.
 */
watch(pending, (value) => {
    const element = dialog.value;

    if (element === null) {
        return;
    }

    if (value === null) {
        element.close();

        return;
    }

    element.showModal();
});

// Escape and the backdrop both land here. Neither is a yes. The body lock is
// released by answer(), in the composable, for the same reason confirm.ts
// releases it rather than the Blade component: it is behaviour, not markup.
const onClose = (): void => {
    if (pending.value !== null) {
        answer(false);
    }
};

// An open modal <dialog> covers the viewport for hit-testing and paints its own
// backdrop, so a click whose target IS the dialog, rather than anything inside
// it, is a click outside the panel.
const onClick = (event: MouseEvent): void => {
    if (event.target === dialog.value) {
        dialog.value?.close();
    }
};
</script>

<template>
    <dialog ref="dialog" class="confirm" aria-labelledby="confirm-dialog-message"
            @close="onClose" @click="onClick">
        <form method="dialog">
            <p class="confirm__message" id="confirm-dialog-message">
                <Emph v-if="pending" :message="pending.message" />
            </p>

            <div class="confirm__actions">
                <!--
                    Explicit type="submit": Btn gives ghost a type="button" so a
                    dismiss cannot post a form by accident, and inside a dialog
                    form that is precisely the button that would fail to close
                    anything.
                -->
                <Btn variant="ghost" type="submit" value="cancel" @click="answer(false)">Cancel</Btn>

                <!--
                    The variant follows the tone. Most of these actions are
                    destructive, but not all, and the browser's own dialog had
                    no colour to get wrong where a styled one does.
                -->
                <Btn :variant="pending?.tone === 'primary' ? 'primary' : 'danger'"
                     type="submit" value="confirm" @click="answer(true)">Confirm</Btn>
            </div>
        </form>
    </dialog>
</template>
