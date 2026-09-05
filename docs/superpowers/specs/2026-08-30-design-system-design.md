# First to Act Poker — Design System & Redesign

**Date:** 2026-08-30
**Status:** Approved for planning
**Scope:** Replace Tailwind CSS with a hand-built design system; redesign all application pages; land six correctness/cleanup fixes first.

---

## 1. Goals

1. Remove the Tailwind CSS dependency entirely.
2. Replace it with a semantic, token-driven CSS system using the fixed brand palette.
3. Redesign every page — layout and structure, not only colour.
4. Use no inline CSS. The single exception is a `style` attribute that sets **only** a CSS custom property (e.g. `style="--meter-fill: 86%"`) for genuinely data-driven values.
5. Fix six existing correctness and cleanup issues before the redesign, so markup is converted once.

### Non-goals

- No changes to scoring, leaderboard or points logic.
- No new league features.
- No database restructuring beyond what the six fixes require.

---

## 2. Current state

- Laravel 12 / PHP 8.2, SQLite, Blade + Alpine.js, Vite.
- **68 Blade views, ~5,700 lines**, styled entirely with Tailwind utilities.
- Tailwind v3 (`tailwind.config.js`, `darkMode: 'class'`) plus a stray `@tailwindcss/vite` v4 dependency.
- Fonts: Figtree via `fonts.bunny.net`.
- Theme toggle: `localStorage.theme` + `.dark` class, duplicated verbatim in two layouts.
- 12 inline `style=""` occurrences; 7 are in a dead view.
- Three unrouted dead views: `welcome.blade.php`, `about/mission.blade.php`, `about/sponsors.blade.php`.

---

## 3. Design direction — "Under the Gun"

The league is named for a seat at the table: *first to act* is the player under the gun. The design treats the app as a **tournament director's readout** — a broadcast-grade display of who is playing, where they stand, and what happens next.

This drives three decisions:

- **Broadcast typography.** Expanded uppercase display type, the visual language of sports lower-thirds.
- **Data as the subject.** Tabular monospaced figures everywhere; standings and results are the design, not decoration on it.
- **Hairline panelling.** 1px borders rather than shadows (per brief) read as instrument-panel divisions, which the direction earns rather than merely complies with.

**The risk being taken:** Archivo Expanded as the display face. Expanded grotesques are uncommon on the web and make the product immediately recognisable. Validated on one real page at the end of Phase 1 before committing all 68 views.

---

## 4. Phase plan

Each phase ships independently and leaves the app working.

| Phase | Contents | Views |
|---|---|---|
| **0** | Correctness & cleanup fixes, on existing Tailwind markup | — |
| **1** | Foundation: Tailwind removed, tokens, components, both shells | 4 layouts + ~24 components |
| **2** | Public pages: home, events, about, contact, 4 rules pages | 8 |
| **3** | Auth + profile | 10 |
| **4** | Dashboard + showcase detail pages (tournament, venue) | 3 |
| **5** | Admin CRUD + user management | 24 |

Phase 1 is the architectural work specified below. Phases 2–5 are conversions against it and get their own implementation plans.

---

## 4.1 Amendment, 2026-08-31 — two visual registers

The original brief set one rule for the whole app: *thin 1px borders instead of heavy
shadows*. Sizing up Phase 2 showed all eight public pages leaning on gradients, blurs and
glow shadows — 32 instances — so converting them literally meant flattening the shop
window. The owner's ruling: **keep borders-not-shadows for the dashboard; the public pages
may use gradients.**

That is two registers, not an exception, so it is built as two:

| | Dashboard (`layouts/app`) | Public (`layouts/public`, `layouts/guest`) |
|---|---|---|
| Separation | 1px `--c-border` hairline | elevation, `--shadow-raised` / `--shadow-float` |
| Fills | flat `--c-surface` | `--gradient-primary` / `--gradient-accent` / `--gradient-surface` |
| Radius | `--radius` 6px | `--radius-lg` 14px |
| Shadow | `--shadow-overlay` only, on dropdowns and modals | free |

**One product, two volumes.** Every gradient stop derives from `--c-primary` or
`--c-accent`, so the public site is louder than the dashboard, not different from it.

Contrast is fixed at the token, not left to each page:

- `--gradient-primary` carries `--c-primary-ink` — measured 8.72:1 / 9.93:1 on the light
  stops, 7.89:1 / 6.91:1 on the dark.
- `--gradient-accent` carries `--gradient-accent-ink`, **never white**. White is 3.77:1 on
  coral and 2.15:1 on amber; both fail. Dark ink gives 5.09:1 and 8.92:1. Note this is a
  *different token* from `--c-accent-ink`, which is white and belongs to
  `--c-accent-strong` fills.

**The boundary is enforced, not documented.** `resources/css/5-public/` is the only place
these tokens may be referenced (plus the public shell and page files), and
`tests/Feature/PublicRegisterTest.php` fails the build otherwise. A second assertion
catches the sidestep — a raw `box-shadow` value outside the public register. Both were
proven to fire by injecting a violation. The check fences the *tokens*, not the word
"gradient", because `_meter.css` uses `repeating-linear-gradient` for the chip stack: one
colour repeated is a pattern, not decoration.

---

## 4.2 Amendment, 2026-08-31 — auth pages take the dashboard register

**Decision (owner):** login, register, password reset, password confirmation and email
verification use the **dashboard register** — flat `--c-surface`, a 1px `--c-border`
hairline, no gradient and no elevation.

Signing in is entering the app, not being sold it, and the guest panel is 26rem of mostly
input fields: a gradient there would sit behind form controls rather than behind anything.
The panel now matches the dashboard a member lands on a second later.

**This required splitting a stylesheet, and the split is the point.** `_shell-public.css`
defined *both* shells — `.public*` and `.guest*` — and `PublicRegisterTest` allows that file.
So the guest shell sat inside the gradient fence by accident of file organisation: a gradient
could have landed on `.guest__panel` with no test objecting, though nobody had decided auth
belonged to the public register.

`.guest*` now lives in `2-layout/_shell-guest.css`, which is deliberately **not** on
`PublicRegisterTest::ALLOWED`. Verified by adding `var(--gradient-panel)` there and watching
the build fail. A fence that permits something by accident is not a fence.

---

## 5. Phase 0 — Correctness & cleanup

### 5.1 `is_active` crash (blocker)

`routes/web.php:63` queries `PokerSeason::where('is_active', true)`. That column does not exist — it is `is_current`. The `/rules/points-structure` page throws. One-word fix; it is the only `is_active` reference in the codebase.

### 5.2 Admin gating

Every authenticated user can currently create, edit and delete seasons, venues, tournaments, results, points structure and venue points. Only `/users` is gated, via seven repeated `abort_unless` calls.

**Decision: the entire `/poker` prefix becomes admin-only.**

New `App\Http\Middleware\EnsureUserIsAdmin`, aliased `admin` in `bootstrap/app.php`, applied to the `/poker` group and `/users`. The `abort_unless` calls in `UserController` are removed in favour of it.

Because `/poker` becomes fully admin-only, four player-facing endpoints move out of it. Without this, self-registration from `/dashboard` and `/events` breaks, as does every "details" link:

| Current | Becomes | Access |
|---|---|---|
| `poker.tournaments.show` | `tournaments.show` — `GET /tournaments/{tournament}` | any authenticated user |
| `poker.tournaments.register` | `tournaments.register` | any authenticated user |
| `poker.tournaments.unregister` | `tournaments.unregister` | any authenticated user |
| `poker.seasons.show` | `seasons.show` — `GET /seasons/{season}` | any authenticated user |

Admin-only controls inside `tournaments/show` (register-another-user, edit/delete, results entry) stay in that view behind an `is_admin` check. The controller already computes `$availableUsers` this way.

Call sites to update: `dashboard.blade.php:62,127,134`, `events.blade.php:85,90,122`, `poker/tournaments/index.blade.php:42`, `poker/venues/show.blade.php:183`, `poker/tournaments/show.blade.php:100,110,273`.

### 5.3 `scheduled_at` vs `start_time`

These are not redundant. The tournament form labels them **"Registration Closes (Scheduled At)"** and **"Start Date & Time"**. They are two real concepts used interchangeably in code.

Canonical semantics:

- `scheduled_at` — registration cutoff.
- `start_time` — when play begins.

Corrections:

| Location | Change |
|---|---|
| `DashboardController` upcoming filter | `scheduled_at` → `start_time` |
| `PokerTournamentController::show` `$isPast` | `scheduled_at` → `start_time` |
| `register()` / `unregister()` guards | stay on `scheduled_at`; copy changes to "Registration has closed" |
| `is_late_entry` computation | stays on `scheduled_at` (already correct) |
| create/update validation | add `scheduled_at` ≤ `start_time` |

### 5.4 Contact and sponsorship forms

Both post to `action="#"` and do nothing.

`ContactController@store` on `POST /contact`, plus `App\Mail\ContactSubmission` and a Blade mail view. Validation on name, email, subject, message. Honeypot field. `throttle:5,1`. Recipient from `config('mail.league_contact')`, backed by a new `LEAGUE_CONTACT_EMAIL` in `.env` and `.env.example`, defaulting to `MAIL_FROM_ADDRESS`. Works immediately under `MAIL_MAILER=log`.

Both `contact.blade.php` and the sponsorship form in `about/index.blade.php` post to it with a hidden `type` field (`general` / `sponsorship`) that selects the subject line.

### 5.5 Vue / PrimeVue removal

`resources/js/components/Dashboard.vue` is a 23-line "Click Me" counter demo. The real UI is Blade + Alpine.

Delete `Dashboard.vue` and `vue-shims.d.ts`; drop `vue`, `primevue`, `@primevue/themes`, `primeicons`, `@vitejs/plugin-vue`, `vue-tsc`; remove the Vue plugin and the `vue` alias from `vite.config.js`; remove the mount block from `app.ts`.

### 5.6 Cosmetics and dead code

- `APP_NAME` → `"First to Act Poker"` in `.env` and `.env.example`.
- Replace the stock Laravel `README.md` with a project readme.
- Delete `welcome.blade.php`, `about/mission.blade.php`, `about/sponsors.blade.php` (zero references).

---

## 6. Phase 1 — Foundation

### 6.1 Token layer

Themed by `data-theme` on `<html>`. `:root` carries light; `@media (prefers-color-scheme: dark)` supplies the system default; `[data-theme="dark"]` and `[data-theme="light"]` override both so the toggle wins in either direction. Every colour is defined on bare `:root` first — none may be defined only inside a media or attribute block.

| Token | Dark | Light | Purpose |
|---|---|---|---|
| `--c-bg` | `#0B0F19` | `#F4F7FC` | page ground — the 60% |
| `--c-surface` | `#161F33` | `#FFFFFF` | cards, rail, menus — the 30% |
| `--c-surface-raised` | `#1C2740` | `#EEF2F9` | derived: row hover, nested panels |
| `--c-border` | `#1E293B` | `#E2E8F0` | all 1px separators |
| `--c-text` | `#E2E8F0` | `#0F172A` | body text |
| `--c-text-muted` | `#94A3B8` | `#475569` | derived: labels, captions |
| `--c-primary` | `#06B6D4` | `#1E40AF` | actions — the 10% |
| `--c-primary-hover` | `#22D3EE` | `#1E3A8A` | derived |
| `--c-primary-ink` | `#0B0F19` | `#FFFFFF` | text on primary fill |
| `--c-accent` | `#EF4537` | `#EF4537` | destructive, alerts |
| `--c-accent-strong` | `#D63A2C` | `#D63A2C` | derived: small text on accent fill |
| `--c-accent-ink` | `#FFFFFF` | `#FFFFFF` | text on accent fill |

**Measured contrast:**

| Pair | Ratio | Verdict |
|---|---|---|
| `#E2E8F0` on `#0B0F19` | 15.8:1 | pass |
| `#0F172A` on `#F4F7FC` | 17.4:1 | pass |
| `#94A3B8` on `#0B0F19` | 7.5:1 | pass |
| `#475569` on `#F4F7FC` | 7.1:1 | pass |
| `#06B6D4` on `#0B0F19` | 7.8:1 | pass |
| `#1E40AF` on `#FFFFFF` | 8.8:1 | pass |
| `#FFFFFF` on `#EF4537` | **3.8:1** | large/bold text and UI only |
| `#FFFFFF` on `#D63A2C` | 4.7:1 | pass |

Two derived values exist specifically to satisfy AA:

- Light-theme muted text is `#475569`, not the more obvious `#64748B` — that measures 4.43:1 on `#F4F7FC` and fails.
- `--c-accent-strong` covers small text on coral fills. Coral itself is unchanged wherever it is actually seen.

**Other tokens:** spacing on a 4px base (`--space-1` … `--space-12`); radii `--radius-sm: 3px`, `--radius: 6px`, `--radius-pill: 999px`; `--border-width: 1px`; a single `--shadow-overlay` used only by dropdowns and modals.

### 6.2 Typography

Self-hosted woff2 under `public/fonts`, removing the `fonts.bunny.net` preconnect and stylesheet link. If the font files cannot be fetched at build time, fall back to serving the same three families from `fonts.bunny.net` and record the deviation.

| Role | Family | Treatment |
|---|---|---|
| `--font-display` | Archivo Expanded | uppercase; `letter-spacing` `0.08em` at label sizes → `-0.01em` at display sizes; weights 600/700 |
| `--font-body` | Archivo | weights 400/500/600 |
| `--font-mono` | IBM Plex Mono | `font-variant-numeric: tabular-nums` applied globally to numeric cells |

Fluid scale:

```
--step--2  0.6875rem                      eyebrows, micro labels
--step--1  0.8125rem                      captions, table meta
--step-0   0.9375rem                      body
--step-1   1.125rem
--step-2   1.5rem
--step-3   2rem
--step-4   clamp(2.5rem, 5vw, 3.5rem)     page titles
--step-5   clamp(3rem, 8vw, 5.5rem)       hero numerals
```

### 6.3 CSS architecture

Single `resources/css/app.css` entry, importing in cascade order:

```
1-base/        _reset.css  _tokens.css  _typography.css
2-layout/      _shell-app.css  _shell-public.css  _primitives.css
3-components/  _btn _card _table _form _badge _meter _nav
               _dropdown _modal _alert _pagination _stat _empty _avatar
4-pages/       one file per page, added by the phase that converts it
```

Three conventions, chosen to prevent the system rotting into unmaintainable specificity:

1. **Components use single-class selectors only.** BEM-lite: `.card`, `.card__header`, `.card--flush`. No element selectors for components, no nesting beyond state and modifier. This specifically prevents element-vs-class selectors cancelling each other's padding.
2. **Components never own outer spacing.** All margins belong to layout primitives — `.l-stack > * + *`, `.l-cluster`, `.l-grid`, `.l-container`. A component can be placed anywhere without dragging gaps with it.
3. **Utilities are capped at roughly 15**, all `.u-` prefixed. Exceeding that means Tailwind has been rebuilt by accident and the component layer is wrong.

`[x-cloak] { display: none !important; }` lives here and replaces the `style="display: none"` attributes in `components/dropdown.blade.php` and `layouts/public.blade.php`.

### 6.4 Components

Rewritten (15): `application-logo`, `auth-session-status`, `dropdown`, `dropdown-link`, `input-error`, `input-label`, `modal`, `nav-link`, `responsive-nav-link`, `section-badge`, `text-input`, `tournament-badge`, and the three button components.

**Consolidated:** `primary-button`, `secondary-button` and `danger-button` become one `<x-btn variant="primary|ghost|danger" size="sm|md">`.

**New:** `<x-card>`, `<x-field>` (label + control + error as a unit), `<x-stat>`, `<x-meter>`, `<x-table>`, `<x-rank>`, `<x-alert>`, `<x-page-header>`, `<x-empty-state>`, `<x-avatar>`, `<x-theme-script>`.

`<x-alert>` replaces flash-message markup currently duplicated in every index view. `<x-theme-script>` holds the pre-paint theme script once instead of copy-pasted in two layouts.

Dropdowns and modals are the **only** exception to borders-not-shadows: a floating menu separated by a 1px border alone reads as broken. They use `--shadow-overlay`; nothing else does.

The 8 fixed rotation angles in `tournament-badge.blade.php:23` become 8 static `.chip-mark--{deg}` classes, removing that inline style without needing the custom-property escape hatch.

### 6.5 Shells and navigation

**App shell** (`layouts/app.blade.php`) — a top bar on `--c-surface` with a 1px bottom border. *(Amended 2026-08-31: this originally specified a 240px left rail. The owner reviewed the built rail and rejected the concept — a permanent sidebar is the wrong shape for this app. The grouping it introduced is kept: eight admin destinations reached through three grouped menus rather than one flat dropdown.)*

Brand: `header_logo.png` plus the wordmark "First to Act Poker", then Dashboard, then League / Play / Setup menus, then theme toggle and user menu:

```
Dashboard
League    Seasons · Venues · Tournaments
Play      Results · Registrants · Venue Points
Setup     Points Structure · Players
——
theme toggle · user menu
```

League, Play and Setup render only for admins (they are admin-only routes after Phase 0). Below 900px the rail becomes a focus-trapped drawer driven by Alpine.

This replaces eight admin links buried in a top-bar dropdown — the worst usability problem in the current UI.

**Public shell** (`layouts/public.blade.php`) — top bar, centred nav, theme toggle, Log in / Join. Wide editorial measure.

**Guest shell** (`layouts/guest.blade.php`) — centred panel on the ground colour, deliberately quiet.

### 6.6 Signature element — the chip-stack meter

Points render as stacked chips rather than a smooth progress bar:

```html
<div class="meter" style="--meter-fill: 86%">
  <div class="meter__track"><div class="meter__fill"></div></div>
  <span class="meter__value">860</span>
</div>
```

`.meter__fill` uses a `repeating-linear-gradient` of fixed-width segments separated by 1px gaps, so a longer bar literally reads as more chips stacked. Pure CSS, no images. This is the sole approved use of the custom-property escape hatch.

Appears on: season leaderboard, venue-points leaderboard, venue frequency stats, dashboard season standing, points-structure ladder. One idea in five places — a signature rather than an ornament. It also resolves the dynamic width at `poker/seasons/show.blade.php:160`.

### 6.7 Motion

- One orchestrated reveal: the next-event panel on home and dashboard, on load.
- Everything else: 120–160ms hover and focus transitions.
- `@media (prefers-reduced-motion: reduce)` disables all transitions and animations.

### 6.8 Accessibility floor

- Visible focus ring on every interactive element: 2px `--c-primary`, 2px offset. Never removed without replacement.
- Contrast per the table in 6.1.
- Rail is a labelled `<nav>` landmark; the mobile drawer traps focus and restores it on close.
- Tables carry `<caption>` and `scope` attributes.
- Theme toggle is a real `<button>` with `aria-pressed`.
- Full keyboard operation for dropdowns and modals, including Escape to dismiss.

### 6.9 Build changes

Removed: `tailwindcss`, `@tailwindcss/forms`, `@tailwindcss/vite`, `tailwind.config.js`, and the Tailwind entry in `postcss.config.js`.
Added: `postcss-import`. `autoprefixer` stays. Vite inputs are unchanged.

---

## 7. Copy conventions

Interface language is plain and consistent; poker vernacular is used only where it is literally accurate.

- The dashboard is **Dashboard**, not "Deck".
- Season points are **Standings**, not "Chip count" — a chip count is a live stack in a running tournament.
- Buttons name their outcome and keep that name through the flow: "Register" produces "Registered".
- Errors state what happened and how to fix it: "Registration has closed for this tournament", not "Cannot register".
- Empty states invite an action rather than reporting absence.

---

## 8. Verification

A 68-view rewrite needs mechanical verification, not spot-checking.

1. **Route smoke test (new).** GET every registered route as guest, player and admin; assert no 5xx. This is what catches a Blade file broken mid-conversion, and no equivalent exists today.
2. **Grep gates.** Zero Tailwind class patterns remaining in `resources/views`. Zero `style="` in `resources/views` except the approved `style="--…"` form.
3. **Existing suite.** All 13 feature test files stay green; route-rename updates from Phase 0 applied.
4. **Build.** `npm run build` succeeds; output bundle contains no Tailwind artefacts.
5. **Phase 0 regression tests.** Admin middleware blocks non-admins on every `/poker` route and admits admins; non-admins still reach the relocated `tournaments.show`, `seasons.show`, register and unregister; `/rules/points-structure` returns 200; registration closes on `scheduled_at` while "upcoming" keys off `start_time`; contact form validates, honeypot rejects, mail dispatches to the configured address.
6. **Visual.** Screenshots of key pages in both themes at desktop and mobile widths.

TDD applies throughout: tests are written before the code they cover.

---

## 9. Open decisions

1. **Archivo Expanded validation.** The display face is the deliberate risk. Phase 1 ends by converting `poker/seasons/show.blade.php` — the most demanding page, exercising the app shell, tables, stat tiles and the chip-stack meter — and presenting it in both themes at desktop and mobile widths for approval before Phases 2–5 commit the remaining views.
2. **Route relocation in Phase 0** (section 5.2) follows from "all of `/poker` is admin-only" but was inferred rather than explicitly confirmed. Confirm before implementing.
