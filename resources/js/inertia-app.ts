import { initInertia } from './inertia';

/**
 * The entry for Inertia pages, and only for them.
 *
 * Deliberately separate from app.ts. A single entry importing both halves put
 * Vue and Inertia into the bundle that `/`, `/events`, `/rules/*` and the auth
 * screens download -- 214 kB to render server-side HTML that never uses a line
 * of it. Protecting those pages is the entire reason the conversion is a
 * hybrid, so the split is not an optimisation, it is the point.
 *
 * What is NOT here, and why:
 *
 *   - Alpine. An Inertia page has Vue; Alpine is for the Blade half.
 *   - initTheme(). It binds click handlers by querying the DOM once at load.
 *     Vue renders the toggle after mount and replaces it on every navigation,
 *     so a one-shot query would bind nothing. Vue components import
 *     toggleTheme and call it directly. The pre-paint script in app.blade.php
 *     still stamps data-theme, so the theme itself is applied either way.
 *   - confirm.ts. Dialogs on an Inertia page are Vue's.
 *   - recaptcha.ts. /register is Blade and staying that way.
 */
initInertia();
