# Resume here

**Last worked: 2026-08-31.** Design-system project for First to Act Poker.

## Where things stand

Phases 0, 1 and **2** are complete and committed. Phase 1's exit criteria were measured,
not assumed — see `docs/PHASE-1-EXIT-AUDIT.md`. Phase 2 converted all eight public pages.

Suite: **107 passed, 0 failed.** Run `php artisan test`.

**38 of 86 views still contain Tailwind — and that number is now a test, not a note.**
`tests/Feature/ConvertedViewsTest.php` names every view still permitted to contain it and
fails in both directions: a converted view picking Tailwind back up, AND an allowlist entry
that has since been converted and should be removed. The second assertion is what stops the
list rotting. Phase 3 removes the auth and profile entries, Phase 4 the three showcase
pages, Phase 5 the admin CRUD — and when the array is empty, deleting Tailwind is a test
going green rather than a judgement call.

The heaviest remaining: `poker/tournaments/show` (553), `dashboard` (325),
`poker/venues/show` (311), `auth/register` (179), `auth/login` (149).

### Read these first, in order

1. `docs/superpowers/specs/2026-08-30-design-system-design.md` — the approved design:
   "Under the Gun" direction, the full token palette, typography, CSS architecture,
   component inventory, both shells, the chip-stack meter signature element, and the
   6-phase breakdown.
2. `docs/superpowers/plans/2026-08-30-phase-1-foundation.md` — 12 tasks, ready to execute.
3. `docs/PHASE-0-HANDOFF.md` — what Phase 0 changed and why.

### The next action

Plan and execute **Phase 3** (auth + profile, 10 views). Write
`docs/superpowers/plans/2026-XX-XX-phase-3-auth.md` the way Phase 2's plan was written, and
**pre-flight it** — the Phase 2 plan had three defects caught that way, one of which (the
contact honeypot) would have silently disabled spam protection.

Guards added during Phase 2 that Phase 3 inherits:

| Test | What it catches |
|---|---|
| `ConvertedViewsTest` | the conversion ledger, in both directions |
| `ModifierClassGuardTest` | `l-grid--wide` without `l-grid` — a modifier alone silently does nothing |
| `PublicRegisterTest` | gradient/elevation tokens leaking into the dashboard |
| `InlineStyleGuardTest` | inline CSS, both the attribute and `<style>` blocks |
| `ContentPreservationTest` | now covers the rules pages, events, and the public empty states |

The one lesson worth carrying: **computed-style checks pass while a page looks wrong.**
Every bug the owner found in Phase 2 — invisible gradients, a double-escaped apostrophe, a
300px hole from a specificity collision, an unreadable panel — passed every assertion I ran
and was obvious in a screenshot. Screenshot each page before calling it converted. Phase 1 built the system; Phases 2–5 apply it to the 50 views
that still carry Tailwind. Tailwind itself is removed at the end of Phase 5 — until then
it loads *after* the design system in `app.css` so unconverted views keep rendering.

Two things must happen during that removal, both already known:
- Publish the pagination view and add `_pagination.css` (deferred out of Phase 1).
- Nothing in a Blade `class` attribute may be the only reference to a Tailwind class.
  Task 12 found one such case — the modal's scroll lock named `.overflow-y-hidden` from
  inside a JS string — and it would have failed silently. Grep the JS and TS for class
  names before deleting Tailwind.

The plan is written to be run task-by-task with a fresh implementer per task and a review
after each — that process caught four defects in the Phase 0 plan and three more in the
Phase 1 plan, so it earned its cost.

## Standing constraints

- **Never run git commands.** The repository owner runs every git operation manually.
  Convert every commit step into a hand-off. Two subagents breached this in Phase 0 by
  running read-only `git show`/`git diff`; dispatch prompts must name those explicitly.
- **No inline CSS in Phase 1.** The only permitted `style` attribute is one setting only
  custom properties (`style="--meter-fill: 86%"`). Enforced by a test the plan adds.
- The app has no browser-based tests. Alpine dropdowns, the modal focus trap, the theme
  toggle and responsive breakpoints are verified **by hand at the Phase 1 review gate** —
  the owner declined Laravel Dusk. Phase 1 rewrites all four, so that manual pass matters.

## The safety net Phase 1 relies on

| Test | What it guards |
|---|---|
| `RouteSmokeTest` | Every GET route as admin, guest and player: no 5xx, and no literal Blade artifact (`@if`, `{{`) leaking into output. Hard-fails on an unmapped route parameter rather than skipping. |
| `EmptyStateSmokeTest` | The 36 `@forelse`/`@empty` branches across 13 views — none of which had ever executed before this was written. |
| `ContentPreservationTest` | Season show, tournament show and dashboard assert on **data only** (names, point totals, counts), never markup — so the rewrite can change every tag and still be checked. |

The Blade-artifact detector was proven to fire by injecting a literal `@if` into a view
and confirming the sweeps failed. It is not an assumption.

## Deferred minor findings — Phase 1 should decide on these

Triaged as LEAVE during Phase 0's final review, but several land in files Phase 1 rewrites:

- Task 1: minor (deferred): both tests assert only HTTP 200; a regression that broke
- Task 1: minor (deferred): inline \App\Models\... FQCNs in the route closure vs `use`
- Task 2: minor (deferred): the implementer's read-only `git show` breach (already ruled).
- Task 3: minor (deferred): guest-redirect test covers only the group-gated route, not the
- Task 3: minor (deferred): provider covers index routes only; no non-admin write-route
- Task 3: minor (deferred): EnsureUserIsAdmin lacks a docblock noting it depends on `auth`
- Task 4: minor (deferred): test_dashboard_excludes_a_tournament_that_has_already_started
- Task 5: minor (deferred): single-arg assertSessionHas('status') checks key presence only
- Task 5: minor (deferred): success message + back()->with() duplicated across the honeypot
- Tasks 6+7: minor (deferred): npm run build rewrote public/build/manifest.json and hashed
- Tasks 6+7: minor (deferred): package-lock.json had unrelated uncommitted changes before
- Task 8: minor (deferred): procedural — a consequential expansion landed before controller
- Final fix wave: minor (deferred): Carbon::parse() in the accessor is redundant given the
- Final fix wave: minor (deferred): FQCN \Illuminate\Support\Carbon instead of a use import.

## Decisions taken during Phase 0 that still bind

- **`/poker` is admin-only in its entirety.** Four player-facing routes were moved out of
  the prefix to keep self-registration working: `tournaments.show`, `tournaments.register`,
  `tournaments.unregister`, `seasons.show`. Do not move them back in.
- **`poker.venues.show` deliberately stayed admin-only** — it is a report, not a player view.
- **Five dead routes were removed** via `->except(...)` because their controllers had no
  matching methods and they returned HTTP 500: `users.create`, `users.store`, and the `show`
  routes for `results`, `registrants`, `venue-points`, `points-structure`. If any is wanted
  later it returns alongside a real controller method and view.
- **`registration_open`** is an accessor on `PokerTournament`. Registration controls gate on
  it; `$isPast` (derived from `start_time`) is only for "has play begun". Do not conflate
  them — there is a real window where registration is closed but play has not started.
- **Contact forms use a `topic` field**, not the `type` field the spec names. The
  implementation is internally consistent across controller, mailable, both views and tests;
  the spec is the outlier. No action needed.

## Four defects found in the plan itself during Phase 0

All four are already corrected in the plan files. Recorded because they show what the
review loop is for:

1. A prescribed test that could never fail — it passed both before and after the fix.
2. `UserController` had five `abort_unless` calls, not the seven the plan claimed.
3. The `is_active` bug was described as a crash. On SQLite it is **silent**: Laravel emits
   `where "is_active" = ?` and SQLite's double-quoted-identifier misfeature degrades the
   unknown identifier to a string literal, returning 0 rows. It would throw on MySQL/Postgres.
4. The README claimed late self-registrations are flagged as late entries. They are refused;
   only admin-entered registrations get the flag.

## Known bug — FIXED in Phase 1 Task 12

**The delete-account modal's focus trap and scroll lock never engaged when it opened on
load.** Fixed on 2026-08-31 by replacing `x-init="$watch('show', ...)"` with `x-effect`,
which runs immediately *and* on change. The scroll lock also stopped using Tailwind's
`.overflow-y-hidden` (named only inside a JS string, so it would have died silently when
Tailwind is removed) in favour of `body.is-modal-open` in `_modal.css`.

The original diagnosis, kept because it explains the class of bug:

`resources/views/components/modal.blade.php` drives both from
`x-init="$watch('show', ...)"`. Alpine's `$watch` deliberately skips its callback on first
evaluation, so it only fires when `show` *changes*. On the wrong-password path,
`profile/partials/delete-user-form.blade.php:17` renders the modal with `show: true`
already set server-side — the watcher never runs, focus is never moved into the modal, and
the body scroll lock never applies. A keyboard or screen-reader user has to hunt for the
dialog that just appeared.

This predates the design-system work and was not introduced by it. It was left alone during
Phase 1 Task 7 because fixing it means changing Alpine wiring inside what was scoped as a
CSS class swap. The fix is small — run the same logic once on init when `show` starts true,
in addition to watching for changes — but it deserves its own change and its own test rather
than being smuggled into an unrelated task.

`tests/Feature/ProfileTest.php` covers the server-rendered `show: true`, so the reopen
behaviour itself is guarded; only the focus/scroll side effects are missing.

## Open questions for the owner

1. **Commit the three uncommitted test files?** `RouteSmokeTest.php` (modified),
   `EmptyStateSmokeTest.php` and `ContentPreservationTest.php` (new).
2. **Should the 10 new hardening tests get a review pass?** They pass and the Blade detector
   is proven, but they have not had the per-task scrutiny every Phase 0 task received, and
   Phase 1 leans on them.
3. **`.superpowers/sdd/` may be deleted.** It holds the 539-line decision ledger and 17
   agent reports, all gitignored. Everything with forward value has been copied into this
   file. Delete it whenever convenient.

## Deferred to after Phase 5 — the accent gradient's hue drift

**Decision (owner, 2026-08-31): do not change the accent until Phase 5 is done.**
Changing it now would leave 14 unconverted admin views on the old colour while the
public site moved, so the app would be visibly two-toned until the conversion finishes.

The facts, so this does not have to be re-derived:

- The accent **already matches the logo exactly**. `public/images/hero_logo.png`'s
  dominant colour is `#EF4537` (22.7% of the mark; 13.9% of `header_logo.png`), and
  `--c-accent` is `#EF4537`. It was sampled, not guessed.
- Five of the six accent values sit at **hue 5deg, identical to the mark** --
  `--c-accent`, `--c-accent-strong`, `--c-accent-hover`, `--dark-accent-text` exactly,
  and `--c-accent-text` within 2deg. They differ only in lightness, for contrast.
- **One value drifted, and it was introduced during Phase 2 Task 5.** Deepening
  `--gradient-accent` for readability kept the original's coral-to-amber structure:
  stop A `#8A2B1E` is hue 7deg (fine), stop B `#A2570C` is **hue 30deg -- amber, not
  red**. That is why the accent panel reads brown rather than as the brand colour, and
  it is the largest accent surface on the site.

Two ways to resolve, both costed:

1. **Keep the amber ramp.** 25deg of hue travel is what makes it read as a gradient
   rather than a flat fill.
2. **Make it a red ramp**, both stops at hue 5deg, so the panel is unmistakably the
   logo colour. A lightness gradient rather than a hue one -- which does work: the
   primary panel's dark ramp is cyan into pale cyan at 0deg apart and reads fine.
   Needs a pair clearing white at 4.5:1 on both stops AND 1.5:1 between them.

**Cost of any accent change: six values in `1-base/_tokens.css`, consumed by 9 rules.
No accent hex is hardcoded anywhere else in `resources/`.** The work is not the
substitution, it is re-deriving the contrast family -- three separate AA failures were
found around the accent during Phases 1-2, which is why there are six values and not one.

After Phase 5 this is a single-file change plus a rebuild.
