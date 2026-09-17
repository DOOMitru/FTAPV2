# Resume here

**Last worked: 2026-09-17.** First to Act Poker — a league app for free-to-play
poker nights in Regina.

## Where things stand

Suite: **918 passed across 101 files.** `php artisan test` is the command, but
see the segfault note below -- a full-suite run dies on this machine and has to
be taken file by file, which is also how that 918 was counted.

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

**The mass send is DONE, 2026-09-09.** All 206 outstanding accounts were
invited, in four hourly batches, with no failures and no bounces. The league is
live and players are setting their own passwords. `users:invite` now reports
nobody to invite; `--again` re-sends to somebody who has lost their link, one
person at a time, and there is no reason to run the mass send ever again.

**The week since has been the app settling into use, and most of it was
subtraction.** With 206 people actually in it the work stopped being "build the
screen" and became "the league found this by using it": a privacy rule promoted
to a project-wide invariant, a page for reading another player's figures, a bot
gate on the one form the public can reach, an account actually deleted when it
is rejected, and **four admin screens removed** because the record they showed
reads better where it lives. Each removal cost far more than the screen -- nav
entries, empty states, error copy, dead JS modules, dead CSS blocks and nine to
twelve test files apiece. The detail is under **Decisions taken since the
conversion** below; what follows immediately is only what is still open.

### Open, in rough priority — ALL CLOSED as of 2026-09-17

1. ~~Seed production through the dashboard~~ **DONE, 2026-09-08.** Venues,
   seasons, sponsors and the points structure were entered by hand by the owner.
2. ~~Invite the players~~ **DONE, 2026-09-09.** 206 accounts, four batches an
   hour apart (20, then 65 x 3), `--sleep=5`, every one delivered.

   **The DreamHost figure this document said was never established: 200
   recipients per server per hour on shared hosting, and 40 recipients per
   message.** The per-message cap never applied -- `PlayerInvitation` sets one
   `To` and no cc or bcc, so every invitation is its own message. The hourly cap
   is the real one, and 206 does not fit in 200, so the send had to span hours
   whatever the pacing. Batches were sized to peak near 130 in any rolling hour,
   leaving room for password resets and the contact form, which spend the same
   allowance.

   The second-order effect is the part worth remembering, because it is invisible
   until it bites: **an expiring invitation link generates a second email from the
   same quota.** At the stock 60-minute expiry most of 206 people would have read
   the mail too late, clicked "Forgot your password?", and turned a 206-email
   send into roughly 412 -- the second half arriving in a pattern nobody controls
   or paces. `AUTH_PASSWORD_RESET_EXPIRE=10080` on the server is what stopped
   that, and is why `users:invite` warns when the expiry is <= 60.

   ~~Loose end: that seven-day window is still set.~~ **CLOSED, 2026-09-17.**
   It was widened for the invite period, not on the merits -- a week-long
   password-reset link is a weaker default than Laravel's hour. The last batch
   went out 2026-09-09, every invitation link expired on 2026-09-16, and the
   variable was removed from the server `.env` with `php artisan config:clear`
   the next day. The expiry is back to Laravel's 60 minutes; anyone still
   locked out is a `--again` or a normal reset.

   **`users:invite` warns at an expiry of 60 minutes or less, so that warning
   is live again -- by design.** It is gated on more than five recipients, so
   the one-at-a-time `--again` path stays quiet and only a mass send trips it,
   which is exactly when widening the window is the right advice. Do not
   silence it by raising the default; raise the variable for the send and drop
   it again after, which is the round trip this item records.
3. ~~One unreproduced test failure~~ **FOUND AND FIXED, 2026-09-08.** It
   surfaced again during the tournament-filter work and this time the name was
   captured: `DeleteConfirmationTest::deleting an actual person still says so`.
   The users listing orders by first name, the helper read only the FIRST
   `data-confirm` form on the page, and the test seeds its admin from the
   factory -- so whenever that random first name sorted before "Ada" (Aaron,
   Abbie, Abigail), the first confirmation belonged to the admin and the
   assertion failed. Confirmed by forcing an admin named Aaron. The helper now
   returns every confirmation and the test asserts one of them matches; five
   full runs clean since.
4. ~~Merge `feature/user-notifications` and watch the MySQL leg of CI~~ **DONE,
   2026-09-08.** Merged, and the MySQL leg did fail -- though not where this
   document predicted. The `where('data->tournament_id', ...)` JSON query in
   `unpublish()`, flagged here as the risk, passed. What broke was a test:
   `TournamentPlacementNotificationTest` asserted `Schema::getColumnType()`
   returned `'varchar'`, which is SQLite's word for the `CHAR(26)` that
   `ulidMorphs` creates. MySQL says `'char'`. The assertion now accepts either
   and still fails on `integer`/`bigint`, which is the regression it exists to
   catch. The lesson is narrow and worth keeping: **a test written to guard
   against SQLite/MySQL divergence can itself be driver-specific**, and asserting
   a type NAME is how that happens.

5. ~~The monogram survey's Tier 2 and 3~~ **DONE, 2026-09-13.** Every list of
   players now pictures them; `MonogramCoverageTest` names the ten and fails if
   one loses its monogram. Two recorded beliefs turned out to be wrong. The
   blocker -- "several need their controllers reshaped to carry a model rather
   than a name string" -- was weaker than it read: `<x-monogram>` takes a name
   STRING, and a decorative monogram needs no accessible label because the name
   is beside it. And the privacy question was aimed at the wrong page: the
   public Current Season Leaders panel had since been wrapped in `@auth`, so it
   was never public. The four `<select>` forms are the only sites left, and an
   `<option>` cannot hold markup, so they are not deferred -- they are out of
   reach. Recorded in the test rather than here.
6. `docs/` holds six audit documents from finished phases. Their open-items
   sections are largely resolved; treat this file as the index, not them.

**Nothing on this list is open.** It is kept for the reasons it records, not as
a queue. The next entry goes under a new heading rather than reopening one of
these.
Nothing else is known-broken. There are no TODO, FIXME or HACK markers anywhere
in `app/`, `routes/` or `resources/` -- re-checked 2026-09-17. Both deploy gates
are wired in CI: `mail:check` and `recaptcha:check` run before the release is
switched in.

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

## PHP 8.5.4 segfaults on a full-suite run (2026-09-08, unresolved)

`./vendor/bin/phpunit` with no arguments dies with **exit 139, SIGSEGV**, most
runs, at a different point each time -- sometimes 24 tests in, sometimes 567 of
671. It is not the project's code:

- It reproduces with recent changes reverted.
- Every test file passes when run alone, including the one a crash lands in.
- `MonogramTest` crashed inside a ten-file chunk and then passed four times in a
  row on its own.
- Not memory (55GB free), not disk, not opcache (off for CLI), not JIT
  (disabled), and **not PCRE JIT** -- `-d pcre.jit=0` still segfaults.

**Work around it by running in chunks**, which covers all 671:

```bash
for c in 1 2 3 4 5 6 7; do
  s=$(( (c-1)*10 + 1 ))
  ./vendor/bin/phpunit $(ls tests/Feature/*.php | sed -n "$s,$((s+9))p")
done
./vendor/bin/phpunit $(find tests/Feature -mindepth 2 -name '*.php')
./vendor/bin/phpunit tests/Unit
```

That last line matters: `ls tests/Feature/*.php` misses `tests/Feature/Auth/`,
which is 21 tests. The three parts must add up to the whole suite -- if they do
not, a directory is being skipped rather than passing.

CI runs on its own PHP build and has never shown this, so it is a local
toolchain problem rather than something to fix in the app. Worth a look at the
PHP 8.5 build on this machine before it eats another afternoon.

## The screenshot harness cannot show you a phone

**`chromium --headless --window-size=375,…` does NOT give a 375px viewport.**
This machine's Chromium clamps the layout viewport to a **500px minimum**, in
both `--headless` and `--headless=new`, and `--force-device-scale-factor` does
not move it. The screenshot comes out 375px wide, so it looks like a phone and
is not one -- it is a 500px layout in a narrower frame.

Every mobile check made before 2026-09-08 was therefore taken at 500px. The
seasons card layout looked correct that way and broke badly at a real 375: the
Current badge takes 103px and three row actions take 128, which left the season
name **51px** and wrapped it across two lines with the badge stranded beside it.

**Use an iframe.** A media query keys off the iframe's own width, so a 375px
iframe inside a 520px window is a true 375px viewport:

```html
<iframe src="page.html" width="375" height="1400"></iframe>
```

`~/ftap-shots/frame.html` takes `?page=…&w=375`. Measure inside it by appending
a script that writes `getBoundingClientRect()` widths into a fixed-position
`<pre>` -- reading them back from `--dump-dom` gives the 500px numbers, because
the dump uses the clamped viewport too.

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

## Deferred minor findings from Phase 0 — CLEARED, 2026-09-13

All fourteen are closed. Four needed no change, two had already been overtaken
by later work, and eight were fixed. Recorded in full because "we looked and
there was nothing to do" is a result, and the next person should not have to
re-derive it.

**Fixed (8):**

- Task 1: the route file named eighteen classes by FQCN inline; all are `use`
  imports now, models and controllers alike.
- Task 3: `EnsureUserIsAdmin` says in a docblock that it depends on `auth`
  running first -- on its own it refuses a guest with 403 where they should get
  302 to the login screen -- and `AdminAccessTest` now proves the pairing
  instead of only describing it.
- Task 3: the guest-redirect test covered ONE route, and the route it named --
  `poker.seasons.index` -- is no longer admin-gated: the three league indexes
  moved out to the signed-in group. It proved that `auth` redirects, which was
  never in question, and nothing about `admin`. It is now provider-driven over
  every admin route.
- Task 3: the provider reached GET routes only. A second provider covers seven
  admin WRITE routes for both a player (403) and a guest (302) -- a gate that
  holds at the form and not at the endpoint is exactly what this suite is for.
- Task 5: the contact form's acknowledgement was written twice, once for a real
  submission and once for the honeypot. The honeypot's whole trick is that the
  two are indistinguishable, so they are one constant now.
- Task 5: thirty-seven `assertSessionHas('status')` calls checked that the key
  existed and nothing else. They now assert the message is not empty. Pinning
  the exact copy in thirty-seven places was considered and rejected: it trades
  a weak assertion for a brittle one, and every future wording change would
  break a dozen tests. Verified by flashing an empty status, which now fails
  four tests that used to pass.
- Final fix wave: five redundant `Carbon::parse()` calls removed -- the
  attributes were already cast to Carbon, so the parse was re-reading its own
  output. The five that remain are on genuinely uncast values (`event_date` is
  deliberately a plain string, `played_on` comes out of notification JSON).
  One of the five was also a second definition of "has this tournament
  started?", and now calls `PokerTournament::hasStarted()`.
- Final fix wave: the one PHP file using `\Illuminate\Support\Carbon` inline
  imports it. Blade files keep the FQCN, which is how a template names a class.

**Already overtaken (2):**

- Task 1: "both tests assert only HTTP 200" -- `PointsStructurePageTest` has
  since gained view-data assertions and four more tests about who appears on
  the leaders panel.
- Task 4: `test_dashboard_excludes_a_tournament_that_has_already_started` was
  renamed and rewritten as `UpcomingEventsCardTest`, which covers the same rule
  and nine more.

**Nothing to change (4):** the read-only `git show` breach in Task 2 (already
ruled at the time), the two Tasks 6+7 notes about `npm run build` rewriting
`manifest.json` and `package-lock.json` carrying unrelated changes, and the
Task 8 note that a consequential expansion landed before controller review.
These are records of what happened during Phase 0, not defects in the code.

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

### A player's venue points are private (2026-09-13)

**Stated by the owner as a hard rule for the whole project:** a player's venue
points may be seen only by that player and by admins. The one exception is the
finale qualification THRESHOLD (`finale_venue_points_required`) -- a published
target, not anybody's tally -- which is shown to everyone, guests included.

- **One definition: `VenuePoints::readableBy(?User $viewer, ?string $ownerId)`.**
  Call it rather than re-deriving the check. Every site that shows a figure
  asks the same question, so the rule cannot drift apart across pages.
- **Withhold the figure from the VIEW DATA, not just the template.** A gate in
  Blade stops today's column and not tomorrow's partial, and the value is still
  sitting in the payload for anyone who looks.
- **`VenuePointsPrivacyTest` walks every registered GET route** as a third
  party and as a guest, so a page added later is covered without being named.
  It carries positive controls too: a bug that hides venue points from
  *everybody*, owner included, cannot pass as compliance.

### The season standings read two ways (2026-09-13)

- **A Points/Rank toggle**, because the two answer different questions: who has
  scored most this season, and who is playing best per night. Rank is the
  dashboard's own calculation, so a player sees the same figure in both places
  or the app is arguing with itself.
- **The rank number is the medal badge** -- `#1` inside it -- and the
  points-per-event figure it replaced is still reachable. It was hover-only
  first, which on a phone is no affordance at all.
- **The signed-in player's own row is washed.** The shade was set by
  measurement, not by eye: the first attempt was 1.13:1 against the ground
  where the house hover shade is 1.18:1, i.e. fainter than an accident. A test
  computes the contrast from the real tokens and fails below the floor.
- **The venue-points column is admin-only**, per the rule above.

### One player can read another's figures (2026-09-15)

- **`/players/{player}`, inside the auth group and nowhere near `/poker`.**
  This is not administration, it is the league looking at itself, so it is open
  to anyone signed in and to nobody else.
- **It is the dashboard without the upcoming tournaments**, which are a
  to-do list and belong to the person whose list it is.
- **Venue points appear only under `readableBy`** -- the owner or an admin.
- **Player names link to it throughout the app**, through `<x-player-link>`, so
  the linking rule has one home. Two existing row-extractor regexes in tests
  used `[^<]+` and silently returned empty lists the moment names were wrapped
  in anchors: a test that finds nothing to check still passes.

### Rejecting a pending account deletes it (2026-09-15)

- **Scoped to PENDING, and only pending.** A self-registration nobody let in
  has nothing worth keeping. The other reject control acts on somebody already
  approved, who has played and been scored, and deleting them would orphan
  every result they earned -- that path still demotes rather than deletes.
- **The confirmation says the account will be deleted, and names the person**
  through `emph()`. Note the trap this exposed: `emph_html()` on an unmarked
  string returns plain text, so a test asserting the sentence passes whether or
  not the name was marked. Assert the whole sentence WITH its markers.

### Registration is gated by reCAPTCHA v3 (2026-09-16)

- **v3, not v2**: no checkbox, a 0.0-1.0 score, and the site decides the line.
  `RECAPTCHA_THRESHOLD` defaults to 0.5 and is configuration because it is a
  judgement about this league's traffic, not a constant.
- **The rule verifies `success`, the `action` and the score**, in that order.
  Checking the action matters: without it a token minted on any other page of
  the site passes here.
- **`Recaptcha::configured()` is one definition asked by two places** -- the
  controller, which only adds the rule when there is a secret, and the form,
  which only fetches a token when there is a site key. Half-configured would be
  a form nobody can submit, or a check nothing can fail.
- **With no keys the form asks Google nothing and says nothing about it.** That
  is deliberate, so local work and the suite do not depend on Google -- and it
  means production is protected exactly as far as its env file says. Which is
  the reason for the next point.
- **`php artisan recaptcha:check` gates the deploy alongside `mail:check`.** It
  fails on no keys, on half a configuration, on a threshold outside 0-1, and on
  a STALE CONFIG CACHE -- keys in `.env` that the running app cannot see. It
  reads `.env` from disk for that comparison, because a cached config means
  Laravel never loads the file. `--probe` posts a deliberately invalid token to
  tell `invalid-input-secret` (the secret is wrong) from
  `invalid-input-response` (the secret is right, the token was junk), which is
  the only way to confirm a secret without a real visitor.
- **A connection failure to Google fails CLOSED.** An outage should not turn
  the gate off.

### Four admin screens removed, and the indexes became lists (2026-09-16/17)

The pattern, learned four times: **removing a screen is never just the screen.**
Each one cascaded into nav entries, empty states, error copy, dead JS modules,
dead CSS blocks and nine to twelve test files. The rule each screen enforced had
to be re-proved through whatever path survived -- the publish-lock through the
remaining routes, the filter behaviour on the page that kept it -- because
deleting the mechanism must not delete the guarantee.

- **`poker/results` and its create page are gone.** A tournament's results are
  read and entered on the tournament.
- **`poker/registrants` and its create page are gone**, for the same reason.
  `registrants.destroy` survives, because removing somebody is still an action
  taken from the tournament page.
- **`poker/venue-points` is gone, and so are its edit and delete.** Points are
  read on the venue they were earned at. `create`/`store` survive and are
  reached from the venue page with the venue already chosen.
- **The seasons, venues and tournaments listings link the whole ROW**, drop the
  view-details button, and moved edit and delete to the detail pages -- where
  the takings, the leaderboard and the field are in front of whoever is about
  to change something. A row of three icons beside every name is a column of
  decisions on a page whose job is to list.
- **`poker/tournaments` shows the current season only**, ordered by
  `start_time` (not `latest()`, which is `created_at` -- the order the rows
  were typed in), 100 to a page. The season is the heading's eyebrow, and the
  Season column went with the scoping that made it constant. With no current
  season the query is `whereRaw('1 = 0')`: an unguarded filter there becomes no
  filter at all, and the page says the season is unset rather than showing
  everything.

### Players can read the league's records (2026-09-08)

- **The seasons, venues and tournaments INDEXES are open to anyone signed in.**
  They sat behind the admin gate because the only reason to open them was to
  edit something; a player has a reason to read them. Declared in their own
  `prefix('poker')->name('poker.')` block so every existing link resolves
  unchanged, while the resources keep `->except(['index'])`.
- **Everything that changes a record stayed shut, and so did the venue DETAIL
  page** -- it carries takings, leaderboards and per-player point histories.
- **The venue list therefore drops its whole Actions column for a player**, not
  just its contents. With View Stats gone (it would link to a 403) an empty
  column is a heading over nothing, so the `<th>`, the `<td>` and the empty
  state's colspan were all made conditional. *Superseded 2026-09-17: the column
  is gone for everyone and the row itself is the link -- but only for an admin,
  for the same reason. A player's row is plain text, because a row a player can
  follow is a row that leads to a 403.*
- **The League menu opened with them.** Access nobody can navigate to is half a
  feature. Play and Setup stay admin-only.
- `AdminAccessTest` swapped those three indexes for the three CREATE pages, so
  it still proves the `/poker` prefix refuses a player.

### Filtering the admin lists by tournament (2026-09-08) — GONE, 2026-09-16/17

All three pages it was built for were removed (see **Four admin screens
removed, and the indexes became lists** above), and `PokerTournament::nearest()`, `x-tournament-filter` and
`dependent-select.ts` went with them. Three things it established outlived the
mechanism and still bind:

- **Venue points have no tournament.** The table records a player, a venue, a
  date and an amount, and there is no column linking one to a game. Any page
  that wants to relate the two has to INFER it -- points at that tournament's
  venue on its date -- which is wrong two ways: points awarded on a night with
  no game appear under none of them, and a venue running two events in a day
  shows both under either. Anything inferring it again must **state what it
  matched on**, because an inferred filter that looks like a real one turns
  "nothing matched" into "nothing was awarded".
- **`event_date` is a plain `Y-m-d` string, deliberately uncast.** A tournament
  has to come down to a date to meet it; compared against the raw 7pm
  `start_time` it matches nothing, for every tournament, silently.
- **`x-dropdown` closes its panel on any click inside it.** Right for a menu of
  links, fatal for any input put inside one -- the filter's search box shut the
  moment you clicked it. The fix was `x-on:click.stop` on the input, containing
  the exception rather than changing the shared component the nav and row-action
  menus rely on. Worth knowing before putting a field in a dropdown again.

### Admin lists become cards on a phone (2026-09-07/08)

- **`x-table` takes a `cards` prop**; `.table--cards` in `_table.css` owns the
  shell -- grid row, clipped header, cell reset, actions, empty state -- and each
  page declares only its own `grid-template-areas`, because what leads a card
  differs: a result by rank badge, a sponsor by logo.
- **`.table--stacked` was the obvious answer and the wrong one.** It prints
  every cell as a labelled line, so a five-column list becomes five lines per
  record and the identifier you came to find sits between two labels.
- **One fact per line, with only a short value sharing the first.** At a true
  375px the actions take about 128px of a 317px card, and pairing facts
  overflowed the grid every time -- a long venue name once pushed the actions on
  top of the season text.
- **`grid-template-areas` must name exactly as many cells as there are columns**
  or CSS drops the declaration in silence. Three of five did not, and those rows
  fell back to an unstyled grid with nothing reported.
- **A spanning cell inflates every auto column it crosses.** The actions column
  measured 128 around buttons measuring 104, and a badge's cell 88 around a
  badge of 64; those phantom pixels came off the name.
- **The season card is NOT a grid, after five attempts at one.** Its two lines
  share column tracks, so they fight. It is two lines of flowing text with the
  actions taken out of flow -- `display: block`, inline cells, one forced break
  via `::before { display: block }`, and `position: absolute` for the actions
  with padding reserving their space.

### Link underlines are opt-in (2026-09-08)

- **The reset takes the browser's underline off every anchor; `.link` puts it
  back.** Most anchors here are not prose links -- rows, cards, menu items, icon
  buttons, pagination, a logo -- and each had to remember to switch it off. Two
  shipped having forgotten, weeks apart, both found by eye with a green suite:
  the venue page drew ten linked rows as thirty underlined fragments, and the
  tournament picker underlined each option's name AND its date.
- Audited before flipping: of every anchor class in the app, only
  `.sponsor-thumb-link` relied on the default, and it wraps an image.
- **`.entry--link` marks a row that is a whole link** -- no underline, a hover
  that colours the title, and a chevron that is ALWAYS drawn, because a hover
  state says nothing on a touch screen. A guard scans views for an `<a
  class="entry">` that is not marked.

### Player notifications (2026-09-06)

Spec: `docs/superpowers/specs/2026-09-06-notifications-design.md`.
Plan: `docs/superpowers/plans/2026-09-06-player-notifications.md`.

- **Publishing is an explicit admin act, because "all the results are in" is not
  a stable state.** Registering a late player shifts every recorded finish, and
  results can be edited through the admin CRUD -- so firing automatically would
  send true statements that quietly became false. An administrator presses
  Publish results; that notifies and **locks** in one transaction, and the lock
  is what makes the message permanently true.
- **Recipients are results with `points > 0`**, so the cut is the points
  structure rather than the podium. Fanfare is tiered: 1st/2nd/3rd reuse the
  podium's medal tokens and its fixed ink, anything else scoring takes the
  accent. A tournament nobody scored in still publishes -- locking is the other
  half of the job.
- **Unpublishing deletes the placement notifications it sent**, scoped by type
  so a player's unrelated ones survive. Reopening means the league no longer
  stands behind those results.
- **Seven call sites across six paths ask one method**, `publishedRefusal()`.
  THREE of them were already refused by older rules -- unregister and
  registrant-removal by `hasRecordedResults()`, and eliminate because a
  published tournament has a finish for everyone so the place on offer computes
  to zero. What publishing adds there is the reason, so those three assert the
  message rather than the refusal. Do not delete them as dead code.
- **Laravel's stock notifications migration is wrong for this app.** It declares
  `morphs('notifiable')`, an unsigned bigint, against ULID users. SQLite stores
  a ULID in that column without complaint, so every send test passes locally
  against a schema that fails on MySQL -- the column type is asserted directly
  (`varchar` against `integer`), which discriminates on both drivers.
- **`assertViewHas` cannot see composer-bound data on an `@include`d view**, and
  it compares LOOSELY -- so `assertViewHas('unreadNotificationCount', 0)` read
  null, `assertEquals(0, null)` is true in PHP, and the assertion passed at any
  count. Those tests assert rendered HTML now.
- **The same CSS collision bit three times in one component.** `.dropdown` sets
  `position: relative` and `display: inline-block` as single classes from
  `_dropdown.css`, which imports AFTER `_topbar.css`. Single-class rules in the
  topbar for `position: static`, `width` and `display: none` all lost silently.
  Every one is now two classes (`.dropdown.topbar__bell-menu`). The suite was
  green through all three; only screenshots at 390px and 1280px found them --
  including a desktop rendering two bells and two badges.

### Profile pictures are gone; a player is a monogram (2026-09-06)

- **The whole feature is removed**, one day after its fallback was fixed --
  which is the right order: fixing it is what made visible that the picture half
  had no users. Nobody ever uploaded a photo. The 205 accounts came from a CSV
  import that sets none, so what was being maintained was a column, an accessor,
  an upload control on two forms and file handling in two controllers, all for a
  state the app had never been in.
- **Dropped:** `users.profile_image` (migration), `User::profile_image_url`, the
  `profile_image` rule in `ProfileUpdateRequest` and `UserController@update`,
  both upload blocks and the `enctype="multipart/form-data"` that existed only
  for them, and `.field__file` (its only callers were those two blocks -- the
  sponsor logo input goes through `x-field`, which merges `.field__control`).
- **`<x-avatar>` is now `<x-monogram>`, and `.avatar*` is `.monogram*`.** A
  component named for a picture it cannot hold lies about itself, and this
  project renames things when they stop describing what they do -- the same call
  as `closesIn` becoming `startsIn` when the deadline went.
- **The podium's photo rules went with it.** `.podium__place--N img.podium__seat`
  moved the medal to a ring for a photographed seat; with no photographs the
  seat is the gold/silver/bronze disc it always was.
- **The `Photo` column heading on both `users/index` tables is now a
  visually-hidden `Initials`.** The cell repeats the Name column beside it, so
  the heading is for a screen reader rather than the eye.
- **`storage:link` is still required** -- sponsors keep their logos on the public
  disk. `storage/app/public/profile-images` never existed in production and the
  migration deliberately does not delete files: a migration that reaches into
  the filesystem cannot be rolled back.
- `MonogramTest` guards the removal three ways: the column is gone and no file
  quotes or accesses `profile_image`, neither profile form still offers an
  upload or an enctype, and the component never emits an `<img>`.

### Avatars fall back to initials, not to a stock face (2026-09-06)

- **`<x-avatar>` draws a monogram when there is no photo.** The fallback was
  `images/default_profile.png`: one generic 1024x1024 face, **1.9MB**, drawn at
  24px, identical for everybody -- and that was not a rare state but the only
  state, because nobody has uploaded a photo and the 205 accounts came from a
  CSV import that sets none. An identical face beside every name is worse than
  no face, because it occupies the space where a difference belongs.
- **Both initials, everywhere** (the owner asked for this explicitly): first
  letter of the first word and of the last, so "Jean-Luc Picard" is JP and a
  player nicknamed "Ace" is still WR. Taken from `first_name.' '.last_name`, NOT
  from `display_name` -- that accessor prefers a nickname and otherwise falls
  back to the first name alone, so initials from it would drop every surname.
  `mb_*` throughout; ÉZ and ДК both render.
- **`user` and `name` are both optional.** `user_id` is nullable with
  `nullOnDelete` on results, registrants and venue points, so a deleted player
  leaves a `player_name` string and no account. Handling that once in the
  component is why the call sites do not each have to.
- **On the podium, `.podium__seat` is the MEDAL, not a circle** -- gold, silver
  and bronze grounds with contrast ratios recorded in `_rows.css`. A photo would
  sit on top of that background and the medal would simply vanish, so
  `.podium__place--N img.podium__seat` moves the medal to a 3px ring. Selected on
  `img` so the monogram state is untouched: with no photo the disc IS the medal.
  Specificity 0,2,1 against the 0,2,0 that sets `border-color: transparent`,
  chosen deliberately rather than betting on file order -- the avatar carries
  `.avatar` from a different stylesheet, and this project has had three defects
  from two single-class selectors colliding.
- **The Registered Players row dropped `.podium__seat`** and just uses `.avatar`.
  It was borrowing a podium class for "a 2.5rem circle" in a list that is not a
  podium, which is how a podium rule ends up reaching a row that has no medal.
- **Tier 1 only.** The Awaiting-approval table on `users/index` now has the photo
  column the approved-users table below it has always had. The remaining ten
  sites from the survey (leaderboards, pickers, public Current Season Leaders)
  were finished on 2026-09-13 -- see item 5 at the top of this file. Both of the
  reasons recorded here for deferring them turned out not to hold.
- **The stock face is deleted**, not merely unreferenced -- 1.9MB that rsync
  shipped on every deploy for an image no page asked for. `profile_image_url`
  answers **null** when there is no photo, which is what makes it a question
  worth asking: while it always returned a URL it could not distinguish "has a
  photo" from "has none", and `<x-avatar>` had to consult `profile_image`
  itself. Two guards keep the pair honest, because a reference surviving the
  file is worse than either alone -- every avatar would be a broken image.
  `AvatarTest` checks the file is gone AND that nothing quoted still names it
  (prose in `User` and `x-avatar` explaining the history is deliberately left
  alone), and a second test resolves every `asset('images|build|fonts/...')`
  path in every view against `public/`. A missing asset otherwise fails
  silently: no exception, no failing test, nothing in a log.

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

## Closed: the sdd ledger

**`.superpowers/` is gone, 2026-09-13.** It held a 539-line decision ledger and
17 agent reports. Everything with forward value had already been copied into
this file, which is why this file is long. Nothing references the directory.
