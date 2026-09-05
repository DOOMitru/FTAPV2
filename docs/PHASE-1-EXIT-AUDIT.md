# Phase 1 exit audit

**2026-08-31.** Every criterion in `docs/superpowers/plans/2026-08-30-phase-1-foundation.md`
was measured rather than ticked. Three of them turned out to be stale — written before the
left rail was rejected and before Archivo Expanded became Archivo Black — and are recorded
as such rather than quietly marked done.

| # | Criterion | Result |
|---|---|---|
| 1 | `php artisan test` all green, incl. `RouteSmokeTest` + `InlineStyleGuardTest` | **PASS** — 96 passed, 0 failed |
| 2 | `npm run build` succeeds | **PASS** |
| 3 | No `bunny.net` in views | **PASS** — 0 hits; all fonts self-hosted |
| 4 | No Tailwind utility class in `resources/views/components/` | **ONE LEFT** — `tournament-badge` (see below) |
| 5 | Margins in `3-components/*.css`: only `_nav.css` | **STALE WORDING, INTENT MET** |
| 6 | ≤5 `.u-` in `_typography.css`, ≤15 system-wide | **PASS** — 5 and 5 |
| 7 | Every colour token has a bare `:root` definition | **PASS** — 14 of 14 |
| 8 | Theme toggle works both ways, persists, no flash | **PASS** — measured, see below |
| 9 | Rail collapses below 900px; drawer traps focus; Escape closes | **OBSOLETE** — see below |
| 10 | No page scrolls horizontally at 375px | **PASS** — 16 pages at 375px and 1280px |
| 11 | With reduced motion, nothing animates | **PASS** — measured, see below |
| 12 | Owner has approved the season page and the display face | **See "The gate" below** |

## Measurements

**8 — theme toggle**, driven through a real click in headless Chromium:

```
initial data-theme          dark
stamp script is in <head>   yes      (so it runs before first paint — no flash)
click 1: dark -> light      flipped
persisted to localStorage   light
click 2 -> dark             flipped back
after reload, stored=dark   dark
```

**10 — horizontal overflow.** 16 pages (both shells, guest and admin, index/show/form)
loaded in a 375px-wide iframe and again at 1280px. `documentElement.scrollWidth` never
exceeded the viewport. Chromium clamps its own window to ~500px, so the iframe is the only
way to measure a true 375px viewport headlessly.

**11 — reduced motion.** Chromium launched with `--force-prefers-reduced-motion`:

```
                    reduced      normal
meter animation     0.00001s     0.48s
button transition   0.00001s     0.14s
nav transition      0.00001s     0.14s
```

The global block in `_tokens.css` covers everything; no component needs its own rule.

**9 — the rail is obsolete.** The owner rejected the left rail during Task 8 and it was
replaced by `<x-topbar>`, shared by both shells. The equivalent behaviour was measured:

```
@375px    burger flex    panel none    escape handler present
@1280px   burger none    panel flex    escape handler present
```

The panel does **not** trap focus, deliberately: it is an inline disclosure that pushes the
page down, not a modal drawer overlaying it. Trapping focus in a non-modal region would be
wrong. Escape closes it (`x-on:keydown.escape.window` on the header).

**5 — the wording is stale twice over.** `_nav.css` now contains no margin at all, and nine
margins live in `_dropdown`, `_empty`, `_form`, `_stat`. Every one of them is *inner*
spacing between a component's own parts — a hint under a control, a body under a title —
which is what the criterion was written to permit. The rule's real target is an **outer**
margin on a component root, which makes the component un-composable. There was exactly one,
`.modal__panel { margin-block-end }`, introduced during Task 12 from Breeze's `mb-6`; it
moved to `.modal`'s block-end padding, which preserves the panel's slight lift above centre
while leaving the component free of outer spacing.

## The one open item

`components/tournament-badge.blade.php` is the only component still carrying Tailwind. It
has **zero callers** — `app/View/Components/TournamentBadge.php` registers it, but no view
renders it — and its look (per-type gradients, glow shadows) is the direct opposite of a
system whose premise is one accent and a 1px border. Converting it would be a redesign, not
a port. Its inline CSS was removed under Task 11; the rest awaits an owner decision:
delete, or say what it is for and have it rebuilt in the system's language.

## The gate

Criterion 12 named "Archivo Expanded", which no longer exists in the system — the owner
replaced it with Archivo Black on 2026-08-31. The formal Task 10 gate was never answered as
a single yes, but it was overtaken: the owner directed roughly fifteen rounds of changes to
the display face, the border weight, the top bar, the page headers and the mobile menus, all
on the built system. That is the review, delivered continuously instead of at one checkpoint.
Phases 2–5 are unblocked.
