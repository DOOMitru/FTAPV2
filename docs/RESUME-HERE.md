# Resume here

**Last worked: 2026-09-05.** First to Act Poker — a league app for free-to-play
poker nights in Regina.

## Where things stand

Suite: **493 passed.** Run `php artisan test`.

**The design-system work is finished and is no longer what this project is
about.** Phases 0-5 moved all 86 views off Tailwind onto hand-built CSS
(`docs/PHASE-5-EXIT-AUDIT.md`); the red-and-black refresh then moved the app onto
the logo's palette (`docs/RED-BLACK-EXIT-AUDIT.md`). Colour is enforced rather
than asserted: `TokenContrastTest` parses the real token file and fails the suite
on any pair below AA. Everything since has been features and defect work on top
of that foundation.

**The four public rules pages carry the league's real documents.** They were
paraphrases written to fill a layout -- and some of them were wrong: the finale
was described as a top-20 cut when it is three thresholds, and a "Point
Multiplier: Double Weighted Points Awarded" fact stated a scoring rule the league
does not have and the app does not implement. The rules now live as data in
`config/holdem.php`, `config/conduct.php` and `config/regulations.php`, render
through one recursive component, and are numbered by CSS counters so a clause is
cited by where it sits.

**Mail works, and now looks like the league.** SMTP is configured against
Dreamhost, connects, authenticates and delivers; a real password reset was sent,
clicked and completed on 2026-09-05. `php artisan mail:check` reports the
resolved configuration and fails on anything that would silently not work — a
log/array transport, a placeholder from-address, from-NAME or league contact, and
an `APP_URL` pointing at the sending machine.

All four emails the app sends — invitation, approval, password reset, email
verification — carry the home page's light hero: the logo, `FIRST TO ACT POKER
LEAGUE` with the second half in the accent red, and the motto beneath it. Two of
those four are written by the framework, and were arriving in Laravel's voice
("Whoops!", a button reading "Verify Email Address", signed with the app name);
`AppServiceProvider::composeAuthenticationEmails()` rewrites both through
`toMailUsing`. The sign-off is `The First to Act Team`, rendered unconditionally
in `resources/views/vendor/notifications/email.blade.php` so a notification added
later cannot forget it. `EmailPresentationTest` holds masthead, greeting,
signature and single-call-to-action for all four together.

The from-name read **"Example"** on the first production send. Nothing was
misconfigured in this repo: `config/mail.php` shipped Laravel's
`env('MAIL_FROM_NAME', 'Example')` and the server's `.env` had no such line, so
one missing variable branded every message. The fallback is now the league's own
name, and `mail:check` looks at the from-name as well as the from-address — it
had reported "nothing here would silently fail" against that exact mailer.

**The app is deployed and deploying itself.** Push to `main` and GitHub Actions
runs the suite on both database drivers, then ships to DreamHost shared hosting:
rsync, migrate, cache, `mail:check`. See `docs/DEPLOYMENT.md` for the setup and
the section below for what it cost to get there.

The 205 imported players are approved and verified but hold random 32-character
passwords, so each needs an invitation to get in. `users:invite` sends it — not
Laravel's stock reset notification, which opens "we received a password reset
request", a false statement about a request nobody made and the shape of a
phishing message when several hundred people get one at once.

**The mass send is deliberately not done. It waits on the owner, not on code.**
Verified end to end on 2026-09-05: `mail:check` passes on the server, and a real
invitation to the administrator's own address arrived, looked right and worked.
What remains is 204 outstanding accounts and a decision about when a league gets
204 emails at once. Nothing is blocking it technically — do not treat this as an
unfinished feature and do not send it to "finish" the work.

### Open, in rough priority

1. **Seed production through the dashboard** — venues, seasons, sponsors and the
   points structure. Independent of the invites below, and worth doing first:
   the invitation points players at a site, and an empty schedule is a poor
   first impression.
2. **Invite the players — ON THE OWNER'S SAY-SO, deferred 2026-09-05.** 204
   outstanding. The plan, when it is wanted:
   - `users:invite --limit=20 --sleep=5 --force` first, then read the bounces at
     `info@firsttoactpoker.com` before continuing. Twenty is small enough to see
     a problem before it is two hundred people.
   - Then `--limit=80 --sleep=5 --force`, an hour apart, three times.
   - **DreamHost rate-limits outgoing SMTP on shared hosting and the current
     per-hour figure was never established.** If sends start failing partway
     through a batch with connection or "too many messages" errors, that is what
     it is; the answer is smaller batches over more hours, not a retry loop.
   - Failures are safe. The command records `invited_at` only after the send
     returns, catches per-recipient throwables, keeps going, and tables what
     failed — so a dead batch leaves those players outstanding for the next run.
     A duplicate invitation is a nuisance; a missing one is a player who never
     gets in.
   - Check the report says `links valid for 10080 minutes` before a batch. At
     seven days people can act whenever they read it; at 60 the command warns,
     and most of the league would need "Forgot your password?" instead.
   - Someone should watch `info@firsttoactpoker.com` that evening. The
     invitation sets no Reply-To, so replies land there.
3. **One unreproduced test failure**, seen once on 2026-09-05: a full run
   reported `1 failed, 435 passed`, and the name was not captured. It did not
   recur in 45 further full runs or 120 targeted ones against everything in the
   suite that uses randomness or the clock. Recorded rather than dismissed: a
   test that fails once in fifty runs will eventually fail in CI, and the next
   person to see it should know it is not new.
4. `docs/` holds six audit documents from finished phases. Their open-items
   sections are largely resolved; treat this file as the index, not them.
5. `.superpowers/sdd/` can be deleted whenever convenient — see the end of this
   file.

Nothing else is known-broken. There are no TODO, FIXME or HACK markers anywhere
in `app/`, `routes/` or `resources/`.

## The pipeline

`.github/workflows/ci.yml`, one file, two jobs. `deploy` declares `needs: test`,
so a red suite stops a release rather than racing it.

**Tests** run as a matrix over SQLite and MySQL. Production is MySQL, so that leg
is the one that must pass; local development is SQLite, so that leg must keep
working. Running one driver would leave the differences between them untested,
which is not theoretical — see below.

**Deploy** builds `vendor/` and `public/build` on the runner and rsyncs them up.
Shared hosting has no reliable Composer and little memory to resolve
dependencies with. That is why `PHP_VERSION` in the workflow has to match the
DreamHost panel and `DEPLOY_PHP`: those files are compiled against it. All three
are 8.5.

Secrets live in the `production` GitHub Environment: `DEPLOY_SSH_KEY`,
`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`. Adding a required reviewer there
turns this into deploy-on-approval without touching the workflow.

Every action is pinned to a commit SHA — a moving tag is a decision handed to
somebody else, and this job holds a key that writes to production. Dependabot
keeps them current: routine bumps grouped into one PR, majors one at a time.

### What the pipeline caught that months of green tests did not

Worth recording, because each one was invisible from a working tree that had
been running fine for weeks:

- **`@vite` throws without `public/build`.** It is gitignored, so a fresh
  checkout has no manifest and every view test fails on a missing file. CI
  builds before it tests.
- **`public/storage` is a gitignored symlink**, so a fresh checkout has none and
  `StorageLinkTest` fails — on both database legs, for a reason that has nothing
  to do with databases. CI runs `storage:link`.
- **A `GROUP BY` that only MySQL rejects.** `$season->results()` is a
  HasManyThrough, and the relation appends `tournaments.season_id as
  laravel_through_key` to the SELECT. Beside a `GROUP BY` that is fatal under
  `ONLY_FULL_GROUP_BY`, on by default since MySQL 8. **SQLite does not enforce
  that rule**, so the dashboard — the most-visited authenticated page — would
  have thrown a 500 the first time a real player loaded it in production. This
  is the reason the MySQL leg exists.
- **PDO returns MySQL columns as strings** where SQLite returns typed values, so
  `place`, `points`, `amount` and `sort_order` were `"5"` in production and `5`
  in development. Fixed by casting on the models rather than loosening the
  assertions: the model should be the authority on its own types.

### Things about DreamHost shared hosting that cost time

- **rsync creates only the last component of a destination path.** A
  `DEPLOY_PATH` whose parent does not exist fails with `mkdir … No such file or
  directory`. The deploy makes the chain itself now.
- **`DEPLOY_PATH` must be absolute.** A leading `~` does not expand inside the
  quoted commands the deploy runs. The workflow rejects a relative path early
  with a clear message.
- **`storage/` is excluded from the sync** — it holds the logs, the sessions and
  the uploaded sponsor logos, and a release must not overwrite them. The
  consequence is that the framework's writable skeleton never arrives with the
  code, and `view:cache` fails with "View path not found" because it clears
  before it compiles. The deploy creates those directories.
- **Always pass `-o IdentitiesOnly=yes`.** Without it ssh offers every key it can
  find before the one named by `-i`, the server counts each as a failed login,
  and DreamHost blocks the IP. That happened once, from a home connection, and
  took an hour to clear. Every ssh call in the workflow names its key and only
  its key.

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
- **Check a new class name against the stylesheet before using it.** `.p-rule`
  was already the decorative `<hr>` at the foot of four pages -- 4rem wide,
  centred, 2px tall -- so list items wearing it collapsed into a 64px column of
  overlapping text. Every one of the 436 tests passed while the page was
  unreadable; only a screenshot showed it. The rules list is `.p-clause` now.
- **Source order decides between two single-class rules, and this project has
  been bitten three times.** `.rows`, then `.p-benefits`, then
  `.p-clause__text`: in each case a `margin: 0` reset loaded after the spacing
  rule that was supposed to apply and silently won. If spacing does not appear,
  look for a reset later in the cascade before looking anywhere else.
- **`assertSee($text)` escapes its expectation; `assertSee($text, false)` does
  not.** Blade turns an apostrophe into `&#039;`, so a raw search for one finds
  nothing on a page rendering it perfectly. Default to the escaping form when
  asserting content.
- **Chromium here is snap-confined**: it cannot read or write `/tmp`, and enforces a
  500px minimum window width. Use a directory under `$HOME` for screenshot work.

**One exemption from the no-inline-CSS rule, and only one.**
`resources/views/vendor/mail/` is skipped by `InlineStyleGuardTest`, because HTML
email requires the opposite of what the rule enforces: Gmail and Outlook.com
strip `<style>` blocks, so Laravel reads `themes/default.css` at send time and
writes each declaration back out as a `style` attribute. The styling still lives
in one stylesheet — that file rather than `resources/css/`. The exemption list is
itself asserted (`test_the_exemption_covers_only_the_mail_templates`) so widening
it is a deliberate edit rather than a line in a diff nobody reads.

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

### The application timezone is America/Regina (2026-09-05)

- **Every date this app holds is a Regina wall clock somebody typed into a
  form.** A `datetime-local` input posts naive text with no zone, so under the
  old `'timezone' => 'UTC'` a tournament entered as 7pm was stored as 7pm UTC.
  It read back as 7pm on every page -- so nothing looked wrong -- while being a
  real instant six hours before the one intended. `now()` is a genuine moment,
  so every comparison was six hours out: a tournament dropped off "upcoming"
  everywhere at 1pm, and the details page showed Final Standings before anyone
  sat down. `today()` rolled over at 6pm Regina, for the whole of every poker
  evening.
- **Saskatchewan does not observe daylight saving.** America/Regina is CST at a
  fixed -06:00 all year, verified in both halves of the year by
  `LeagueTimezoneTest`. The usual argument for storing UTC is ambiguous and
  skipped local times at the DST changeover, and this zone has neither -- which
  is why a wall clock in the database is safe here and would not be elsewhere.
- **Not read from the environment**, deliberately. An `APP_TIMEZONE` unset on
  one server reintroduces the bug silently, and a league in Regina is not
  reconfigured per environment.
- **`PokerSeason::current()` was immune** because it is an `is_current` flag
  rather than a date range. That decision paid off here.
- **`Carbon::setTestNow()` with a ZONED instance rewrites the default parse
  timezone**, and Eloquent's datetime cast goes through `Carbon::parse()`. A
  test that freezes the clock with `Carbon::parse($t, 'America/Regina')` makes
  the models read their dates back in Regina whatever `config/app.php` says --
  so the harness silently repairs the fault it is testing for. Four of these
  tests passed under UTC until that was found, by reverting the config and
  asking which ones still passed. `LeagueTimezoneTest::reginaTime()` now freezes
  a bare instant in `date_default_timezone_get()`; note that Carbon 3's
  `createFromTimestamp()` defaults to UTC, so the zone has to be passed.

### The registration deadline is gone (2026-09-05)

- **A tournament has one date now: `start_time`.** `scheduled_at` held a
  registration cutoff an hour or so before play, and it decided three separate
  things -- whether a player could enter, whether they could withdraw, and
  whether an admin could start recording finishes. The league does not work that
  way: people turn up, and someone who cannot make it says so on the night. The
  column is **dropped**, not left unused -- a column named `scheduled_at` that
  nothing reads is worse than most orphans, because the name implies it still
  governs something.
- **Entering and withdrawing both hang on results, and nothing else.** That was
  the rule doing the real work all along: a place is a position in a field, so a
  recorded finish describes a field of a particular size, and that is what must
  not change underneath it. A clock never had anything to do with it.
- **Entering is deliberately still allowed after results exist; leaving is not.**
  Not an oversight. Joining a field of ten makes it a field of eleven,
  unambiguously, and `PokerTournamentRegistrant`'s shift hook moves every
  recorded finish down to match. Leaving one leaves the question of whether the
  player played at all, which nothing can answer.
- **Eliminate is gated on being an admin, and nothing else** (the owner's call).
  It required registration closed, to stop a late entry changing how many places
  there are -- but the shift hook already handles exactly that, so the guard was
  protecting against a problem solved elsewhere at the cost of an admin being
  unable to score a game that started early.
- **`is_late_entry` is measured against `start_time`** now that there is no
  deadline to be late for. Its test was always *named* after start_time; the
  arithmetic said otherwise.
- **`.p-event` lost a row, so its `min-height` was refitted from 24rem to
  20rem.** `.p-event__actions` has `margin-block-start: auto`, so a floor taller
  than the content does not centre anything -- it opens a gap above the buttons,
  and at 24rem that gap was most of an inch of nothing. Only a screenshot showed
  it; all 486 tests passed with the hole in the card.
- Two labels outlived the thing they described and had to be hunted down: the
  dashboard row said `Closes 05:11 AM` over the time play *starts*, and two
  confirmations offered to let you "register again while registration is open".

### Added with the admin registrant control

- **A tournament with recorded results can still have registration open.** This
  was assumed impossible -- eliminating needs registration closed, withdrawing
  needs it open -- and the assumption was written into a test comment as "two
  guards happening to agree". They do not agree:
  `PokerTournamentResultController@store` records a result through the admin
  results form with no requirement that registration be shut. So the state is
  reachable, and `p-event.blade.php` was offering players an Unregister button
  the controller then refused. The card now asks `hasRecordedResults()` too.
- **Removing a registrant is one route, not two.** `poker.registrants.destroy`
  already carried the settled-field rule; the tournament page reuses it rather
  than growing an admin variant of `tournaments.unregister`. Two routes doing
  the same thing behind different guards is how the two come to disagree.
- **The remove control is tied to results, not to registration.** The entry most
  likely to be wrong is one an admin added late, by which time registration is
  shut -- tying it to `registration_open`, as the Eliminate button beside it is
  tied, would remove it from exactly the case it exists for.
- **Once any result exists the control leaves every row**, not just the rows of
  players who have finished. A place is a position in a field, so the field is
  settled as a whole.
- **A control that disappears gets a reason.** `Results recorded · entries
  locked` appears on the Registered Players card, to admins only -- a player was
  never offered the control and has nothing to account for.

### Added while the rules pages were rebuilt

- **A rule set is data, not markup.** `config/holdem.php`, `config/conduct.php`
  and `config/regulations.php` hold the clauses; `<x-p-rules>` renders them
  recursively and `.p-rules-doc` styles them. A page's markup is then about
  layout, and a test can walk the same data the page renders.
- **Numbering comes from CSS counters, never from the content.** Clauses are
  cited by number -- "21.8" -- so a number typed into the text is one that
  silently stops matching the moment a clause is inserted above it.
- **Rule sets run the full container with no measure on the text.** Unusual, and
  deliberate: these are short numbered clauses, not prose, and at the container's
  width all but a handful fit on one line. A measure puts back the whitespace
  without shortening a line that mattered. Above 48rem they are inset by
  `--space-6` so they align with the panels around them.
- **Every departure from the source document is commented where it is made** --
  a corrected blind level, four transcription slips -- so nobody later restores
  it to match the paper copy.
- **The finale panel states only what the app enforces or the rules say.** It
  previously carried an invented scoring rule. There is a test refusing any
  rank-cut phrasing, and another refusing to print a season's threshold figures,
  which differ per season and are published on the season page.
- **The season has no fixed number of tournaments.** Confirmed by the owner on
  2026-09-05; the regulations page's old "12 regular tournaments" section was
  removed rather than corrected, because there was no number to put back.
- **Public form actions share `.p-form-actions`** -- full width on a phone,
  label-width and flush right above 40rem. `btn--block` survives on the sign-in
  button alone, where a full-width primary in a 38rem column is right.

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
