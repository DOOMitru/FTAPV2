import Alpine from 'alpinejs';
import { initAutofill } from './autofill';
import { initConfirm } from './confirm';
import { initDependentSelects } from './dependent-select';
import { initRecaptcha } from './recaptcha';
import { initTheme, toggleTheme } from './theme';

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

// The result form's player list depends on the chosen tournament.
document.addEventListener('DOMContentLoaded', initDependentSelects);

// reCAPTCHA v3 scores the submission instead of asking for a click, so the
// token has to be fetched as the form is sent -- see recaptcha.ts.
document.addEventListener('DOMContentLoaded', initRecaptcha);
