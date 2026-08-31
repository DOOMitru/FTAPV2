# Resume here

**Last worked: 2026-08-30.** Design-system project for First To Act Poker.

## Where things stand

Phase 0 (correctness + cleanup) is **complete and committed**. Phase 1 (the design
system) is **planned but not started**.

Suite: **93 passed, 0 failed.** Run `php artisan test`.

### Read these first, in order

1. `docs/superpowers/specs/2026-08-30-design-system-design.md` — the approved design:
   "Under the Gun" direction, the full token palette, typography, CSS architecture,
   component inventory, both shells, the chip-stack meter signature element, and the
   6-phase breakdown.
2. `docs/superpowers/plans/2026-08-30-phase-1-foundation.md` — 12 tasks, ready to execute.
3. `docs/PHASE-0-HANDOFF.md` — what Phase 0 changed and why.

### The next action

Execute Phase 1 Task 1. The plan is written to be run task-by-task with a fresh
implementer per task and a review after each — that process caught four defects in the
plan itself during Phase 0, so it earned its cost.

**Phase 1 stops at Task 10** and presents `poker/seasons/show` in both themes for
approval before the remaining ~60 views commit to Archivo Expanded. That gate is
deliberate: rejecting the direction there costs one page, not sixty.

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

## Open questions for the owner

1. **Commit the three uncommitted test files?** `RouteSmokeTest.php` (modified),
   `EmptyStateSmokeTest.php` and `ContentPreservationTest.php` (new).
2. **Should the 10 new hardening tests get a review pass?** They pass and the Blade detector
   is proven, but they have not had the per-task scrutiny every Phase 0 task received, and
   Phase 1 leans on them.
3. **`.superpowers/sdd/` may be deleted.** It holds the 539-line decision ledger and 17
   agent reports, all gitignored. Everything with forward value has been copied into this
   file. Delete it whenever convenient.
