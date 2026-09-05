import Alpine from 'alpinejs';
import { initAutofill } from './autofill';
import { initConfirm } from './confirm';
import { initDependentSelects } from './dependent-select';
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
