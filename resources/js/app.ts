import Alpine from 'alpinejs';
import { initConfirm } from './confirm';
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
