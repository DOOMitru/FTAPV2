# Project exit audit — the design system is complete

**2026-08-31.** Tailwind is removed. Every Blade view in the application renders on the
hand-built design system.

## The measured result

| | |
|---|---|
| Suite | **108 passed, 0 failed** |
| Views converted | **86 of 86** |
| `ConvertedViewsTest::NOT_YET_CONVERTED` | **empty** |
| `@tailwind` directives | 0 |
| `tailwind.config.js` | deleted |
| Tailwind dependencies | 0 |
| CSS bundle | **65,895 → 46,823 bytes** (11,371 → 7,614 gzipped) — 29% / 33% smaller |
| Final audit | **116 renders** — 29 pages × 2 widths × 2 themes, no overflow, every theme stamped, Archivo everywhere |

## What the phases actually cost

| Phase | Scope | Utility instances removed |
|---|---|---|
| 0 | Correctness fixes on existing markup | — |
| 1 | Tokens, components, both shells | — |
| 2 | 8 public pages | ~2,400 |
| 3 | 10 auth + profile views | 689 |
| 4 | dashboard + 2 showcase pages | 1,189 |
| 5 | 25 admin CRUD views | 1,930 |

## The five guards that outlive the project

These are the reason the conversion cannot quietly regress:

| Test | What it catches |
|---|---|
| `ConvertedViewsTest` | Tailwind returning to any view. Its allowlist is empty, so **both** assertions now cover the whole codebase. |
| `ModifierClassGuardTest` | a layout modifier used without its base class — which silently does nothing |
| `PublicRegisterTest` | gradient and elevation tokens outside the public register, plus any raw `box-shadow` in the app shell |
| `InlineStyleGuardTest` | inline CSS, both the `style` attribute and `<style>` blocks |
| `ContentPreservationTest` | that a rewrite has not dropped content — rules pages, events, dashboard, season, tournament and venue leaderboards, asserted on data and never on markup |

## The lesson this project kept teaching

**Computed-style checks pass while a page looks wrong.** Every significant defect found here
passed every assertion that existed at the time:

- gradients whose two stops were 1.14:1 apart, rendering as flat fills
- `Texas Hold&#039;em` — a value escaped correctly for HTML, then parsed as JS
- a 300px hole from two selectors colliding at equal specificity
- a podium whose DOM order announced the runner-up first
- a season edit form offering to wipe its own dates
- and finally: **removing Tailwind reverted `box-sizing` to `content-box` across the entire
  application**, and 108 tests stayed green

Screenshot the page. The suite tells you it responded, not that it works.

## Two security fixes made along the way

Both were stored XSS in the same class — a value escaped for HTML that then crosses into a
JavaScript parsing context, where Blade's escaping no longer applies:

1. **`onsubmit="return confirm('{{ $model->name }}')"`** — the browser HTML-decodes an
   attribute *before* handing its contents to the JS parser, so a name containing an
   apostrophe closes the string literal. Introduced during this project and caught by
   review; fixed with a delegated `data-confirm` handler.
2. **A `<script>` block building a JS object literal from player names** — inside a script
   element the parser does not decode entities at all, so a name containing `</script>`
   ends the element and executes. Pre-existing; fixed by moving the data to a `data-`
   attribute parsed as JSON.

**There is now no inline JavaScript in any view.** Three modules — `confirm.ts`,
`autofill.ts`, `dependent-select.ts` — all read through `dataset`, so values stay data.

## Still open — these need the owner, not code

1. ~~**The delete-account modal's focus trap has never run in a browser.**~~
   **Verified by hand on 2026-09-01.** Phase 1 fixed it (`x-init` + `$watch` never fired on
   a modal rendered already-open) and `ProfileTest` covers the server-side reopen, but
   headless Chromium cannot drive Alpine's `x-show`, so the focus and scroll effects needed
   a person. Both paths check out: focus lands inside the dialog, Tab cycles within it,
   Escape closes it, and the page behind does not scroll — including on the wrong-password
   path that renders the modal already open, which is the case that was broken.

2. **Email verification is half-wired, and predates this project.**
   `routes/web.php:113` gates the dashboard on `->middleware(['auth', 'verified'])` and
   `RegisteredUserController:46` fires `Registered` — but **`User` does not implement
   `MustVerifyEmail`**; the import is commented out at `app/Models/User.php:5`. Laravel
   therefore never sends the verification mail, and the profile page's "your email is
   unverified" block is unreachable. `EmailVerificationTest` passes because it calls
   `markEmailAsVerified()` and signed URLs directly. Uncommenting line 5 is the likely fix
   but changes behaviour for existing users.

3. **The accent gradient's hue drift.** `--gradient-accent`'s second stop sits 25° off the
   brand hue — introduced when the accent panel was deepened for readability. See the
   section at the end of `docs/RESUME-HERE.md`; the accent is otherwise an exact match for
   the logo's `#EF4537`. Changing it is six values in one file.

4. **`components/tournament-badge` still has no callers.** It was redesigned onto the system
   rather than deleted, at the owner's request, but nothing renders it.
