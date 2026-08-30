# Phase 0 — commit hand-off

All work is **uncommitted** in the working tree, per your instruction that you run git yourself.

## Recommended: one commit

`routes/web.php` was edited by five of the eight tasks, so a clean per-task split is not
achievable without interactive staging. The changes are also interdependent — the route
relocation exists only to make the admin gating safe, and the smoke test depends on both.
One commit reflects that honestly:

```bash
git add -A
git commit -m "Phase 0: correctness and cleanup fixes

Fix a silent bug where the points-structure page queried a nonexistent
is_active column, so the current season never resolved and the top-3
leaders panel never rendered.

Gate the whole /poker area and user management behind a new admin
middleware. Move the four player-facing routes (tournament show/register/
unregister, season show) out of the /poker prefix first so self-service
registration keeps working for non-admins.

Separate the two tournament datetime concepts that were being used
interchangeably: scheduled_at is the registration cutoff, start_time is
when play begins.

Deliver contact and sponsorship form submissions by email; both forms
previously posted to action=\"#\" and silently discarded input.

Remove five dead routes whose controllers had no matching methods and
which returned HTTP 500: users.create, users.store, and the show routes
for results, registrants, venue-points and points-structure.

Remove the unused Vue/PrimeVue stack, delete three unrouted views, name
the application, and add a real README.

Add a route smoke test covering every GET route as both admin and guest,
which is what found the five 500s above."
```

## If you want granularity instead

Three file-disjoint commits are possible if you stage `routes/web.php` interactively:

1. `git add -p routes/web.php` — take the is_current fix, the relocated routes, the admin
   group and the ->except chains; plus `bootstrap/app.php app/Http/Middleware app/Http/Controllers tests/`
2. `git add app/Mail config/mail.php resources/views/mail resources/views/contact.blade.php resources/views/about/index.blade.php` — plus the remaining `routes/web.php` hunk (POST /contact)
3. `git add package.json package-lock.json vite.config.js resources/js README.md .env.example` — the cleanup

## Before you commit, know this

- **`.env` is modified and gitignored.** Two new/changed keys: `APP_NAME="First To Act Poker"`
  and `LEAGUE_CONTACT_EMAIL="hello@example.com"`. Add `LEAGUE_CONTACT_EMAIL` to any deployed
  environment or contact-form mail falls back to `MAIL_FROM_ADDRESS`.
- **`package-lock.json` was regenerated cleanly.** You restored the committed version and
  `npm install` reconciled it against `package.json`, so its diff is now only the removed
  front-end dependencies (3,914 -> 3,468 lines) with no unrelated churn mixed in.
- **`public/build/` was regenerated** by `npm run build` during verification. Hashed asset
  filenames and `manifest.json` changed. Stage or discard as you prefer.
Expected suite state: **83 passed, 0 failed.**

The two long-standing `ProfilePictureTest` failures (`LogicException: GD extension is not
installed`) were cleared by installing `php8.5-gd`, so the suite is fully green. Those two
tests had never run in this environment before, meaning the profile-image upload paths they
cover were previously unverified — they now pass.

## Post-review fix wave

The final whole-branch review found three Important issues that the eight per-task reviews
structurally could not see — the backend was gated correctly but the Blade layer was never
brought into line. All three are fixed, in these additional files:

- `resources/views/layouts/navigation.blade.php` — the admin guard wrapped only the Users
  link, so all seven Poker Management links 403'd for non-admins
- `resources/views/poker/tournaments/show.blade.php`, `resources/views/poker/seasons/show.blade.php`
  — six unguarded admin links; the season page's only route into a tournament was an edit link
- `resources/views/dashboard.blade.php`, `resources/views/events.blade.php` — register controls
  were offered between the registration cutoff and the start time, where they always failed
- `app/Models/PokerTournament.php` — new `registration_open` accessor
- `tests/Feature/ViewAuthorizationTest.php` — 8 new tests, the first in this phase that
  assert on rendered output

Note: `poker/seasons/show.blade.php:183` also changes the **admin** click target in the
season's tournament list (now the edit page), not only the non-admin one.

## Out-of-plan cleanup: axios removed

Approved separately, after Phase 0. `axios` was declared in `devDependencies` but imported
by `resources/js/bootstrap.ts`, so Vite bundled it into the JS every visitor downloads --
while nothing in the app ever called it. `npm audit --omit=dev` could not see this, because
the declaration is dev-only even though the code ships.

Removed: `resources/js/bootstrap.ts` (deleted), its import in `app.ts`, the `axios`
declaration in `types/global.d.ts` (now correctly declares `window.Alpine` instead, which
was previously undeclared), and the `axios` devDependency.

Effect on the shipped bundle:

| | before | after |
|---|---|---|
| modules transformed | 54 | 3 |
| app.js | 83.04 kB | 46.14 kB |
| app.js gzipped | 30.88 kB | 16.59 kB |
| npm audit total | 11 | 8 |

`resources/js/` is now just `app.ts` and `types/global.d.ts`.
