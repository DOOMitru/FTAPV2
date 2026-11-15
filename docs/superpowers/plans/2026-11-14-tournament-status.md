# Tournament Status Badges Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every row on the Poker Tournaments list and the venue page's Recent Tournaments card carries one badge: Upcoming, Awaiting results or Results published.

**Architecture:** `PokerTournament::status()` is the one definition. Both controllers send `status` per row; one Vue component turns it into a `Badge` with fixed words and variant.

**Tech Stack:** Laravel 13, PHPUnit 13, Inertia + Vue 3 + TS.

**Spec:** `docs/superpowers/specs/2026-11-14-tournament-status-design.md`

## Global Constraints

- Words exactly: "Upcoming", "Awaiting results", "Results published".
- Variants: upcoming → `open`, awaiting → `primary`, published → none (plain).
- Published is checked first; otherwise `start_time <= now()` is awaiting.
- No inline CSS; no new badge colour. No git commands — commit steps are hand-offs.
- Run PHP and node pinned: `taskset -c 0-9,12-31`.

## Review Focus

- A night starting exactly now, or a minute ago, is "Awaiting results" (`<=`, not `<`).
- A published night whose start time is in the future (data edited after publishing) reads "Results published".
- The badge must not sit inside the row's `<a>` on the venue page in a way that nests links (it is a `<span>`; NestedAnchorTest holds the rule).
- Phone layout of the Index card: name and badge on one line, wrapping, no overflow (sweep).
- The venue page is admin-only; nothing here changes who sees it.

---

### Task 1: `PokerTournament::status()`

**Files:** Modify `app/Models/PokerTournament.php`; Test `tests/Feature/TournamentStatusTest.php` (new).

**Produces:** `public function status(): string` → `'upcoming' | 'awaiting' | 'published'`.

- [ ] Tests: `test_a_night_still_to_come_is_upcoming`, `test_a_night_that_has_started_is_awaiting_results` (start = now, frozen with `$this->travelTo`), `test_a_past_night_with_results_out_is_published`, `test_published_wins_over_a_future_date`. Run → FAIL (undefined method).
- [ ] Implement; run → PASS.

### Task 2: Both pages show the badge

**Files:** Modify `PokerTournamentController::index`, `VenueController::show`, `Poker/Tournaments/Index.vue`, `Poker/Venues/Show.vue`; Create `resources/js/Components/TournamentStatus.vue`; CSS for the Index name cell's title+badge line; Test: extend `TournamentStatusTest`.

**Consumes:** `status()` from Task 1.

- [ ] Tests: `test_the_tournaments_list_sends_each_rows_status` and `test_the_venue_page_sends_each_rows_status` — three nights (upcoming, awaiting, published) and assert `status` per row in Inertia props. Run → FAIL (missing key).
- [ ] Add `'status' => $tournament->status()` to both row maps; add `status: 'upcoming' | 'awaiting' | 'published'` to both page interfaces; render `<TournamentStatus :status>` beside the name (Index name cell, venue entry title line).
- [ ] Run the two tests → PASS. Then `bin/suite.sh`, `npm run typecheck`, `bin/sweep.sh` → all clean.
- [ ] Hand-off: commit checkpoint.
