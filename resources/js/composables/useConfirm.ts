import { ref } from 'vue';

export type ConfirmTone = 'danger' | 'primary';

interface Pending {
    message: string;
    tone: ConfirmTone;
    resolve: (confirmed: boolean) => void;
}

/**
 * The app's confirmation question, as a promise.
 *
 * The Blade half intercepts form submissions carrying `data-confirm`. Vue has
 * no submission to intercept -- a component decides to act -- so this inverts
 * it: the caller asks, awaits an answer, and acts only on yes.
 *
 * Module-level state, deliberately. There is one dialog per page, mounted by
 * AppLayout, and a component anywhere in the tree must be able to reach it
 * without every layer in between passing it down.
 */
const pending = ref<Pending | null>(null);

export function useConfirm() {
    /**
     * Ask, and resolve to what the person said.
     *
     * Destructive unless told otherwise, matching the Blade rule: nearly every
     * one of these deletes something, so the exceptions declare themselves
     * rather than every deletion having to.
     */
    const ask = (message: string, tone: ConfirmTone = 'danger'): Promise<boolean> =>
        new Promise((resolve) => {
            // The same lock confirm.ts sets for the Blade half, and set in the
            // same place: the behaviour module, not the component. showModal()
            // makes the page inert but leaves it scrollable behind the dialog.
            document.body.classList.add('is-modal-open');

            pending.value = { message, tone, resolve };
        });

    /** Answer the open question. Anything but an explicit yes is a no. */
    const answer = (confirmed: boolean): void => {
        document.body.classList.remove('is-modal-open');

        pending.value?.resolve(confirmed);
        pending.value = null;
    };

    return { pending, ask, answer };
}
