/**
 * The public site's toasts.
 *
 * The markup is rendered by layouts/public.blade.php, not by this file -- the
 * same arrangement confirm.ts has with confirm-dialog.blade.php. emph_split
 * runs in PHP where it already does, the live-region role is in the response
 * rather than assigned after mount, and the message is on the page with
 * JavaScript switched off. All this adds is the timer and the way out.
 *
 * The Vue half does the same job in Toast.vue. The two are not shared because
 * there is nothing to share: one is a component with a lifecycle and the other
 * is a handful of listeners over server-rendered markup.
 */

/** Five seconds, the same figure Toast.vue uses. */
const DISMISS_AFTER_MS = 5000;

export function initToast(): void {
    document.querySelectorAll<HTMLElement>('[data-toast]').forEach((host) => {
        const dismiss = (): void => host.remove();

        host.querySelector<HTMLButtonElement>('[data-toast-dismiss]')
            ?.addEventListener('click', dismiss);

        /*
         * Success goes; a refusal stays.
         *
         * Asked of the markup rather than of the variant class: what the
         * stylesheet calls a colour is not this file's business, and "this one
         * waits to be read" is the actual rule. A danger toast on the public
         * site is a registration turned away, which is the message somebody is
         * most likely to have looked away from.
         */
        if (host.querySelector('[data-toast-persist]') === null) {
            window.setTimeout(dismiss, DISMISS_AFTER_MS);
        }
    });
}
