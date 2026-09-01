# Resume here

**Last worked: 2026-08-31.** Design-system project for First to Act Poker.

## Where things stand

**Two projects are complete.** The design-system conversion (Phases 0-5) moved
all 86 views off Tailwind onto hand-built CSS — see `docs/PHASE-5-EXIT-AUDIT.md`.
The red-and-black aesthetic refresh then moved the whole app onto the logo's
palette — see `docs/RED-BLACK-EXIT-AUDIT.md`, with its spec and plan in
`docs/superpowers/specs/2026-08-31-red-black-redesign-design.md` and
`docs/superpowers/plans/2026-08-31-red-black-refresh.md`.

Suite: **135 passed, 0 failed.** Run `php artisan test`.

The site was 27x more blue than red while wearing a red logo; it now measures
4.1% red and 0.1% blue across the public pages. Colour is enforced rather than
asserted: `TokenContrastTest` parses the real token file and fails the suite on
any pair that drops below AA.

Since then: email verification was switched on and enforced (existing accounts
grandfathered), and a **player approval gate** was added -- anyone may register,
but only an approved account may enter a tournament. See
`docs/PLAYER-APPROVAL-AUDIT.md`.

Suite: **169 passed.**

Also added: mail configuration guarding (`php artisan mail:check`), an approval
notification, and **sponsor management** -- sponsors are now a managed resource
under Setup rather than a hardcoded array. See `docs/SPONSOR-MANAGEMENT-AUDIT.md`.

Suite: **196 passed.**

Open items are listed at the end of `docs/SPONSOR-MANAGEMENT-AUDIT.md`,
`docs/PLAYER-APPROVAL-AUDIT.md` and `docs/RED-BLACK-EXIT-AUDIT.md`. The most
consequential: **MAIL_MAILER is still `log`**, so invite and verification emails
reach nobody -- the admin-facing copyable links cover it for now, but a real
mailer is needed before this ships. The sponsor wall is also empty until real
sponsors are added.

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

## Known bug — fixed in Phase 1 Task 12, **still unverified in a browser**

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
behaviour itself is guarded; only the focus/scroll side effects are missing. **They have
never been exercised by a human.** This is open item 1 in `docs/PHASE-5-EXIT-AUDIT.md`.

## Open question for the owner

**`.superpowers/sdd/` may be deleted.** It holds the 539-line decision ledger and 17
   agent reports, all gitignored. Everything with forward value has been copied into this
file. Delete it whenever convenient.

## The accent gradient's hue drift — now unblocked

The owner deferred this until Phase 5 was done, because changing it mid-conversion would
have left unconverted admin views on the old colour and made the app visibly two-toned.
**Phase 5 is done, so this is now a single-file change plus a rebuild whenever wanted.**

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
