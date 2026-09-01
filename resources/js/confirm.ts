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
 * of.
 *
 * Delegated from the document, so it covers forms rendered after load and every
 * index page gets it without repeating a handler.
 */
export function initConfirm(): void {
    document.addEventListener('submit', (event) => {
        const form = (event.target as HTMLElement | null)?.closest<HTMLFormElement>('form[data-confirm]');

        if (! form) {
            return;
        }

        if (! window.confirm(form.dataset.confirm ?? '')) {
            event.preventDefault();
        }
    });
}
