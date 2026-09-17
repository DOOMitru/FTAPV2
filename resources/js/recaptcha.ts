/**
 * Google reCAPTCHA v3 on a form.
 *
 * v3 has nothing to click. Google scores the submission instead, and the token
 * that carries the score has to be fetched at the moment of submitting: it
 * expires two minutes after it is issued, so a token taken at page load is
 * stale on any form somebody fills in slowly.
 *
 * Markup contract:
 *
 *     <form data-recaptcha="SITE_KEY" data-recaptcha-action="register">
 *       <input type="hidden" name="g-recaptcha-response">
 *
 * The key travels on a data attribute rather than being written into a script
 * tag -- the same rule as confirm.ts and autofill.ts, and for the same reason:
 * this project keeps JavaScript out of the document.
 *
 * The form ALWAYS submits. If the script is blocked, or Google hangs, or the
 * promise rejects, it goes with an empty token and the server refuses it with
 * a message and a retry. The alternative -- a submit handler that quietly does
 * nothing -- is a button that appears broken, which is worse than being told
 * the check failed.
 */

type Grecaptcha = {
    ready: (callback: () => void) => void;
    execute: (siteKey: string, options: { action: string }) => Promise<string>;
};

/** Long enough for a slow connection, short enough not to read as a hang. */
const TIMEOUT_MS = 5000;

function tokenFor(api: Grecaptcha, siteKey: string, action: string): Promise<string> {
    const scored = new Promise<string>((resolve) => {
        api.ready(() => {
            api.execute(siteKey, { action }).then(resolve, () => resolve(''));
        });
    });

    // Raced, not chained: ready() takes a callback and execute() returns a
    // promise, and neither is guaranteed to settle. Without this a form whose
    // grecaptcha never answers has no way to submit at all.
    const timeout = new Promise<string>((resolve) => {
        window.setTimeout(() => resolve(''), TIMEOUT_MS);
    });

    return Promise.race([scored, timeout]);
}

function bind(form: HTMLFormElement): void {
    const siteKey = form.dataset.recaptcha;
    const action = form.dataset.recaptchaAction ?? 'submit';
    const field = form.querySelector<HTMLInputElement>('input[name="g-recaptcha-response"]');

    if (!siteKey || !field) {
        return;
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const api = window.grecaptcha;

        // form.submit(), not requestSubmit(): submit() fires no submit event,
        // so this handler cannot re-enter and loop. It also skips constraint
        // validation, which is correct here -- the browser already ran it, or
        // this event would never have fired.
        if (!api) {
            form.submit();

            return;
        }

        tokenFor(api, siteKey, action).then((token) => {
            field.value = token;
            form.submit();
        });
    });
}

export function initRecaptcha(): void {
    document.querySelectorAll<HTMLFormElement>('form[data-recaptcha]').forEach(bind);
}
