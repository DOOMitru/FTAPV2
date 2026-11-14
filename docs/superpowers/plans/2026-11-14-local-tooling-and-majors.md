# Local Tooling and Major Upgrades — Plan

> **For agentic workers:** pick up at the first unticked task. Each task is
> independent; do them in order unless the owner says otherwise.

**Goal:** Clear the local flakiness at its source, then take the deferred major
upgrades one at a time.

**Context:** Written 2026-11-14 at the end of a session that updated every
dependency inside its constraints, pinned `vue-tsc`, and made `bin/sweep.sh`
retry a crashed database step. All of that is recorded in `docs/RESUME-HERE.md`;
read its **PHP 8.5.4 segfaults** section and the `composer audit` section first.

## Global Constraints

- **Never run `git` commands.** The owner runs every git operation. Each task
  ends at a hand-off checkpoint: say what to commit and suggest a message.
- **Run the suite with `bin/suite.sh`**, never a bare full `phpunit` run. A
  `FAIL` with *no summary line (crashed)* is a file to re-run alone first.
- **Find the root cause before fixing** (superpowers:systematic-debugging). The
  sweep investigation found its "race" was a PHP crash only by keeping the logs
  the script normally deletes -- instrument first, guess never.
- **Majors one at a time**, each with suite + `npm run typecheck` + build +
  `bin/sweep.sh` green before the next.

---

### Task 1: The PHP 8.5.4 segfault

Behind most local flakiness: the suite (`zend_mm_heap corrupted`, exit 139),
and `php artisan tinker` in the sweep's seed step (3 of 10 runs before the
retry). Not the project's code -- every file passes alone.

- [ ] Check whether a newer PHP 8.5 release exists for this machine
      (`apt policy php8.5`), and whether its changelog mentions heap corruption.
- [ ] If one exists: install it, then measure -- a full bare `./vendor/bin/phpunit`
      three times, and `bin/sweep.sh` ten times. Before the fix, a bare run
      crashed most times and the sweep's seed step about one in three.
- [ ] If the crash is gone: say so in `docs/RESUME-HERE.md`'s segfault section
      with the version and the measurements, and ask the owner whether to keep
      or retire `bin/suite.sh`'s per-file workaround and the sweep's retry
      (cheap to keep; they also guard CI if it ever runs 8.5.4).
- [ ] If not, or no newer release: record that and stop. Do not dig into PHP
      internals without the owner asking.
- [ ] Hand-off checkpoint.

> **Task 1 result, 2026-11-14:** PHP already 8.5.12; bare suite 3/3 clean;
> sweep 8/10 (one Node SIGSEGV in `vite build`, one PHP SIGSEGV in
> `artisan serve`). Crashes cluster on CPU 10 of an i9-14900K -- likely
> hardware. Recorded in RESUME-HERE; awaiting the owner. Task 2's question
> is answered (it is a signal death of `artisan serve`).

### Task 2: The sweep's "Server never came up" (only if Task 1 did not clear it)

Once in twenty runs on 2026-11-13, with an empty `serve.log`. Suspected to be
the same crash taking down `artisan serve` at start. Not shown.

- [ ] Make the message explain itself in `bin/sweep.sh`: whether `$SERVE_PID`
      is still alive, its exit status if not (`wait`), and the last HTTP code
      the readiness loop saw.
- [ ] Run the sweep until it recurs (roughly 20+ runs, in the background).
- [ ] Fix only what the evidence shows. If it is a signal death, retry the
      server start the way the database step retries.
- [ ] Hand-off checkpoint.

Latent and separate: `listener()`'s greedy pattern returns only the last `pid=`
on an `ss` line. Harmless today (one process holds the port); fix it only if a
run ever shows several.

### Task 3: PHPUnit 13

The most code-facing major. Last major (11→12) broke exactly one thing: a data
provider passing more arguments than its test accepted.

- [ ] Read the PHPUnit 13 release notes for removals and new strictness.
- [ ] Check what `laravel/laravel` v13's own `require-dev` asks for -- the last
      upgrade guessed versions and was wrong twice.
- [ ] Bump the constraint, `composer update phpunit/phpunit -W`, run the suite,
      fix what breaks, update the suite figure in `RESUME-HERE.md`.
- [ ] Hand-off checkpoint.

### Task 4: Front-end majors, one per checkpoint

Each: read its migration guide, bump, `npm install`, then typecheck, build,
suite and sweep.

- [ ] Vite 8 together with `laravel-vite-plugin` 3 (they move as a pair).
- [ ] TypeScript 7 -- check `vue-tsc` supports it first.
- [ ] `postcss-import` 17.
- [ ] `concurrently` 10.
- [ ] `@types/node` 26 only if Node itself is on 26.

### Task 5: Lift the `vue-tsc` pin (whenever a release newer than 3.3.14 is out)

- [ ] `npm install -D vue-tsc@latest`, `npm run typecheck`.
- [ ] If `Sponsors/Partials/SponsorForm.vue` is clean, restore a caret range
      and delete the pin note in `RESUME-HERE.md`. If not, put `3.3.11` back.
