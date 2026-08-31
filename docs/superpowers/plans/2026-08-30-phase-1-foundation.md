# Phase 1 — Design System Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete "Under the Gun" design system — tokens, typography, layout primitives, components and both shells — and prove it by converting one real page.

**Architecture:** Semantic BEM-lite components driven by CSS custom properties, in a cascade-ordered import chain. Theming moves from Tailwind's `.dark` class to `data-theme` on `<html>`. The new system is built *alongside* Tailwind so the app stays styled while Phases 2–5 convert views; Tailwind is uninstalled at the end of Phase 5.

**Tech Stack:** Laravel 12 Blade, Vite 7, PostCSS (`postcss-import` + `autoprefixer`), Alpine.js 3. No CSS framework.

**Spec:** `docs/superpowers/specs/2026-08-30-design-system-design.md` (sections 3, 6, 7)

**Prerequisite:** Phase 0 complete, all exit criteria met.

## Global Constraints

- **Never run `git` commands.** Every task ends with a **Checkpoint** naming files to stage and a suggested message — present it, do not execute it.
- **No inline CSS.** The only permitted `style` attribute is one that sets *only* custom properties: `style="--meter-fill: 86%"`. No `style="width: 86%"`, ever.
- **Component selectors are single-class.** `.card`, `.card__header`, `.card--flush`. No element selectors for components. No nesting beyond state (`:hover`, `:focus-visible`) and modifier.
- **Components never set outer margin.** Spacing belongs to layout primitives only.
- **Utilities are capped at 15**, all `.u-` prefixed. Exceeding the cap means the component layer is wrong.
- Every colour is defined on bare `:root` first. No colour may exist only inside a media query or `[data-theme]` block.
- Focus is never removed without replacement: `outline: 2px solid var(--c-primary); outline-offset: 2px`.
- Copy follows spec §7: "Dashboard" not "Deck", "Standings" not "Chip count", active voice, errors state what happened and how to fix it.
- **Deviation from spec §6.9, deliberate:** Tailwind is *not* uninstalled in this phase. See Task 1.
- **Pagination styling is deferred to Phase 5, deliberately.** Eight admin index views call `->links()`, which renders Laravel's built-in pagination markup — it emits Tailwind classes, never `.pagination__link`. Shipping a `_pagination.css` in this phase would be dead CSS with no consumer, the same defect the Task 5 review flagged in `.field__checkbox`. Phase 5 converts those eight views and must, in the same task: publish the pagination view (`php artisan vendor:publish --tag=laravel-pagination`), rewrite it onto `.pagination` / `.pagination__link`, and add the stylesheet. Views affected: `users/index`, `poker/{seasons,venues,tournaments,results,registrants,venue-points,points-structure}/index`.
- **Breakpoints are raw values, deliberately, and they collapse in a fixed order.** A CSS custom property cannot be used in a media query condition (`@media (max-width: var(--bp))` is invalid), so these values agree only by convention:

  | Value | Where | Collapses |
  |---|---|---|
  | `60rem` (960px) | `.l-sidebar` (Task 4) | two-column content → one column |
  | `56.25rem` (900px) | `.shell__rail` (Task 8) | left rail → off-canvas drawer |
  | `48rem` (768px) | `.public__bar` (Task 9) | top bar row → stacked |

  The order is intentional: `poker/seasons/show` nests a `.l-sidebar` inside the app shell, so the inner layout must simplify *before* the shell itself changes shape. Do not "tidy" these into one value — that would collapse both at once and produce a worse intermediate state. Do not add a breakpoint without extending this table.


### Palette (spec §6.1) — copy these values exactly

| Token | Dark | Light |
|---|---|---|
| `--c-bg` | `#0B0F19` | `#F4F7FC` |
| `--c-surface` | `#161F33` | `#FFFFFF` |
| `--c-surface-raised` | `#1C2740` | `#EEF2F9` |
| `--c-border` | `#1E293B` | `#E2E8F0` |
| `--c-text` | `#E2E8F0` | `#0F172A` |
| `--c-text-muted` | `#94A3B8` | `#475569` |
| `--c-primary` | `#06B6D4` | `#1E40AF` |
| `--c-primary-hover` | `#22D3EE` | `#1E3A8A` |
| `--c-primary-ink` | `#0B0F19` | `#FFFFFF` |
| `--c-accent` | `#EF4537` | `#EF4537` |
| `--c-accent-strong` | `#D63A2C` | `#D63A2C` |
| `--c-accent-hover` | `#B93225` | `#B93225` |

**Contrast rule that must not be broken:** white on `#EF4537` is 3.8:1 and fails AA for normal text. `--c-accent` is for fills behind *large or bold* text, borders and icons only. Any control with a small text label on a coral ground uses `--c-accent-strong` (4.7:1). Destructive buttons therefore use `--c-accent-strong` as their base.

---

## File Structure

```
resources/css/
  app.css                        entry; imports in cascade order
  1-base/
    _tokens.css                  :root palette, spacing, radii, type scale
    _typography.css              @font-face, base type, display/mono helpers
    _reset.css                   added in Phase 5, when Tailwind preflight goes
  2-layout/
    _primitives.css              .l-container .l-stack .l-cluster .l-grid .l-sidebar
    _shell-app.css               left rail + main, authenticated area
    _shell-public.css            top bar + main, marketing area
  3-components/
    _btn.css _card.css _form.css _table.css _badge.css _meter.css
    _stat.css _alert.css _nav.css _dropdown.css _modal.css
    _empty.css _avatar.css _rank.css   (_pagination.css deferred to Phase 5)
  4-pages/
    _season-show.css             first page converted; one file per page thereafter

resources/js/
  theme.ts                       data-theme read/write, toggle, aria-pressed

public/fonts/                    self-hosted woff2

resources/views/components/
  theme-script.blade.php  btn.blade.php  card.blade.php  field.blade.php
  alert.blade.php  badge.blade.php  meter.blade.php  stat.blade.php
  table.blade.php  rank.blade.php  page-header.blade.php
  empty-state.blade.php  avatar.blade.php

tests/Feature/
  InlineStyleGuardTest.php       enforces the no-inline-CSS rule in CI
```

---

## Task 1: Build pipeline and CSS skeleton

**Files:**
- Modify: `postcss.config.js`, `package.json`, `resources/css/app.css`
- Create: the `1-base/`, `2-layout/`, `3-components/`, `4-pages/` directories

**Interfaces:**
- Consumes: nothing
- Produces: a working `@import` chain. Every later task adds files to it.

**Why Tailwind stays for now.** Uninstalling it here would leave the site unstyled through Phases 2–5 — roughly 60 views' worth of conversion with no working design. Instead `app.css` keeps the Tailwind directives at the top and appends the new system below, so converted and unconverted views both render correctly. The uninstall moves to the end of Phase 5. Two consequences to accept: the dev bundle is temporarily larger, and `_reset.css` is deferred because Tailwind's preflight already resets and the two would fight.

- [ ] **Step 1: Add postcss-import**

```bash
npm install --save-dev postcss-import
```

Note: `postcss-import` is already present at 15.1.0 as a transitive dependency, so this promotes it to a direct devDependency rather than downloading anything new.

- [ ] **Step 2: Register it**

Replace `postcss.config.js` entirely:

```js
export default {
    plugins: {
        'postcss-import': {},
        tailwindcss: {},
        autoprefixer: {},
    },
};
```

`postcss-import` must run first so `@import` is inlined before Tailwind processes the file.

- [ ] **Step 3: Create the directories**

```bash
mkdir -p resources/css/1-base resources/css/2-layout resources/css/3-components resources/css/4-pages
```

- [ ] **Step 4: Rewrite the entry**

Replace `resources/css/app.css` entirely:

```css
/* First To Act Poker design system. Cascade order within the imports matters:
   tokens define the variables everything else reads, layout owns spacing,
   components own appearance, pages own the few one-off rules that belong
   nowhere else.

   These @import statements MUST come before the @tailwind directives. CSS
   requires @import to precede all other statements, and postcss-import
   enforces it by SILENTLY SKIPPING any @import that follows one — it emits a
   warning ("@import must precede all other statements") and the build still
   succeeds. Putting these after @tailwind would therefore leave the entire
   design system unloaded with a green build and no error. Verified
   empirically before this plan was executed. */
@import "./1-base/_tokens.css";
@import "./1-base/_typography.css";

/* Tailwind — temporary. Removed at the end of Phase 5, once every view is
   converted. Until then it styles the views this phase has not reached.
   It loads after the design system, so on the rare selector both target,
   a Tailwind utility wins. That is correct during the transition: unconverted
   views must keep rendering exactly as they do today. */
@tailwind base;
@tailwind components;
@tailwind utilities;
```

Every later task appends its `@import` to the block at the top of the file, never below the `@tailwind` directives.

- [ ] **Step 5: Verify the build**

Run: `npm run build`
Expected: succeeds. `public/build/assets/*.css` exists.

Run: `php artisan test`
Expected: all green.

This will fail until Task 2 creates the two imported files. Create empty placeholder files to confirm the pipeline first:

```bash
: > resources/css/1-base/_tokens.css
: > resources/css/1-base/_typography.css
npm run build
```
Expected: succeeds.

- [ ] **Step 6: Checkpoint — hand off for commit**

Stage: `package.json package-lock.json postcss.config.js resources/css/`
Suggested message: `build: add postcss-import and the design system css skeleton`

---

## Task 2: Token layer and theming

**Files:**
- Modify: `resources/css/1-base/_tokens.css`
- Create: `resources/js/theme.ts`, `resources/views/components/theme-script.blade.php`
- Modify: `resources/js/app.ts`, `tailwind.config.js`
- Modify: `resources/views/layouts/app.blade.php:17-33`, `resources/views/layouts/public.blade.php:17-33`

**Interfaces:**
- Consumes: the import chain from Task 1
- Produces: every `--c-*`, `--space-*`, `--step-*`, `--radius*`, `--font-*` token; `<x-theme-script />`; `window.toggleTheme()`

- [ ] **Step 1: Write the tokens**

Replace `resources/css/1-base/_tokens.css`:

```css
/* Design tokens.
 *
 * The light palette is the base definition on bare :root, so every colour has
 * a value with no media query or attribute selector in play. Dark values are
 * declared once as --dark-* and mapped twice: under prefers-color-scheme for
 * viewers who never chose, and under [data-theme="dark"] so an explicit choice
 * beats the system in both directions.
 */

:root {
    color-scheme: light;

    /* Light — Frosted Ice */
    --c-bg: #F4F7FC;
    --c-surface: #FFFFFF;
    --c-surface-raised: #EEF2F9;
    --c-border: #E2E8F0;
    --c-text: #0F172A;
    --c-text-muted: #475569;
    --c-primary: #1E40AF;
    --c-primary-hover: #1E3A8A;
    --c-primary-ink: #FFFFFF;

    /* Coral is shared by both themes. --c-accent is for fills behind large or
       bold text, borders and icons. White on #EF4537 is 3.8:1 and fails AA for
       normal text, so anything with a small label uses --c-accent-strong. */
    --c-accent: #EF4537;
    --c-accent-strong: #D63A2C;
    --c-accent-hover: #B93225;
    --c-accent-ink: #FFFFFF;

    /* Dark — Cyber Navy. Declared here, applied below. */
    --dark-bg: #0B0F19;
    --dark-surface: #161F33;
    --dark-surface-raised: #1C2740;
    --dark-border: #1E293B;
    --dark-text: #E2E8F0;
    --dark-text-muted: #94A3B8;
    --dark-primary: #06B6D4;
    --dark-primary-hover: #22D3EE;
    --dark-primary-ink: #0B0F19;

    /* Spacing — 4px base */
    --space-1: 0.25rem;
    --space-2: 0.5rem;
    --space-3: 0.75rem;
    --space-4: 1rem;
    --space-5: 1.5rem;
    --space-6: 2rem;
    --space-8: 3rem;
    --space-10: 4rem;
    --space-12: 6rem;

    /* Radii — tight, to read as instrument panelling rather than soft cards */
    --radius-sm: 3px;
    --radius: 6px;
    --radius-pill: 999px;

    --border-width: 1px;

    /* The only shadow in the system. Dropdowns and modals float; nothing else
       does. Every other separation is a 1px border. */
    --shadow-overlay: 0 12px 32px -8px rgb(11 15 25 / 0.28);

    /* Type */
    --font-display: "Archivo Expanded", "Archivo", system-ui, sans-serif;
    --font-body: "Archivo", system-ui, sans-serif;
    --font-mono: "IBM Plex Mono", ui-monospace, "SFMono-Regular", monospace;

    --step--2: 0.6875rem;
    --step--1: 0.8125rem;
    --step-0: 0.9375rem;
    --step-1: 1.125rem;
    --step-2: 1.5rem;
    --step-3: 2rem;
    --step-4: clamp(2.5rem, 5vw, 3.5rem);
    --step-5: clamp(3rem, 8vw, 5.5rem);

    --leading-tight: 1.15;
    --leading-normal: 1.55;

    --transition-fast: 140ms ease;
    --transition-reveal: 480ms cubic-bezier(0.2, 0.7, 0.3, 1);

    --rail-width: 240px;
    --measure: 68ch;
}

@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
        color-scheme: dark;
        --c-bg: var(--dark-bg);
        --c-surface: var(--dark-surface);
        --c-surface-raised: var(--dark-surface-raised);
        --c-border: var(--dark-border);
        --c-text: var(--dark-text);
        --c-text-muted: var(--dark-text-muted);
        --c-primary: var(--dark-primary);
        --c-primary-hover: var(--dark-primary-hover);
        --c-primary-ink: var(--dark-primary-ink);
    }
}

:root[data-theme="dark"] {
    color-scheme: dark;
    --c-bg: var(--dark-bg);
    --c-surface: var(--dark-surface);
    --c-surface-raised: var(--dark-surface-raised);
    --c-border: var(--dark-border);
    --c-text: var(--dark-text);
    --c-text-muted: var(--dark-text-muted);
    --c-primary: var(--dark-primary);
    --c-primary-hover: var(--dark-primary-hover);
    --c-primary-ink: var(--dark-primary-ink);
}

/* Alpine hides these until it initialises. Replaces the style="display: none"
   attributes previously used in dropdown and public shell markup. */
[x-cloak] {
    display: none !important;
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
```

- [ ] **Step 2: Write the theme module**

Create `resources/js/theme.ts`:

```ts
type Theme = 'dark' | 'light';

const STORAGE_KEY = 'theme';

function systemPrefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function storedTheme(): Theme | null {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        return value === 'dark' || value === 'light' ? value : null;
    } catch {
        // Private browsing, or site data blocked. Fall back to the system.
        return null;
    }
}

export function currentTheme(): Theme {
    return storedTheme() ?? (systemPrefersDark() ? 'dark' : 'light');
}

export function applyTheme(theme: Theme): void {
    document.documentElement.setAttribute('data-theme', theme);

    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Nothing to do — the attribute still applies for this page view.
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((el) => {
        el.setAttribute('aria-pressed', String(theme === 'dark'));
    });
}

export function toggleTheme(): void {
    applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
}

export function initTheme(): void {
    // Sync aria-pressed with whatever the pre-paint script already applied.
    const theme = currentTheme();

    document.querySelectorAll('[data-theme-toggle]').forEach((el) => {
        el.setAttribute('aria-pressed', String(theme === 'dark'));
        el.addEventListener('click', toggleTheme);
    });
}
```

- [ ] **Step 3: Wire it into `app.ts`**

Replace `resources/js/app.ts`:

```ts
import Alpine from 'alpinejs';
import { initTheme, toggleTheme } from './theme';

window.Alpine = Alpine;
Alpine.start();

// Kept on window so Blade can call it from an onclick during the phased
// conversion. Buttons carrying data-theme-toggle need no handler.
window.toggleTheme = toggleTheme;

document.addEventListener('DOMContentLoaded', initTheme);
```

There is deliberately no `import './bootstrap'` line: `resources/js/bootstrap.ts` existed
only to put `axios` on `window`, and both it and the `axios` dependency were removed after
this plan was written. `resources/js/` now contains exactly `app.ts` and `types/global.d.ts`.

`resources/js/types/global.d.ts` already declares `window.Alpine`. **Add** `toggleTheme` to
that existing interface — do not replace the file, or the Alpine declaration is lost:

```ts
import type Alpine from 'alpinejs';

declare global {
    interface Window {
        Alpine: typeof Alpine;
        toggleTheme: () => void;
    }
}
```

- [ ] **Step 4: Create the pre-paint script component**

Create `resources/views/components/theme-script.blade.php`:

```blade
{{--
    Runs before first paint to avoid a flash of the wrong theme. It only
    restores an explicit choice — with no stored choice the CSS falls through
    to prefers-color-scheme on its own.
--}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('theme');
            if (stored === 'dark' || stored === 'light') {
                document.documentElement.setAttribute('data-theme', stored);
            }
        } catch (e) {
            // Site data blocked. prefers-color-scheme still applies.
        }
    })();
</script>
```

- [ ] **Step 5: Replace the duplicated scripts in both layouts**

In `resources/views/layouts/app.blade.php` and `resources/views/layouts/public.blade.php`, delete the entire `<script>` block (the `localStorage.theme` / `classList.add('dark')` / `function toggleTheme()` block, roughly lines 17–33 in each) and replace it with:

```blade
        <x-theme-script />
```

- [ ] **Step 6: Keep Tailwind's dark variant working during the transition**

Unconverted views still use `dark:` utilities, which key off the `.dark` class that no longer exists. Point Tailwind at the attribute instead.

In `tailwind.config.js`, change:

```js
    darkMode: 'class',
```

to:

```js
    darkMode: ['selector', '[data-theme="dark"]'],
```

This line disappears with the rest of the file at the end of Phase 5.

- [ ] **Step 7: Verify**

Run: `npm run build` — expected: succeeds.
Run: `php artisan test` — expected: all green.

Run `php artisan serve`, then in the browser:
- Load any page. Toggle the theme. Expected: `<html>` gains `data-theme="dark"` / `"light"`, and existing Tailwind `dark:` styling still flips.
- Reload. Expected: the choice persists with no flash of the wrong theme.
- Clear the stored value (`localStorage.removeItem('theme')`), reload with the OS in dark mode. Expected: dark, with no `data-theme` attribute present.

- [ ] **Step 8: Checkpoint — hand off for commit**

Stage: `resources/css/1-base/_tokens.css resources/js/ resources/views/components/theme-script.blade.php resources/views/layouts/ tailwind.config.js`
Suggested message: `feat: add design tokens and move theming to data-theme`

---

## Task 3: Typography

**Files:**
- Create: `public/fonts/*.woff2`, `resources/css/1-base/_typography.css`
- Modify: `resources/css/app.css`, both layout `<head>` blocks

**Interfaces:**
- Consumes: `--font-*` and `--step-*` tokens from Task 2
- Produces: `.u-display`, `.u-mono`, `.u-eyebrow`, `.u-muted` helpers used by every later component

- [ ] **Step 1: Fetch the fonts**

**Corrected before dispatch — "Archivo Expanded" is not a real font family.** Requesting it
returns `400: Font family not found`. Archivo is a *variable* font with a width (`wdth`) axis
spanning 62%-125%, and "Expanded" is the top of that axis, not a separate family. So the
display face is Archivo at `font-stretch: 125%` — the same typeface the design calls for,
delivered from **one** file instead of two.

Three files, latin subset, woff2:

```bash
mkdir -p public/fonts
# Archivo variable (wght 100-900, wdth 62%-125%) -> public/fonts/archivo.woff2
# IBM Plex Mono 400                              -> public/fonts/plex-mono-400.woff2
# IBM Plex Mono 600                              -> public/fonts/plex-mono-600.woff2
```

Fetch them by requesting the Google Fonts CSS **with a modern browser User-Agent** — with an
old or absent UA the API serves `.ttf` instead of `woff2`:

```bash
UA="Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
curl -s -A "$UA" "https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&display=swap"
curl -s -A "$UA" "https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&display=swap"
```

Each response contains several `@font-face` blocks, one per unicode subset. **Take the `latin`
subset only** — the one whose `unicode-range` starts `U+0000-00FF`. Download that block's
`src: url(...)` file. Both families are OFL licensed and may be self-hosted.

Verify after downloading: `file public/fonts/*.woff2` should report `Web Open Font Format
(Version 2)` for all three, and each should be non-empty.

**Also update the display token.** Task 2 wrote `--font-display: "Archivo Expanded", "Archivo", system-ui, sans-serif;` into `resources/css/1-base/_tokens.css:66`. Since no such family exists, change it to:

```css
    --font-display: "Archivo", system-ui, sans-serif;
```

The expanded appearance now comes from `font-stretch` on `.u-display`, not from a family name.

**If the network is unavailable:** keep the existing `fonts.bunny.net` `<link>` in the layouts, request `archivo:400,500,600` and `ibm-plex-mono:400,600` instead of `figtree`, skip the `@font-face` block, and record the deviation in your report. `.u-display`'s `font-stretch: 125%` still applies if the served Archivo is variable; if it is not, the display face falls back to normal width and the design gate at Task 10 should note it.

- [ ] **Step 2: Write the typography layer**

Replace `resources/css/1-base/_typography.css`:

```css
/* Self-hosted, OFL. font-display: swap keeps text visible during load. */

/* One variable file carries both roles: body text at the default width, and the
   display face at the top of the width axis. font-stretch here declares the
   range the file supports; .u-display below selects 125% from it. */
@font-face {
    font-family: "Archivo";
    src: url("/fonts/archivo.woff2") format("woff2-variations");
    font-weight: 100 900;
    font-stretch: 62% 125%;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: "IBM Plex Mono";
    src: url("/fonts/plex-mono-400.woff2") format("woff2");
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: "IBM Plex Mono";
    src: url("/fonts/plex-mono-600.woff2") format("woff2");
    font-weight: 600;
    font-style: normal;
    font-display: swap;
}

body {
    background-color: var(--c-bg);
    color: var(--c-text);
    font-family: var(--font-body);
    font-size: var(--step-0);
    line-height: var(--leading-normal);
    -webkit-font-smoothing: antialiased;
}

/* Display voice: broadcast lower-third. Wide, uppercase, tightly tracked at
   large sizes and loosely tracked at small ones. */
.u-display {
    font-family: var(--font-display);
    /* The expanded look. Archivo's width axis tops out at 125%; this is what
       makes the display voice read as broadcast lower-third rather than as
       ordinary body type set large. */
    font-stretch: 125%;
    font-weight: 700;
    line-height: var(--leading-tight);
    text-transform: uppercase;
    letter-spacing: -0.01em;
}

/* Small capitalised labels need the opposite tracking to stay readable. */
.u-eyebrow {
    font-family: var(--font-display);
    font-size: var(--step--2);
    font-weight: 600;
    letter-spacing: 0.18em;
    line-height: 1;
    text-transform: uppercase;
    color: var(--c-text-muted);
}

/* Every number in the app. Tabular figures keep columns aligned. */
.u-mono {
    font-family: var(--font-mono);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.01em;
}

.u-muted {
    color: var(--c-text-muted);
}

.u-visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}
```

Running utility count: 5 of 15.

- [ ] **Step 3: Import it and drop the remote fonts**

`resources/css/app.css` already imports `_typography.css` from Task 1 — confirm it does.

In both `resources/views/layouts/app.blade.php` and `resources/views/layouts/public.blade.php`, delete:

```blade
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
```

Also check `resources/views/layouts/guest.blade.php` for the same two lines and remove them there too.

- [ ] **Step 4: Verify**

Run: `npm run build` — expected: succeeds.

Run `php artisan serve` and load any page with devtools open:
- Network tab: the four font files load from `/fonts/`, and there are no requests to `fonts.bunny.net`.
- Run in the console: `getComputedStyle(document.body).fontFamily` — expected: starts with `Archivo`.

Run: `grep -rn "bunny.net" resources/views/` — expected: no output.

- [ ] **Step 5: Checkpoint — hand off for commit**

Stage: `public/fonts/ resources/css/1-base/_typography.css resources/views/layouts/`
Suggested message: `feat: self-host Archivo, Archivo Expanded and IBM Plex Mono`

---

## Task 4: Layout primitives

Spacing lives here and nowhere else. This is the rule that keeps components composable.

**Files:**
- Create: `resources/css/2-layout/_primitives.css`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: `--space-*`, `--measure` (`--rail-width` is a token but is consumed by Task 8's app shell, not here)
- Produces: `.l-container`, `.l-stack`, `.l-cluster`, `.l-grid`, `.l-sidebar`, `.l-prose`

- [ ] **Step 1: Write the primitives**

Create `resources/css/2-layout/_primitives.css`:

```css
/* Layout primitives own every margin and gap in the system. Components set
   their own padding and never their own outer spacing, so any component can be
   dropped into any layout without dragging whitespace along with it. */

.l-container {
    width: 100%;
    max-width: 80rem;
    margin-inline: auto;
    padding-inline: var(--space-4);
}

.l-container--narrow {
    max-width: 48rem;
}

/* Vertical rhythm. The owl selector spaces siblings without a trailing gap. */
.l-stack > * + * {
    margin-block-start: var(--space-5);
}

.l-stack--tight > * + * {
    margin-block-start: var(--space-3);
}

.l-stack--loose > * + * {
    margin-block-start: var(--space-8);
}

/* Horizontal grouping that wraps rather than overflows. */
.l-cluster {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-3);
}

.l-cluster--between {
    justify-content: space-between;
}

.l-cluster--end {
    justify-content: flex-end;
}

/* Responsive without media queries: columns collapse when they cannot fit. */
.l-grid {
    display: grid;
    gap: var(--space-4);
    grid-template-columns: repeat(auto-fit, minmax(min(16rem, 100%), 1fr));
}

.l-grid--wide {
    grid-template-columns: repeat(auto-fit, minmax(min(22rem, 100%), 1fr));
}

.l-sidebar {
    display: grid;
    gap: var(--space-5);
    grid-template-columns: minmax(0, 2fr) minmax(min(18rem, 100%), 1fr);
}

@media (max-width: 60rem) {
    .l-sidebar {
        grid-template-columns: 1fr;
    }
}

.l-prose {
    max-width: var(--measure);
}
```

- [ ] **Step 2: Import**

In `resources/css/app.css`, below the `1-base` imports:

```css
@import "./2-layout/_primitives.css";
```

- [ ] **Step 3: Verify**

Run: `npm run build` — expected: succeeds.

Confirm the discipline holds:
```bash
grep -nE "^\s*(margin|margin-block|margin-inline)" resources/css/3-components/*.css 2>/dev/null
```
Expected: no output now, and after every later task. A component setting margin is a bug.

- [ ] **Step 4: Checkpoint — hand off for commit**

Stage: `resources/css/2-layout/_primitives.css resources/css/app.css`
Suggested message: `feat: add layout primitives`

---

## Task 5: Buttons, cards, forms, alerts, badges

**Files:**
- Create: `resources/css/3-components/_btn.css`, `_card.css`, `_form.css`, `_alert.css`, `_badge.css`
- Create: `resources/views/components/btn.blade.php`, `card.blade.php`, `field.blade.php`, `alert.blade.php`, `badge.blade.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: tokens, typography helpers
- Produces:
  - `<x-btn variant="primary|ghost|danger" size="sm|md" href="?" type="submit">` — renders `<a>` when `href` is set, otherwise `<button>`
  - `<x-card>` with optional `title` and `actions` slots
  - `<x-field name label type value required>` with an optional default slot replacing the control
  - `<x-alert variant="info|success|danger">`
  - `<x-badge variant="neutral|primary|accent">`

- [ ] **Step 1: Buttons**

Create `resources/css/3-components/_btn.css`:

```css
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-4);
    border: var(--border-width) solid transparent;
    border-radius: var(--radius);
    background-color: transparent;
    color: var(--c-text);
    font-family: var(--font-display);
    font-size: var(--step--1);
    font-weight: 600;
    line-height: 1;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    transition:
        background-color var(--transition-fast),
        border-color var(--transition-fast),
        color var(--transition-fast);
}

.btn:focus-visible {
    outline: 2px solid var(--c-primary);
    outline-offset: 2px;
}

.btn:disabled,
.btn[aria-disabled="true"] {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn--primary {
    background-color: var(--c-primary);
    color: var(--c-primary-ink);
}

.btn--primary:hover {
    background-color: var(--c-primary-hover);
}

.btn--ghost {
    border-color: var(--c-border);
    color: var(--c-text);
}

.btn--ghost:hover {
    background-color: var(--c-surface-raised);
}

/* --c-accent-strong, not --c-accent: a 13px uppercase label is normal-size
   text, and white on #EF4537 is only 3.8:1. */
.btn--danger {
    background-color: var(--c-accent-strong);
    color: var(--c-accent-ink);
}

.btn--danger:hover {
    background-color: var(--c-accent-hover);
}

.btn--sm {
    padding: var(--space-1) var(--space-3);
    font-size: var(--step--2);
}

.btn--block {
    width: 100%;
}
```

Create `resources/views/components/btn.blade.php`:

```blade
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
])

@php
    $classes = 'btn btn--'.$variant.($size === 'sm' ? ' btn--sm' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
```

- [ ] **Step 2: Cards**

Create `resources/css/3-components/_card.css`:

```css
/* Surfaces are separated by a 1px border, never a shadow. */
.card {
    background-color: var(--c-surface);
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius);
}

.card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    padding: var(--space-4) var(--space-5);
    border-block-end: var(--border-width) solid var(--c-border);
}

.card__title {
    font-family: var(--font-display);
    font-size: var(--step--1);
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.card__body {
    padding: var(--space-5);
}

/* For a body that is itself a full-bleed table or list. */
.card--flush > .card__body {
    padding: 0;
}
```

Create `resources/views/components/card.blade.php`:

```blade
@props(['title' => null, 'flush' => false])

<div {{ $attributes->merge(['class' => 'card'.($flush ? ' card--flush' : '')]) }}>
    @if ($title || isset($actions))
        <div class="card__header">
            <h2 class="card__title">{{ $title }}</h2>
            @isset($actions)
                <div class="l-cluster">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="card__body">{{ $slot }}</div>
</div>
```

- [ ] **Step 3: Forms**

Create `resources/css/3-components/_form.css`:

```css
.field__label {
    display: block;
    font-family: var(--font-display);
    font-size: var(--step--2);
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--c-text-muted);
}

.field__control {
    display: block;
    width: 100%;
    margin-block-start: var(--space-2);
    padding: var(--space-2) var(--space-3);
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius-sm);
    background-color: var(--c-bg);
    color: var(--c-text);
    font-family: var(--font-body);
    font-size: var(--step-0);
    transition: border-color var(--transition-fast);
}

.field__control:hover {
    border-color: var(--c-text-muted);
}

.field__control:focus-visible {
    outline: 2px solid var(--c-primary);
    outline-offset: 1px;
    border-color: var(--c-primary);
}

.field__control--mono {
    font-family: var(--font-mono);
    font-variant-numeric: tabular-nums;
}

.field__error {
    margin-block-start: var(--space-2);
    color: var(--c-accent-strong);
    font-size: var(--step--1);
    font-weight: 600;
}

.field__hint {
    margin-block-start: var(--space-2);
    color: var(--c-text-muted);
    font-size: var(--step--1);
}

.field__checkbox {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    cursor: pointer;
}
```

`.field__control` and `.field__error` set `margin-block-start`, which looks like it breaks the no-margin rule. It does not: these are *internal* relationships between a label and its own control, not outer spacing. The rule forbids a component pushing away its siblings.

Create `resources/views/components/field.blade.php`:

```blade
@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
])

<div>
    <label class="field__label" for="{{ $name }}">{{ $label }}</label>

    @isset($slot)
        @if (trim($slot) !== '')
            {{ $slot }}
        @endif
    @endisset

    @if (! isset($slot) || trim($slot) === '')
        <input
            class="field__control"
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            @required($required)
            {{ $attributes }}
        >
    @endif

    @if ($hint)
        <p class="field__hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="field__error">{{ $message }}</p>
    @enderror
</div>
```

- [ ] **Step 4: Alerts**

Flash-message markup is currently duplicated across every index view. This replaces it.

Create `resources/css/3-components/_alert.css`:

```css
.alert {
    display: flex;
    align-items: flex-start;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    border: var(--border-width) solid var(--c-border);
    border-inline-start-width: 3px;
    border-radius: var(--radius-sm);
    background-color: var(--c-surface);
    font-size: var(--step--1);
}

.alert--info {
    border-inline-start-color: var(--c-primary);
}

.alert--success {
    border-inline-start-color: var(--c-primary);
}

/* Coral as a border, not a text ground — no contrast problem here. */
.alert--danger {
    border-inline-start-color: var(--c-accent);
}
```

Create `resources/views/components/alert.blade.php`:

```blade
@props(['variant' => 'info'])

<div class="alert alert--{{ $variant }}" role="{{ $variant === 'danger' ? 'alert' : 'status' }}">
    {{ $slot }}
</div>
```

- [ ] **Step 5: Badges**

Create `resources/css/3-components/_badge.css`:

```css
.badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    padding: var(--space-1) var(--space-2);
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius-pill);
    font-family: var(--font-display);
    font-size: var(--step--2);
    font-weight: 600;
    letter-spacing: 0.12em;
    line-height: 1;
    text-transform: uppercase;
    color: var(--c-text-muted);
}

.badge--primary {
    border-color: var(--c-primary);
    color: var(--c-primary);
}

/* Coral as text on the page ground, not white on coral. #EF4537 on both
   #0B0F19 and #F4F7FC clears AA for normal text. */
.badge--accent {
    border-color: var(--c-accent);
    color: var(--c-accent-strong);
}
```

Create `resources/views/components/badge.blade.php`:

```blade
@props(['variant' => 'neutral'])

<span {{ $attributes->merge(['class' => 'badge badge--'.$variant]) }}>{{ $slot }}</span>
```

- [ ] **Step 6: Import them all**

In `resources/css/app.css`, below the layout import:

```css
@import "./3-components/_btn.css";
@import "./3-components/_card.css";
@import "./3-components/_form.css";
@import "./3-components/_alert.css";
@import "./3-components/_badge.css";
```

- [ ] **Step 7: Verify**

Run: `npm run build` — expected: succeeds.
Run: `grep -nE "^\s*margin(-block-start)?:" resources/css/3-components/_btn.css resources/css/_card.css 2>/dev/null` — expected: no output from `_btn.css` or `_card.css`.
Run: `php artisan test` — expected: all green.

- [ ] **Step 8: Checkpoint — hand off for commit**

Stage: `resources/css/3-components/ resources/views/components/ resources/css/app.css`
Suggested message: `feat: add button, card, form, alert and badge components`

---

## Task 6: Data components — table, stat, meter, rank, empty state, avatar, page header

The meter is the system's signature element (spec §6.6).

**Files:**
- Create: `resources/css/3-components/_table.css`, `_stat.css`, `_meter.css`, `_rank.css`, `_empty.css`, `_avatar.css`
- Create: `resources/views/components/table.blade.php`, `stat.blade.php`, `meter.blade.php`, `rank.blade.php`, `empty-state.blade.php`, `avatar.blade.php`, `page-header.blade.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: tokens, typography helpers, `.l-cluster`
- Produces:
  - `<x-meter :value="860" :max="1000" />` — sets `--meter-fill` as a percentage
  - `<x-stat label="Total points" value="860" />`
  - `<x-rank :place="1" />`
  - `<x-table :caption="'Season standings'">` with a `head` slot
  - `<x-page-header title subtitle>` with an `actions` slot

- [ ] **Step 1: The meter**

Create `resources/css/3-components/_meter.css`:

```css
/* The signature element. Points read as a run of stacked chips rather than a
   smooth bar: the fill is a repeating gradient of 6px segments separated by
   1px gaps, so a longer bar is literally more chips. */
.meter {
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

.meter__track {
    flex: 1 1 auto;
    height: 0.625rem;
    min-width: 3rem;
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius-sm);
    background-color: var(--c-surface-raised);
    overflow: hidden;
}

.meter__fill {
    width: var(--meter-fill, 0%);
    height: 100%;
    background-image: repeating-linear-gradient(
        to right,
        var(--c-primary) 0,
        var(--c-primary) 6px,
        transparent 6px,
        transparent 7px
    );
    transition: width var(--transition-reveal);
}

.meter__value {
    flex: 0 0 auto;
    min-width: 4ch;
    font-family: var(--font-mono);
    font-variant-numeric: tabular-nums;
    font-size: var(--step--1);
    font-weight: 600;
    text-align: right;
}
```

Create `resources/views/components/meter.blade.php`:

```blade
@props(['value' => 0, 'max' => 0, 'showValue' => true])

@php
    $percentage = $max > 0 ? min(100, round(($value / $max) * 100, 2)) : 0;
@endphp

<div
    {{ $attributes->merge(['class' => 'meter']) }}
    style="--meter-fill: {{ $percentage }}%"
    role="meter"
    aria-valuenow="{{ $value }}"
    aria-valuemin="0"
    aria-valuemax="{{ $max }}"
>
    <div class="meter__track"><div class="meter__fill"></div></div>

    @if ($showValue)
        <span class="meter__value">{{ number_format($value) }}</span>
    @endif
</div>
```

This `style` attribute is the one approved exception in the whole system: it sets a custom property and nothing else. Every actual declaration lives in `_meter.css`.

- [ ] **Step 2: Tables**

Create `resources/css/3-components/_table.css`:

```css
.table-scroll {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--step--1);
}

.table__caption {
    padding: var(--space-3) var(--space-5);
    font-family: var(--font-display);
    font-size: var(--step--2);
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    text-align: start;
    color: var(--c-text-muted);
}

.table th {
    padding: var(--space-3) var(--space-4);
    border-block-end: var(--border-width) solid var(--c-border);
    font-family: var(--font-display);
    font-size: var(--step--2);
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    text-align: start;
    color: var(--c-text-muted);
    white-space: nowrap;
}

.table td {
    padding: var(--space-3) var(--space-4);
    border-block-end: var(--border-width) solid var(--c-border);
    vertical-align: middle;
}

.table tbody tr:last-child td {
    border-block-end: 0;
}

.table tbody tr:hover {
    background-color: var(--c-surface-raised);
}

/* Any cell holding a number. Right-aligned, tabular, so columns line up. */
.table__num {
    font-family: var(--font-mono);
    font-variant-numeric: tabular-nums;
    text-align: end;
}
```

`.table th` and `.table td` are element selectors, which the single-class rule forbids for components. Tables are the deliberate exception: the alternative is a class on every one of several hundred cells across the admin views. The selectors stay scoped under `.table` so they cannot leak.

Create `resources/views/components/table.blade.php`:

```blade
@props(['caption' => null])

<div class="table-scroll">
    <table class="table">
        @if ($caption)
            <caption class="table__caption">{{ $caption }}</caption>
        @endif

        @isset($head)
            <thead><tr>{{ $head }}</tr></thead>
        @endisset

        <tbody>{{ $slot }}</tbody>
    </table>
</div>
```

- [ ] **Step 3: Stats, rank, empty state, avatar**

Create `resources/css/3-components/_stat.css`:

```css
.stat {
    padding: var(--space-4) var(--space-5);
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius);
    background-color: var(--c-surface);
}

.stat__label {
    font-family: var(--font-display);
    font-size: var(--step--2);
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--c-text-muted);
}

.stat__value {
    display: block;
    margin-block-start: var(--space-2);
    font-family: var(--font-mono);
    font-variant-numeric: tabular-nums;
    font-size: var(--step-3);
    font-weight: 600;
    line-height: 1;
}
```

Create `resources/css/3-components/_rank.css`:

```css
/* Placement ordinal. Deliberately quiet — the meter is the loud element, and
   spending boldness twice would flatten both. */
.rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.75rem;
    height: 1.75rem;
    padding-inline: var(--space-1);
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius-pill);
    font-family: var(--font-mono);
    font-variant-numeric: tabular-nums;
    font-size: var(--step--1);
    font-weight: 600;
    color: var(--c-text-muted);
}

.rank--podium {
    border-color: var(--c-primary);
    color: var(--c-primary);
}
```

Create `resources/css/3-components/_empty.css`:

```css
.empty {
    padding: var(--space-8) var(--space-5);
    text-align: center;
    color: var(--c-text-muted);
}

.empty__title {
    font-family: var(--font-display);
    font-size: var(--step-1);
    font-weight: 700;
    text-transform: uppercase;
    color: var(--c-text);
}

.empty__body {
    margin-block-start: var(--space-2);
    font-size: var(--step--1);
}

.empty__action {
    margin-block-start: var(--space-4);
}
```

Create `resources/css/3-components/_avatar.css`:

```css
.avatar {
    display: block;
    width: 2.5rem;
    height: 2.5rem;
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius-pill);
    object-fit: cover;
}

.avatar--lg {
    width: 4rem;
    height: 4rem;
}
```

- [ ] **Step 4: The Blade components**

`resources/views/components/stat.blade.php`:

```blade
@props(['label', 'value'])

<div {{ $attributes->merge(['class' => 'stat']) }}>
    <span class="stat__label">{{ $label }}</span>
    <span class="stat__value">{{ $value }}</span>
</div>
```

`resources/views/components/rank.blade.php`:

```blade
@props(['place'])

<span class="rank{{ $place <= 3 ? ' rank--podium' : '' }}">{{ $place }}</span>
```

`resources/views/components/empty-state.blade.php`:

```blade
@props(['title'])

<div class="empty">
    <p class="empty__title">{{ $title }}</p>

    @if (trim($slot) !== '')
        <p class="empty__body">{{ $slot }}</p>
    @endif

    @isset($action)
        <div class="empty__action">{{ $action }}</div>
    @endisset
</div>
```

`resources/views/components/avatar.blade.php`:

```blade
@props(['user', 'size' => 'md'])

<img
    class="avatar{{ $size === 'lg' ? ' avatar--lg' : '' }}"
    src="{{ $user->profile_image_url }}"
    alt="{{ $user->display_name }}"
>
```

`resources/views/components/page-header.blade.php`:

```blade
@props(['title', 'eyebrow' => null])

<div class="l-cluster l-cluster--between">
    <div>
        @if ($eyebrow)
            <p class="u-eyebrow">{{ $eyebrow }}</p>
        @endif

        <h1 class="page-header__title">{{ $title }}</h1>
    </div>

    @isset($actions)
        <div class="l-cluster">{{ $actions }}</div>
    @endisset
</div>
```

The title carries its own class rather than `.u-display` plus a size override — an inline `style="font-size: ..."` would violate the no-inline-CSS rule, and a second utility for one size would push the utility budget for no gain.

Create `resources/css/3-components/_page-header.css`:

```css
.page-header__title {
    font-family: var(--font-display);
    font-size: var(--step-3);
    font-weight: 700;
    line-height: var(--leading-tight);
    letter-spacing: -0.01em;
    text-transform: uppercase;
}
```

- [ ] **Step 5: Import them**

Add to `resources/css/app.css`:

```css
@import "./3-components/_table.css";
@import "./3-components/_stat.css";
@import "./3-components/_meter.css";
@import "./3-components/_rank.css";
@import "./3-components/_empty.css";
@import "./3-components/_avatar.css";
@import "./3-components/_page-header.css";
```

- [ ] **Step 6: Verify**

Run: `npm run build` — expected: succeeds.

Run: `grep -rn 'style="' resources/views/components/`
Expected: exactly one match — `meter.blade.php`, setting `--meter-fill`. If `page-header.blade.php` still appears, Step 4 was not completed.

- [ ] **Step 7: Checkpoint — hand off for commit**

Stage: `resources/css/3-components/ resources/views/components/ resources/css/app.css`
Suggested message: `feat: add table, stat, meter, rank, empty-state and avatar components`

---

## Task 7: Overlays — dropdown and modal

The only components allowed a shadow.

**Files:**
- Create: `resources/css/3-components/_dropdown.css`, `_modal.css`
- Modify: `resources/views/components/dropdown.blade.php:29`, `resources/views/components/modal.blade.php:50`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: `--shadow-overlay`, `[x-cloak]` from Task 2
- Produces: `.dropdown`, `.dropdown__menu`, `.modal`, `.modal__panel`

- [ ] **Step 1: Write the CSS**

Create `resources/css/3-components/_dropdown.css`:

```css
.dropdown {
    position: relative;
    display: inline-block;
}

/* Dropdowns and modals are the system's only exception to borders-not-shadows.
   A floating layer separated by a 1px hairline alone reads as broken. */
.dropdown__menu {
    position: absolute;
    inset-inline-end: 0;
    z-index: 50;
    min-width: 12rem;
    margin-block-start: var(--space-2);
    padding-block: var(--space-1);
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius);
    background-color: var(--c-surface);
    box-shadow: var(--shadow-overlay);
}

.dropdown__item {
    display: block;
    width: 100%;
    padding: var(--space-2) var(--space-4);
    border: 0;
    background-color: transparent;
    color: var(--c-text);
    font-size: var(--step--1);
    text-align: start;
    text-decoration: none;
    cursor: pointer;
    transition: background-color var(--transition-fast);
}

.dropdown__item:hover {
    background-color: var(--c-surface-raised);
}

.dropdown__item:focus-visible {
    outline: 2px solid var(--c-primary);
    outline-offset: -2px;
}
```

Create `resources/css/3-components/_modal.css`:

```css
.modal {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-4);
    overflow-y: auto;
}

.modal__backdrop {
    position: fixed;
    inset: 0;
    background-color: rgb(11 15 25 / 0.7);
}

.modal__panel {
    position: relative;
    width: 100%;
    max-width: 32rem;
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius);
    background-color: var(--c-surface);
    box-shadow: var(--shadow-overlay);
}

.modal__body {
    padding: var(--space-5);
}

.modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--space-3);
    padding: var(--space-4) var(--space-5);
    border-block-start: var(--border-width) solid var(--c-border);
}
```

- [ ] **Step 2: Remove the two inline styles**

In `resources/views/components/dropdown.blade.php`, find line 29:

```blade
            style="display: none;"
```

Delete it and add `x-cloak` to the same element's attributes. The `[x-cloak]` rule from Task 2 does the hiding.

In `resources/views/components/modal.blade.php`, find line 50:

```blade
    style="display: {{ $show ? 'block' : 'none' }};"
```

Delete it. Alpine's `x-show` already controls visibility; add `x-cloak` to the same element so it stays hidden until Alpine initialises.

- [ ] **Step 3: Import**

```css
@import "./3-components/_dropdown.css";
@import "./3-components/_modal.css";
```

- [ ] **Step 4: Verify**

Run: `npm run build` — expected: succeeds.

Run `php artisan serve` and check in the browser:
- The user dropdown in the top bar opens and closes, with no flash of the open menu on page load.
- The delete-account modal on `/profile` opens, closes on Escape, and returns focus to the trigger.
- Tab through the open dropdown — every item shows a visible focus ring.

Run: `grep -rn 'style="' resources/views/components/`
Expected: one match only, `meter.blade.php`.

- [ ] **Step 5: Checkpoint — hand off for commit**

Stage: `resources/css/3-components/_dropdown.css resources/css/3-components/_modal.css resources/views/components/ resources/css/app.css`
Suggested message: `feat: restyle dropdown and modal overlays and drop their inline styles`

---

## Task 8: The app shell

Replaces the top-bar dropdown holding eight admin links with a left rail.

**Files:**
- Create: `resources/css/2-layout/_shell-app.css`, `resources/css/3-components/_nav.css`
- Rewrite: `resources/views/layouts/navigation.blade.php`, `resources/views/layouts/app.blade.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: every component from Tasks 5–7, `--rail-width`
- Produces: the `$header` and `$slot` contract that every authenticated view already uses — **do not change it**, or all 30+ authenticated views break at once.

- [ ] **Step 1: Write the shell CSS**

Create `resources/css/2-layout/_shell-app.css`:

```css
.shell {
    display: grid;
    min-height: 100vh;
    grid-template-columns: var(--rail-width) minmax(0, 1fr);
    background-color: var(--c-bg);
}

.shell__rail {
    display: flex;
    flex-direction: column;
    gap: var(--space-6);
    padding: var(--space-5) var(--space-4);
    border-inline-end: var(--border-width) solid var(--c-border);
    background-color: var(--c-surface);
}

.shell__brand {
    font-family: var(--font-display);
    font-size: var(--step-1);
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--c-text);
    text-decoration: none;
}

.shell__rail-footer {
    margin-block-start: auto;
    padding-block-start: var(--space-4);
    border-block-start: var(--border-width) solid var(--c-border);
}

.shell__main {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.shell__header {
    padding: var(--space-5) var(--space-6);
    border-block-end: var(--border-width) solid var(--c-border);
}

.shell__content {
    flex: 1 1 auto;
    padding: var(--space-6);
}

.shell__drawer-toggle {
    display: none;
}

@media (max-width: 56.25rem) {
    .shell {
        grid-template-columns: minmax(0, 1fr);
    }

    /* Below the breakpoint the rail slides off-canvas and Alpine toggles
       .shell__rail--open. Visibility is CSS's job at every width, so there is
       no window.innerWidth check in the markup to go stale on resize. */
    .shell__rail {
        position: fixed;
        inset-block: 0;
        inset-inline-start: 0;
        z-index: 90;
        width: var(--rail-width);
        box-shadow: var(--shadow-overlay);
        transform: translateX(-100%);
        transition: transform var(--transition-fast);
    }

    .shell__rail--open {
        transform: translateX(0);
    }

    .shell__drawer-toggle {
        display: inline-flex;
    }

    .shell__content {
        padding: var(--space-4);
    }
}
```

Create `resources/css/3-components/_nav.css`:

```css
.nav-group + .nav-group {
    margin-block-start: var(--space-5);
}

.nav-group__label {
    display: block;
    padding-inline: var(--space-3);
    margin-block-end: var(--space-2);
    font-family: var(--font-display);
    font-size: var(--step--2);
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--c-text-muted);
}

.nav-link {
    display: block;
    padding: var(--space-2) var(--space-3);
    border-radius: var(--radius-sm);
    color: var(--c-text);
    font-size: var(--step--1);
    text-decoration: none;
    transition:
        background-color var(--transition-fast),
        color var(--transition-fast);
}

.nav-link:hover {
    background-color: var(--c-surface-raised);
}

.nav-link:focus-visible {
    outline: 2px solid var(--c-primary);
    outline-offset: 2px;
}

/* The only place a filled primary appears outside a button. It marks where you
   are, which is worth the ink. */
.nav-link--current {
    background-color: var(--c-primary);
    color: var(--c-primary-ink);
    font-weight: 600;
}
```

`.nav-group + .nav-group` sets margin, which is an outer-spacing rule inside a component file. It is allowed here because the rail's groups have no layout primitive between them; note it in the file so the next reader knows it was considered.

- [ ] **Step 2: Rewrite the navigation**

Replace `resources/views/layouts/navigation.blade.php` entirely:

```blade
<nav class="shell__rail" x-bind:class="{ 'shell__rail--open': railOpen }" aria-label="{{ __('Main') }}">
    <a class="shell__brand" href="{{ route('dashboard') }}">{{ __('FTAP') }}</a>

    <div>
        <div class="nav-group">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link--current' : '' }}"
               href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
        </div>

        @if (Auth::user()->is_admin)
            <div class="nav-group">
                <span class="nav-group__label">{{ __('League') }}</span>
                <a class="nav-link {{ request()->routeIs('poker.seasons.*') ? 'nav-link--current' : '' }}"
                   href="{{ route('poker.seasons.index') }}">{{ __('Seasons') }}</a>
                <a class="nav-link {{ request()->routeIs('poker.venues.*') ? 'nav-link--current' : '' }}"
                   href="{{ route('poker.venues.index') }}">{{ __('Venues') }}</a>
                <a class="nav-link {{ request()->routeIs('poker.tournaments.*') ? 'nav-link--current' : '' }}"
                   href="{{ route('poker.tournaments.index') }}">{{ __('Tournaments') }}</a>
            </div>

            <div class="nav-group">
                <span class="nav-group__label">{{ __('Play') }}</span>
                <a class="nav-link {{ request()->routeIs('poker.results.*') ? 'nav-link--current' : '' }}"
                   href="{{ route('poker.results.index') }}">{{ __('Results') }}</a>
                <a class="nav-link {{ request()->routeIs('poker.registrants.*') ? 'nav-link--current' : '' }}"
                   href="{{ route('poker.registrants.index') }}">{{ __('Registrants') }}</a>
                <a class="nav-link {{ request()->routeIs('poker.venue-points.*') ? 'nav-link--current' : '' }}"
                   href="{{ route('poker.venue-points.index') }}">{{ __('Venue points') }}</a>
            </div>

            <div class="nav-group">
                <span class="nav-group__label">{{ __('Setup') }}</span>
                <a class="nav-link {{ request()->routeIs('poker.points-structure.*') ? 'nav-link--current' : '' }}"
                   href="{{ route('poker.points-structure.index') }}">{{ __('Points structure') }}</a>
                <a class="nav-link {{ request()->routeIs('users.*') ? 'nav-link--current' : '' }}"
                   href="{{ route('users.index') }}">{{ __('Players') }}</a>
            </div>
        @endif
    </div>

    <div class="shell__rail-footer l-stack l-stack--tight">
        <button type="button" class="nav-link" data-theme-toggle aria-pressed="false">
            {{ __('Switch theme') }}
        </button>

        <a class="nav-link" href="{{ route('profile.edit') }}">{{ __('Your profile') }}</a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link">{{ __('Log out') }}</button>
        </form>
    </div>
</nav>
```

- [ ] **Step 3: Rewrite the app layout**

Replace `resources/views/layouts/app.blade.php` entirely:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'First To Act Poker') }}</title>

        <x-theme-script />

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png"/>
    </head>
    <body>
        <div class="shell" x-data="{ railOpen: false }">
            @include('layouts.navigation')

            <div class="shell__main">
                <button type="button" class="btn btn--ghost btn--sm shell__drawer-toggle"
                        x-on:click="railOpen = ! railOpen"
                        x-bind:aria-expanded="railOpen.toString()">
                    {{ __('Menu') }}
                </button>

                @isset($header)
                    <header class="shell__header">{{ $header }}</header>
                @endisset

                <main class="shell__content">
                    @if (session('status'))
                        <x-alert variant="success">{{ session('status') }}</x-alert>
                    @endif

                    @if (session('error'))
                        <x-alert variant="danger">{{ session('error') }}</x-alert>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
```

The `$header` / `$slot` contract is unchanged, so unconverted views keep rendering. The flash-message blocks are new — as Phases 2–5 convert views, delete each view's own duplicated flash markup.

- [ ] **Step 4: Import**

```css
@import "./2-layout/_shell-app.css";
@import "./3-components/_nav.css";
```

- [ ] **Step 5: Verify**

Run: `npm run build` — expected: succeeds.
Run: `php artisan test` — expected: all green, including `RouteSmokeTest`.

In the browser, signed in as an admin:
- The rail shows Dashboard, League, Play, Setup. The current section is highlighted.
- Signed in as a non-admin: only Dashboard and the footer links appear.
- Below 900px the rail collapses and the Menu button toggles it.
- Tab through the rail — every link and button shows a focus ring.
- The theme toggle flips the theme and its `aria-pressed` value.

- [ ] **Step 6: Checkpoint — hand off for commit**

Stage: `resources/css/ resources/views/layouts/`
Suggested message: `feat: replace the admin top-bar dropdown with a left rail app shell`

---

## Task 9: Public and guest shells

**Files:**
- Create: `resources/css/2-layout/_shell-public.css`
- Rewrite: `resources/views/layouts/public.blade.php`, `resources/views/layouts/guest.blade.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: components from Tasks 5–7
- Produces: the public `$slot` contract, unchanged

- [ ] **Step 1: Write the CSS**

Create `resources/css/2-layout/_shell-public.css`:

```css
.public {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background-color: var(--c-bg);
}

.public__bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-5);
    padding: var(--space-4) var(--space-6);
    border-block-end: var(--border-width) solid var(--c-border);
    background-color: var(--c-surface);
}

.public__brand {
    font-family: var(--font-display);
    font-size: var(--step-1);
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--c-text);
    text-decoration: none;
    white-space: nowrap;
}

.public__links {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-4);
}

.public__main {
    flex: 1 1 auto;
    padding-block: var(--space-8);
}

.public__footer {
    padding: var(--space-6);
    border-block-start: var(--border-width) solid var(--c-border);
    color: var(--c-text-muted);
    font-size: var(--step--1);
    text-align: center;
}

.guest {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: var(--space-6) var(--space-4);
    background-color: var(--c-bg);
}

.guest__panel {
    width: 100%;
    max-width: 26rem;
    padding: var(--space-6);
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius);
    background-color: var(--c-surface);
}

.guest__brand {
    display: block;
    margin-block-end: var(--space-6);
    font-family: var(--font-display);
    font-size: var(--step-1);
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    text-align: center;
    color: var(--c-text);
    text-decoration: none;
}

@media (max-width: 48rem) {
    .public__bar {
        flex-direction: column;
        align-items: flex-start;
        padding: var(--space-4);
    }
}
```

- [ ] **Step 2: Rewrite `public.blade.php`**

Keep the existing nav links — Home, Events, Rules (Regulations, Conduct, Texas Hold'em, Points structure), About, Contact — plus Log in and Join. Structure:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'First To Act Poker') }}</title>
        <x-theme-script />
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png"/>
    </head>
    <body>
        <div class="public">
            <header class="public__bar">
                <a class="public__brand" href="{{ route('home') }}">{{ __('First To Act') }}</a>

                <nav class="public__links" aria-label="{{ __('Main') }}">
                    <a class="nav-link {{ request()->routeIs('events') ? 'nav-link--current' : '' }}" href="{{ route('events') }}">{{ __('Events') }}</a>
                    <a class="nav-link {{ request()->routeIs('rules.tournament') ? 'nav-link--current' : '' }}" href="{{ route('rules.tournament') }}">{{ __('Regulations') }}</a>
                    <a class="nav-link {{ request()->routeIs('rules.betting') ? 'nav-link--current' : '' }}" href="{{ route('rules.betting') }}">{{ __('Conduct') }}</a>
                    <a class="nav-link {{ request()->routeIs('rules.texas-holdem') ? 'nav-link--current' : '' }}" href="{{ route('rules.texas-holdem') }}">{{ __('How to play') }}</a>
                    <a class="nav-link {{ request()->routeIs('rules.points-structure') ? 'nav-link--current' : '' }}" href="{{ route('rules.points-structure') }}">{{ __('Points') }}</a>
                    <a class="nav-link {{ request()->routeIs('about.index') ? 'nav-link--current' : '' }}" href="{{ route('about.index') }}">{{ __('About') }}</a>
                    <a class="nav-link {{ request()->routeIs('contact') ? 'nav-link--current' : '' }}" href="{{ route('contact') }}">{{ __('Contact') }}</a>
                </nav>

                <div class="l-cluster">
                    <button type="button" class="nav-link" data-theme-toggle aria-pressed="false">{{ __('Switch theme') }}</button>

                    @auth
                        <x-btn variant="ghost" size="sm" :href="route('dashboard')">{{ __('Dashboard') }}</x-btn>
                    @else
                        <a class="nav-link" href="{{ route('login') }}">{{ __('Log in') }}</a>
                        <x-btn variant="primary" size="sm" :href="route('register')">{{ __('Join') }}</x-btn>
                    @endauth
                </div>
            </header>

            <main class="public__main">
                <div class="l-container l-stack">{{ $slot }}</div>
            </main>

            <footer class="public__footer">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </footer>
        </div>
    </body>
</html>
```

This removes the `x-data="{ mobileMenuOpen: false }"` block and the `style="display: none;"` at line 77 — the link row now wraps instead of collapsing into a toggle.

- [ ] **Step 3: Rewrite `guest.blade.php`**

Same `<head>`. Body:

```blade
    <body>
        <div class="guest">
            <div class="guest__panel l-stack">
                <a class="guest__brand" href="{{ route('home') }}">{{ __('First To Act') }}</a>
                {{ $slot }}
            </div>
        </div>
    </body>
```

- [ ] **Step 4: Import and verify**

```css
@import "./2-layout/_shell-public.css";
```

Run: `npm run build` — expected: succeeds.
Run: `php artisan test` — expected: all green.
Run: `grep -rn 'style="' resources/views/layouts/` — expected: no output.

In the browser: load `/`, `/events`, `/about`, `/contact`, `/login`. Check both themes and a 375px-wide viewport. Confirm the page never scrolls horizontally.

- [ ] **Step 5: Checkpoint — hand off for commit**

Stage: `resources/css/2-layout/_shell-public.css resources/views/layouts/ resources/css/app.css`
Suggested message: `feat: rebuild the public and guest shells on the design system`

---

## Task 10: Convert the season page — the direction validation

Spec §9.1. `poker/seasons/show.blade.php` is the most demanding page in the app: app shell, stat tiles, a leaderboard table, venue distribution meters and a tournament list. If the design works here it works everywhere.

Its controller (`PokerSeasonController::show`) provides `$season`, `$totalTournaments`, `$totalPoints`, `$uniquePlayersCount`, `$leaderboard` (each row: `user`, `player_name`, `points`, `wins`, `top3`, `played`) and `$venueStats` (each row: `name`, `count`). **Do not change the controller.**

**Files:**
- Rewrite: `resources/views/poker/seasons/show.blade.php`
- Create: `resources/css/4-pages/_season-show.css`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: every component built in Tasks 5–8
- Produces: the reference pattern all later page conversions copy

- [ ] **Step 1: Rewrite the view**

Replace `resources/views/poker/seasons/show.blade.php`. Structure:

```blade
<x-app-layout>
    <x-slot name="header">
        <x-page-header :eyebrow="__('Season')" :title="$season->name">
            <x-slot name="actions">
                <x-btn variant="ghost" size="sm" :href="route('poker.seasons.edit', $season)">{{ __('Edit season') }}</x-btn>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="l-stack">
        <div class="l-grid">
            <x-stat :label="__('Tournaments')" :value="number_format($totalTournaments)" />
            <x-stat :label="__('Points awarded')" :value="number_format($totalPoints)" />
            <x-stat :label="__('Players')" :value="number_format($uniquePlayersCount)" />
        </div>

        <div class="l-sidebar">
            <x-card :title="__('Standings')" :flush="true">
                @if ($leaderboard->isEmpty())
                    <x-empty-state :title="__('No results yet')">
                        {{ __('Standings appear once the first tournament result is recorded.') }}
                        <x-slot name="action">
                            <x-btn variant="primary" size="sm" :href="route('poker.results.create')">{{ __('Record a result') }}</x-btn>
                        </x-slot>
                    </x-empty-state>
                @else
                    @php $leaderPoints = $leaderboard->first()['points']; @endphp

                    <x-table :caption="__('Season standings')">
                        <x-slot name="head">
                            <th scope="col">{{ __('Rank') }}</th>
                            <th scope="col">{{ __('Player') }}</th>
                            <th scope="col">{{ __('Points') }}</th>
                            <th scope="col" class="table__num">{{ __('Played') }}</th>
                            <th scope="col" class="table__num">{{ __('Won') }}</th>
                        </x-slot>

                        @foreach ($leaderboard as $index => $row)
                            <tr>
                                <td><x-rank :place="$index + 1" /></td>
                                <td>{{ $row['user']?->display_name ?? $row['player_name'] }}</td>
                                <td class="season-show__meter-cell">
                                    <x-meter :value="$row['points']" :max="$leaderPoints" />
                                </td>
                                <td class="table__num">{{ $row['played'] }}</td>
                                <td class="table__num">{{ $row['wins'] }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>

            <div class="l-stack">
                <x-card :title="__('Venues')">
                    @forelse ($venueStats as $venue)
                        <div class="l-stack l-stack--tight">
                            <div class="l-cluster l-cluster--between">
                                <span>{{ $venue['name'] }}</span>
                                <span class="u-mono u-muted">{{ $venue['count'] }}</span>
                            </div>
                            <x-meter :value="$venue['count']" :max="$venueStats->max('count')" :show-value="false" />
                        </div>
                    @empty
                        <x-empty-state :title="__('No venues yet')">
                            {{ __('Venue usage appears once tournaments are scheduled.') }}
                        </x-empty-state>
                    @endforelse
                </x-card>

                <x-card :title="__('Tournaments')" :flush="true">
                    @forelse ($season->tournaments as $tournament)
                        <a class="season-show__tournament" href="{{ route('tournaments.show', $tournament) }}">
                            <span>{{ $tournament->name }}</span>
                            <span class="u-mono u-muted">{{ $tournament->start_time?->format('d M Y') }}</span>
                        </a>
                    @empty
                        <x-empty-state :title="__('Nothing scheduled')">
                            {{ __('Add a tournament to start this season.') }}
                        </x-empty-state>
                    @endforelse
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
```

Note the route name: `tournaments.show`, not `poker.tournaments.show` — Phase 0 moved it.

- [ ] **Step 2: Add the page CSS**

Create `resources/css/4-pages/_season-show.css`:

```css
/* The meter needs room to read as a chip run rather than a sliver. */
.season-show__meter-cell {
    min-width: 12rem;
}

.season-show__tournament {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    padding: var(--space-3) var(--space-5);
    border-block-end: var(--border-width) solid var(--c-border);
    color: var(--c-text);
    text-decoration: none;
    transition: background-color var(--transition-fast);
}

.season-show__tournament:last-child {
    border-block-end: 0;
}

.season-show__tournament:hover {
    background-color: var(--c-surface-raised);
}

.season-show__tournament:focus-visible {
    outline: 2px solid var(--c-primary);
    outline-offset: -2px;
}
```

Import it:

```css
@import "./4-pages/_season-show.css";
```

- [ ] **Step 3: Verify it renders**

Run: `php artisan migrate:fresh --seed` then `php artisan serve`.
Sign in as `admin@example.com` / `password`, open the current season from the rail.

Check:
- Standings show a chip-stack meter per player, longest for the leader.
- Numbers align in their columns (tabular figures).
- No horizontal page scroll at 375px; the table scrolls inside its own container.
- Both themes.
- Tab through the page — every link, button and row action shows a focus ring.
- With the OS set to reduce motion, the meter fill does not animate.

- [ ] **Step 4: Run the suite**

Run: `php artisan test`
Expected: all green, `RouteSmokeTest` included.

- [ ] **Step 5: Present for approval — the direction gate**

Capture the season page in both themes, desktop and mobile, and present them. **Stop here.** Phases 2–5 do not begin until the user approves Archivo Expanded and the overall direction. If they reject it, only this one page and the type tokens need revisiting, not 60 views.

- [ ] **Step 6: Checkpoint — hand off for commit**

Stage: `resources/views/poker/seasons/show.blade.php resources/css/4-pages/ resources/css/app.css`
Suggested message: `feat: convert the season page to the design system`

---

## Task 11: Lock the rules in with a test

A grep in a checklist gets skipped. A test does not.

**Files:**
- Create: `tests/Feature/InlineStyleGuardTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: CI enforcement of the no-inline-CSS rule for every later phase

- [ ] **Step 1: Write the test**

Create `tests/Feature/InlineStyleGuardTest.php`:

```php
<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The design system forbids inline CSS. The single exception is a style
 * attribute that sets only custom properties, which is how genuinely
 * data-driven values (a meter's fill percentage) reach the stylesheet.
 */
class InlineStyleGuardTest extends TestCase
{
    public function test_no_view_contains_inline_css()
    {
        $offenders = [];

        $finder = Finder::create()
            ->files()
            ->in(resource_path('views'))
            ->name('*.blade.php');

        foreach ($finder as $file) {
            $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES);

            foreach ($lines as $number => $line) {
                if (! preg_match('/style\s*=\s*"([^"]*)"/', $line, $matches)) {
                    continue;
                }

                // Permitted: every declaration in the attribute is a custom
                // property. "--meter-fill: 86%" passes; "width: 86%" does not.
                $declarations = array_filter(array_map('trim', explode(';', $matches[1])));

                $allCustom = $declarations !== [] && array_reduce(
                    $declarations,
                    fn (bool $carry, string $declaration) => $carry && str_starts_with($declaration, '--'),
                    true
                );

                if (! $allCustom) {
                    $offenders[] = sprintf(
                        '%s:%d — style="%s"',
                        str_replace(resource_path('views').'/', '', $file->getRealPath()),
                        $number + 1,
                        $matches[1]
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Inline CSS is not allowed. Move these into a stylesheet:\n  ".implode("\n  ", $offenders)
        );
    }
}
```

- [ ] **Step 2: Run it**

Run: `php artisan test --filter=InlineStyleGuardTest`

Expected at this point in Phase 1: **FAILS**, listing the remaining offenders — `components/tournament-badge.blade.php` (the 8 rotation angles) and `poker/seasons/show.blade.php` if Task 10 left anything behind.

- [ ] **Step 3: Fix `tournament-badge.blade.php`**

The 8 chip marks use `style="transform: rotate({{ $angle }}deg) translateY(-11px);"`. The angles are a fixed set, so they become 8 static classes.

Add to a new `resources/css/3-components/_chip-token.css`:

```css
.chip-mark {
    position: absolute;
    width: 2px;
    height: 8px;
    background-color: currentColor;
    opacity: 0.35;
}

.chip-mark--0   { transform: rotate(0deg)   translateY(-11px); }
.chip-mark--45  { transform: rotate(45deg)  translateY(-11px); }
.chip-mark--90  { transform: rotate(90deg)  translateY(-11px); }
.chip-mark--135 { transform: rotate(135deg) translateY(-11px); }
.chip-mark--180 { transform: rotate(180deg) translateY(-11px); }
.chip-mark--225 { transform: rotate(225deg) translateY(-11px); }
.chip-mark--270 { transform: rotate(270deg) translateY(-11px); }
.chip-mark--315 { transform: rotate(315deg) translateY(-11px); }
```

In the view, change the loop body to:

```blade
        @foreach ([0, 45, 90, 135, 180, 225, 270, 315] as $angle)
            <div class="chip-mark chip-mark--{{ $angle }}"></div>
        @endforeach
```

Import `_chip-token.css` in `app.css`.

- [ ] **Step 4: Run it again**

Run: `php artisan test --filter=InlineStyleGuardTest`
Expected: PASS.

Run: `php artisan test`
Expected: all green.

- [ ] **Step 5: Checkpoint — hand off for commit**

Stage: `tests/Feature/InlineStyleGuardTest.php resources/views/components/tournament-badge.blade.php resources/css/`
Suggested message: `test: forbid inline css outside custom properties`

---

---

## Task 12: Restyle the remaining legacy components

Spec §6.4 lists 15 existing components to rewrite. Tasks 5–7 and 11 covered the three buttons, `dropdown`, `modal` and `tournament-badge`. Nine remain, and Phases 2–5 cannot convert a view whose child components still carry Tailwind classes.

`<x-field>` supersedes `input-label` + `text-input` + `input-error`, but the originals must keep working until every form is converted, so they are restyled rather than deleted.

**Files:**
- Modify: `resources/views/components/application-logo.blade.php`, `auth-session-status.blade.php`, `input-error.blade.php`, `input-label.blade.php`, `text-input.blade.php`, `dropdown-link.blade.php`, `nav-link.blade.php`, `responsive-nav-link.blade.php`, `section-badge.blade.php`
- Modify: `resources/css/3-components/_form.css`, `_nav.css`

**Interfaces:**
- Consumes: tokens and component classes from Tasks 2–7
- Produces: the same component APIs as before — props and slots are unchanged, so no calling view needs editing

- [ ] **Step 1: Check what still uses them**

```bash
for c in application-logo auth-session-status input-error input-label text-input dropdown-link nav-link responsive-nav-link section-badge; do
  printf '%-24s %s\n' "$c" "$(grep -rl "x-$c" resources/views/ | tr '\n' ' ')"
done
```

Record the output. Any component with no callers after Task 8 replaced the navigation — likely `nav-link` and `responsive-nav-link` — can be deleted instead of restyled. Delete only if the list is empty.

- [ ] **Step 2: Map each to an existing class**

No new CSS is needed for most of these; they map onto classes that already exist.

`input-label.blade.php`:

```blade
@props(['value'])

<label {{ $attributes->merge(['class' => 'field__label']) }}>
    {{ $value ?? $slot }}
</label>
```

`text-input.blade.php`:

```blade
@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'field__control']) }}>
```

`input-error.blade.php`:

```blade
@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'field__error']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
```

Add to `resources/css/3-components/_form.css`:

```css
/* input-error renders a list; strip the marker without a second class. */
.field__error {
    list-style: none;
    padding-inline-start: 0;
}
```

`dropdown-link.blade.php`:

```blade
<a {{ $attributes->merge(['class' => 'dropdown__item']) }}>{{ $slot }}</a>
```

`auth-session-status.blade.php`:

```blade
@props(['status'])

@if ($status)
    <div {{ $attributes }}>
        <x-alert variant="success">{{ $status }}</x-alert>
    </div>
@endif
```

`section-badge.blade.php` — read the current file first to preserve its props, then render through `<x-badge>`:

```blade
@props(['label' => null])

<x-badge variant="primary">{{ $label ?? $slot }}</x-badge>
```

`application-logo.blade.php` — keep whatever SVG or `<img>` it currently contains; replace only its Tailwind sizing classes with a class, and add to `_nav.css`:

```css
.app-logo {
    display: block;
    height: 2rem;
    width: auto;
}
```

- [ ] **Step 3: Restyle or delete the nav-link pair**

If Step 1 showed callers, point them at the rail's classes:

`nav-link.blade.php`:

```blade
@props(['active' => false])

<a {{ $attributes->merge(['class' => 'nav-link'.($active ? ' nav-link--current' : '')]) }}>
    {{ $slot }}
</a>
```

`responsive-nav-link.blade.php` — identical body. The rail is responsive by default, so the two no longer differ; keep both names so callers do not break.

If Step 1 showed no callers for either, delete both files instead.

- [ ] **Step 4: Verify**

```bash
grep -rnE 'class="[^"]*(bg-|text-(xs|sm|lg|xl)|border-gray|rounded-(md|lg)|dark:|px-[0-9]|py-[0-9])' resources/views/components/
```
Expected: no output. Every component is now on the design system.

Run: `npm run build` — expected: succeeds.
Run: `php artisan test` — expected: all green, `InlineStyleGuardTest` included.

In the browser, sign in and open `/profile`: the three forms there exercise `input-label`, `text-input`, `input-error` and `modal` together. Confirm labels, controls, focus rings and validation errors all render on the new system in both themes.

- [ ] **Step 5: Checkpoint — hand off for commit**

Stage: `resources/views/components/ resources/css/3-components/`
Suggested message: `refactor: move the remaining legacy components onto the design system`

## Phase 1 exit criteria

- [ ] `php artisan test` — all green, including `RouteSmokeTest` and `InlineStyleGuardTest`.
- [ ] `npm run build` — succeeds.
- [ ] `grep -rn "bunny.net" resources/views/` — no output.
- [ ] No file in `resources/views/components/` contains a Tailwind utility class.
- [ ] `grep -rnE "^\s*margin" resources/css/3-components/*.css` — only `_nav.css`, with its documented reason.
- [ ] `grep -c "^\.u-" resources/css/1-base/_typography.css` — 5 or fewer; total `.u-` classes across the system ≤ 15.
- [ ] Every colour in `_tokens.css` has a bare `:root` definition.
- [ ] The theme toggle works in both directions, persists, and shows no flash on reload.
- [ ] Rail collapses below 900px; the drawer traps focus and Escape closes it.
- [ ] No page scrolls horizontally at 375px.
- [ ] With reduced motion enabled, nothing animates.
- [ ] **The user has approved the season page and the Archivo Expanded direction.** Phases 2–5 are blocked until then.
