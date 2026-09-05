# Resume here

**Last worked: 2026-09-05.** First to Act Poker — a league app for free-to-play
poker nights in Regina.

## Where things stand

Suite: **431 passed.** Run `php artisan test`.

**The design-system work is finished and is no longer what this project is
about.** Phases 0-5 moved all 86 views off Tailwind onto hand-built CSS
(`docs/PHASE-5-EXIT-AUDIT.md`); the red-and-black refresh then moved the app onto
the logo's palette (`docs/RED-BLACK-EXIT-AUDIT.md`). Colour is enforced rather
than asserted: `TokenContrastTest` parses the real token file and fails the suite
on any pair below AA. Everything since has been features and defect work on top
of that foundation.

**Mail works.** SMTP is configured against Dreamhost, connects, authenticates and
delivers; a real password reset was sent, clicked and completed on 2026-09-05.
`php artisan mail:check` reports the resolved configuration and fails on anything
that would silently not work — a log/array transport, a placeholder from-address
or league contact, and an `APP_URL` pointing at the sending machine.

**The one thing left before players can be invited:** `APP_URL` must be the
public address on whatever host sends real mail. Locally it is
`http://localhost:8000`, which is correct for testing here and fatal in
production — every verification, reset and invite link is built from it, so the
mail arrives, opens, and goes nowhere. `mail:check` gates this; gate deploys on
its exit code.

The 235 imported players are approved and verified but hold random 32-character
passwords, so each needs a password-reset mail to get in. That is the first real
send.

### Open, in rough priority

1. `APP_URL` on the production host (above). Owner's, at deploy time.
2. `docs/` holds six audit documents from finished phases. Their open-items
   sections are largely resolved; treat this file as the index, not them.
3. `.superpowers/sdd/` can be deleted whenever convenient — see the end of this
   file.

Nothing else is known-broken. There are no TODO, FIXME or HACK markers anywhere
in `app/`, `routes/` or `resources/`.

## Standing constraints

- **Never run git commands.** The repository owner runs every git operation manually.
  Convert every commit step into a hand-off. Two subagents breached this in Phase 0 by
  running read-only `git show`/`git diff`; dispatch prompts must name those explicitly.
- **No inline CSS, anywhere.** The only permitted `style` attribute is one setting only
  custom properties (`style="--meter-fill: 86%"`). Enforced by `InlineStyleGuardTest`,
  which also rejects `<style>` blocks. There is likewise no inline JavaScript left.
- **The app has no browser-based tests** — the owner declined Laravel Dusk. Alpine
  dropdowns, the modal focus trap, the theme toggle and responsive breakpoints are verified
  by hand. Headless Chromium was used throughout for screenshots and computed-style
  measurement, but it cannot drive Alpine's `x-show`.
- **Screenshots of dumped HTML render in the WRONG FONT unless you fix the font URLs.**
  The faces are self-hosted at the root-relative `/fonts/archivo.woff2`, which a
  `file://` page resolves to a path that does not exist — so the page silently falls back
  to a system font. Box layout still measures correctly; anything about type does not.
  A whole session of "verified visually" was done on the wrong font before this was
  noticed, and it mattered exactly once: a button label sitting 2px high. Re-declare the
  `@font-face` rules with absolute `file://` paths in the dumped copy before measuring type.
- **Chromium here is snap-confined**: it cannot read or write `/tmp`, and enforces a
  500px minimum window width. Use a directory under `$HOME` for screenshot work.

## The safety net the whole conversion ran on

| Test | What it guards |
|---|---|
| `RouteSmokeTest` | Every GET route as admin, guest and player: no 5xx, and no literal Blade artifact (`@if`, `{{`) leaking into output. Hard-fails on an unmapped route parameter rather than skipping. |
| `EmptyStateSmokeTest` | The 36 `@forelse`/`@empty` branches across 13 views — none of which had ever executed before this was written. |
| `ContentPreservationTest` | Season show, tournament show and dashboard assert on **data only** (names, point totals, counts), never markup — so the rewrite can change every tag and still be checked. |

The Blade-artifact detector was proven to fire by injecting a literal `@if` into a view
and confirming the sweeps failed. It is not an assumption.

Five more guards were added as the conversion went on; all eight are listed in
`docs/PHASE-5-EXIT-AUDIT.md`.

## Deferred minor findings from Phase 0 — never actioned

Triaged as LEAVE during Phase 0's final review and still open. None blocks anything; they
are recorded so they are not rediscovered as if new:

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

## Decisions taken since the conversion that still bind

- **`is_current` is the single answer to "which season is current".** `PokerSeason::current()`
  is the only way to ask; the home page used to match date ranges and fall back to the most
  recent season, so it could name a different season from the dashboard. A test scans
  `app/` and `routes/` for the lookup written out by hand, which is how the second
  definition arrived the first time.
- **Venue points store their season.** It used to be derived at read time from whether
  `event_date` fell inside a season's range, so editing a season's dates moved points
  between seasons and changed who qualified for the finale, silently. The column is
  nullable because a date outside every season has no answer; the form refuses such a date
  rather than recording points that count toward nothing.
- **A registrant cannot be removed once any result exists for that tournament**, not even
  by an admin. A place is a position in a field — tenth of ten — so removing someone
  afterwards makes every recorded finish describe a tournament that never happened.
  Registering someone LATE stays open and is handled: the shift hook moves recorded places
  down to match. There is no way back, because removal is ambiguous where addition is not.
- **The finale is earned, not ranked.** Each season sets points, wins and venue-point
  targets, and everyone meeting all three plays. The public pages said "the top 20 on the
  leaderboard" and a fact tile said "Top 10 Players"; both are gone, and a test refuses any
  rank-cut phrasing. The pages deliberately do not quote the threshold figures — those are
  per-season and published on the season page, and a number written into a rules page goes
  stale silently.
- **`Paginator::defaultSimpleView` is deliberately NOT set.** The design-system pagination
  view windows page numbers, so it calls `total()` and `lastPage()`, which a simple
  paginator does not have. Pointing it there made the first ever `simplePaginate()` call a
  fatal error. Simple pagination falls back to Laravel's stock view: unstyled, but working.

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

## Known bug — fixed in Phase 1 Task 12, **verified by hand 2026-09-01**

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
behaviour itself is guarded. The focus and scroll side effects cannot be asserted --
headless Chromium does not meaningfully drive Alpine's `x-show` -- and were **verified by
hand on 2026-09-01**: focus lands inside the dialog, Tab cycles within it, Escape closes
it, and the page behind does not scroll, on both the plain open and the wrong-password
path that renders the modal already open.

## The accent gradient's hue drift — RESOLVED, verified 2026-09-05

This was the last substantive item carried forward from the design-system work.
It is done, and the section is kept only so the finding is not rediscovered as
if new.

The fault was that `--gradient-accent` kept the original's coral-to-amber
structure: stop A `#8A2B1E` at hue 7deg, stop B `#A2570C` at **hue 30deg —
amber, not red**, on the largest accent surface on the site. Two ways forward
were costed: keep the amber ramp for its 25deg of hue travel, or make it a red
ramp at the logo's hue and let lightness carry the gradient.

**The red ramp was taken.** Measured on 2026-09-05:

| | value | hue | vs white |
|---|---|---|---|
| stop A | `#B02718` | 5.9deg | 6.67:1 |
| stop B | `#6B140C` | 5.1deg | 12.14:1 |
| between the stops | | 0.9deg travel | 1.82:1 |

That clears every bar the analysis set: both stops above 4.5:1 on white, and
1.82:1 between them against a 1.5:1 floor. The logo mark is hue 4.6deg, so the
panel is now within about a degree of the brand colour rather than 25deg off it.

**The `--c-accent` family has since been retired entirely** — the tokens file
records why: the primary is the brand red, so there is no second brand hue for an
accent to carry, and keeping one would mean two answers to one question. What the
retired `--c-accent-strong` existed for is now solved once, for the primary.

## Open question for the owner

**`.superpowers/sdd/` may be deleted.** It holds the 539-line decision ledger and
17 agent reports, all gitignored. Everything with forward value has been copied
into this file. Delete it whenever convenient.
