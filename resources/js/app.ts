import Alpine from 'alpinejs';
import { initAutofill } from './autofill';
import { initConfirm } from './confirm';
import { initRecaptcha } from './recaptcha';
import { initTheme, toggleTheme } from './theme';
import { initToast } from './toast';

window.Alpine = Alpine;
Alpine.start();

// Kept on window so Blade can call it from an onclick during the phased
// conversion. Buttons carrying data-theme-toggle need no handler.
window.toggleTheme = toggleTheme;

document.addEventListener('DOMContentLoaded', initTheme);

// Destructive submissions confirm via data-confirm, never an inline
// handler -- see confirm.ts for why that distinction matters.
initConfirm();

// Admin forms carry a user's name into free-text fields; see autofill.ts.
initAutofill();

// The flash is rendered by layouts/public.blade.php and lands in the response;
// this only starts its timer and wires the dismiss button -- see toast.ts.
//
// On DOMContentLoaded, unlike initConfirm above: that one delegates from
// document and works before the body exists, while this one queries for the
// toast itself, and this bundle is loaded from <head>.
document.addEventListener('DOMContentLoaded', initToast);

// reCAPTCHA v3 scores the submission instead of asking for a click, so the
// token has to be fetched as the form is sent -- see recaptcha.ts.
document.addEventListener('DOMContentLoaded', initRecaptcha);

// No Vue here. This entry serves the Blade half of the app -- the public pages
// and the screens not yet converted -- and Inertia pages load inertia-app.ts
// instead. Importing the Vue bootstrap here would put it in the bundle every
// public page downloads.
