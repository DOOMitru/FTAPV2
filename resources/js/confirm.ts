/**
 * Confirmation for destructive form submissions.
 *
 * The message lives in a `data-confirm` attribute and is read through
 * `dataset`, never interpolated into an inline handler. That distinction is the
 * whole point of this file.
 *
 * `onsubmit="return confirm('{{ $model->name }}')"` looks safe because Blade
 * escapes the name for HTML — but the browser HTML-decodes an attribute before
 * handing its contents to the JS parser, so a name containing an apostrophe
 * closes the string literal and everything after it executes. A season called
 *
 *     '); alert(document.cookie); //
 *
 * becomes exactly that script. Reading the value through `dataset` keeps it a
 * JS *value* rather than JS *source*, so there is no parsing step to escape out
 * of. The dialog writes it with `textContent` for the same reason.
 *
 * Delegated from the document, so it covers forms rendered after load and every
 * index page gets it without repeating a handler.
 *
 * The dialog is asynchronous where `window.confirm` was not, so the submit is
 * always stopped and started again once an answer comes back — see `approved`
 * below.
 */

/**
 * Forms whose confirmation has been answered yes and which are being submitted
 * again because of it.
 *
 * A WeakSet rather than an attribute on the form: it is a fact about this
 * moment, not about the document, and nothing should be able to skip a
 * confirmation by arriving with the right markup on it.
 */
const approved = new WeakSet<HTMLFormElement>();

/** Bound once, on whichever dialog the page turns out to have. */
let backdropBound = false;

function bindBackdrop(dialog: HTMLDialogElement): void {
    if (backdropBound) {
        return;
    }

    backdropBound = true;

    dialog.addEventListener('click', (event) => {
        // An open modal <dialog> covers the viewport for hit-testing, and the
        // backdrop is painted by the element itself. So a click whose target IS
        // the dialog, rather than anything inside it, is a click outside the
        // panel. Escape and this both leave returnValue empty, which reads as
        // "not confirmed".
        if (event.target === dialog) {
            dialog.close();
        }
    });
}

export function initConfirm(): void {
    document.addEventListener('submit', (event) => {
        const form = (event.target as HTMLElement | null)?.closest<HTMLFormElement>('form[data-confirm]');

        if (! form) {
            return;
        }

        // The second pass, after Confirm. Cleared as it is read, so asking
        // again is required the next time this same form is submitted.
        if (approved.has(form)) {
            approved.delete(form);

            return;
        }

        event.preventDefault();

        const message = form.dataset.confirm ?? '';

        // Carried through the round trip so the form posts exactly what it
        // would have. None of today's callers name their submit button, but a
        // confirmation that quietly drops a button's value would be a hard
        // thing to find later.
        const submitter = (event as SubmitEvent).submitter as HTMLButtonElement | HTMLInputElement | null;

        const send = (): void => {
            approved.add(form);
            form.requestSubmit(submitter);
        };

        const dialog = document.querySelector<HTMLDialogElement>('[data-confirm-dialog]');

        // No dialog on this page, or a browser without one: ask the way this
        // used to. Silently letting a delete through is not an acceptable way
        // to degrade.
        if (! dialog || typeof dialog.showModal !== 'function') {
            if (window.confirm(message)) {
                send();
            }

            return;
        }

        const text = dialog.querySelector<HTMLElement>('[data-confirm-message]');
        const accept = dialog.querySelector<HTMLElement>('[data-confirm-accept]');

        if (text) {
            text.textContent = message;
        }

        if (accept) {
            // Destructive unless the caller says otherwise. Nearly every one of
            // these deletes something; the exceptions say so with
            // data-confirm-tone="primary" rather than every deletion having to
            // declare itself.
            const calm = form.dataset.confirmTone === 'primary';

            accept.classList.toggle('btn--danger', ! calm);
            accept.classList.toggle('btn--primary', calm);
        }

        // returnValue survives a previous open, so a dialog confirmed once
        // would confirm every time after it if this were left alone.
        dialog.returnValue = '';

        dialog.addEventListener('close', () => {
            document.body.classList.remove('is-modal-open');

            // Set by the Confirm button through method="dialog". Escape, the
            // backdrop and Cancel all leave something else.
            if (dialog.returnValue === 'confirm') {
                send();
            }
        }, { once: true });

        bindBackdrop(dialog);

        // The same lock .modal sets, because showModal() makes the page inert
        // but leaves it scrollable behind the dialog.
        document.body.classList.add('is-modal-open');

        dialog.showModal();
    });
}
