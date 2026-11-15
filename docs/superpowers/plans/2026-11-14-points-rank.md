# Season Rank by Points Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rank players by season points (venue points break ties, then a shared place) on every page that shows a rank, and remove the points-per-event figure and the Points/Rank toggle.

**Architecture:** `PokerSeason::rankings()` stays the single definition and starts returning a `place` per row. The dashboard and profile panel (`SeasonFigures`), the season page (`PokerSeasonController@show`) and the home page (`routes/web.php`) all read places from it instead of ordering on their own.

**Tech Stack:** Laravel 13, PHPUnit 13, Inertia + Vue 3 + TypeScript (app pages), Blade (home page).

**Spec:** `docs/superpowers/specs/2026-11-14-points-rank-design.md`

## Global Constraints

- **Never run `git`.** Every "commit" step is a hand-off: list the files and suggest a message for the owner.
- Pin heavy runs off the faulty core: `taskset -c 0-9,12-31 bin/suite.sh …`, `taskset -c 0-9,12-31 bin/sweep.sh`.
- Ranked = **at least 1 point** this season, **and** entered a tournament this season, **and** has an account (`user_id` not null). Entry and account are today's rules, kept.
- Order: points desc → season venue points desc (`venue_points.season_id`, never date ranges) → recorded `player_name` asc → `user_id` asc (determinism only).
- Shared place only when points **and** venue points are equal; competition numbering 1, 2, 2, 4.
- **The venue-points figure must not enter any payload.** `rankings()` returns no venue figure; only the order and `place` reflect it (the owner's stated exception).
- Copy, exactly: unranked main `No points yet`; sub (self) `Score points to be ranked.`; sub (other) `A player is ranked once they score points.`
- No inline CSS; no class without a stylesheet use (`UnusedCssClassTest`) — delete CSS whose last user goes.
- Grouped queries go against the tables, not the `results()` HasManyThrough (MySQL `ONLY_FULL_GROUP_BY`; see the docblock on `rankings()`).

## Review Focus

1. **A tie straddling the home card's top 3** (places 1, 2, 3, 3): show every player with `place <= 3`, not an arbitrary three — test in Task 4.
2. **Venue points from another season** must not break a tie — test in Task 1.
3. **A stale `?order=rank` link** (bookmarks, shared links) loads the page, ordered by the new rule — test in Task 3.
4. **A player on points with no registration** stays unranked and unlisted, as today — test in Task 1.
5. **The viewer's own row** is still marked on the season page after the reorder — `SeasonStandingsSelfRowTest` must stay green untouched in Task 3.

---

### Task 1: `rankings()` ranks by points

**Files:**
- Modify: `app/Models/PokerSeason.php` (`rankings()`, `figuresFor()`, `rank()` and their docblocks)
- Test: create `tests/Feature/SeasonRankingsTest.php`; update `tests/Feature/SeasonPanelTest.php` (the five rank tests at lines ~190–304)

**Interfaces:**
- Produces: `PokerSeason::rankings(): Collection<int, array{user_id: string, name: string, points: int, events: int, place: int}>` — already in rank order, 0-point players absent, no `ratio`, no venue figure.
- Produces: `PokerSeason::figuresFor()` keeps its keys minus `perEvent`; `rank` is the row's `place` (null when unranked); `ranked` is `rankings()->count()`.

- [ ] **Step 1: Write the failing tests** in `SeasonRankingsTest`, each seeding one season with registrations, results and venue points:
  - `test_players_are_ordered_by_points`: 140, 120, 110 → user order matches, places `[1, 2, 3]`.
  - `test_a_tie_on_points_goes_to_more_venue_points`: both 120, venue 9 vs 4 → the 9 first, places `[1, 2]`.
  - `test_a_tie_on_both_shares_the_place_and_skips_the_next`: 140; 110/v5; 110/v5; 95 → places `[1, 2, 2, 4]`.
  - `test_tied_players_are_listed_by_name`: the two shared-place rows come out `Ada` before `Zed`.
  - `test_a_player_on_zero_points_is_not_ranked`: entered, scored 0 → absent; `ranked` counts only the others.
  - `test_points_without_an_entry_are_not_ranked`: result, no registration → absent.
  - `test_venue_points_from_another_season_do_not_break_the_tie`: tied on points, the only venue points stored under a different `season_id` → shared place.
  - `test_the_board_carries_no_venue_figure`: `array_keys($row) === ['user_id', 'name', 'points', 'events', 'place']`.
- [ ] **Step 2: Run, expect failures** — `taskset -c 0-9,12-31 php artisan test tests/Feature/SeasonRankingsTest.php` → FAIL (no `place` key; ratio ordering).
- [ ] **Step 3: Rewrite `rankings()`.** Keep the grouped `points`/`entries`/`names` queries; add one grouped `VenuePoints` sum by `user_id` for `season_id = $this->id`. Filter `entries` to `points > 0`, sort by `[-points, -venue, name, user_id]`, then assign places:

  ```php
  // Competition ranking: a row shares the place of the row before it only
  // when both points AND venue points are equal; otherwise its place is its
  // 1-based position, which is what makes 1, 2, 2, 4.
  ```

  Drop `venue` from each row after placing. In `figuresFor()` remove `perEvent`; `rank()` returns `$row['place']` rather than `$index + 1`. Rewrite the docblocks that argue for per-event ranking, and record the privacy exception in the `rankings()` docblock.
- [ ] **Step 4: Update `SeasonPanelTest`'s rank tests** to the new rule: `test_rank_is_points_per_event_not_points` → `test_rank_is_by_points`; `test_entering_without_scoring_is_ranked_last_rather_than_unranked` → `test_entering_without_scoring_is_not_ranked`; `test_a_tie_on_the_average_is_broken_by_total_points` → `test_a_tie_on_points_is_broken_by_venue_points`. (The copy tests move in Task 2.)
- [ ] **Step 5: Run** `taskset -c 0-9,12-31 bin/suite.sh Season` → the new and updated tests pass. Failures left in other files are expected and are owned by Tasks 2–4; list them.
- [ ] **Step 6: Hand-off checkpoint.** Files: `app/Models/PokerSeason.php`, both test files. Message: `feat(rank): rank a season by points, venue points breaking ties`.

### Task 2: The Season Rank panel (dashboard and profile)

**Files:**
- Modify: `app/Support/SeasonFigures.php`, `resources/js/Components/SeasonPanel.vue`, `resources/js/types/inertia.d.ts` (`SeasonFigures`), `resources/css/4-pages/_season-panel.css` (only if a class loses its last use)
- Test: `tests/Feature/SeasonFiguresTest.php`, `tests/Feature/SeasonPanelTest.php` (copy tests ~304, ~437), `tests/Feature/PlayerProfileTest.php` (~391)

**Interfaces:**
- Consumes: `figuresFor()` from Task 1 (no `perEvent`).
- Produces: `SeasonFigures` props lose `per_event_label` and gain `is_ranked: bool`. `rank_label`, `totals_label` and `ranked_label` are unchanged in meaning.

- [ ] **Step 1: Write the failing tests:**
  - `SeasonFiguresTest::test_an_unranked_player_has_no_rank` (replaces `…no_average_and_no_rank`): 0 points → `rank_label === '—'`, `is_ranked === false`, `ranked_label === null`, and the array has no `per_event_label` key.
  - `SeasonFiguresTest::test_a_ranked_player_shows_totals_and_the_field`: `totals_label === '120 pts over 6 events'`, `ranked_label === 'of 3 ranked players'`, `rank_label === '#2'`.
  - `SeasonFiguresTest::test_a_shared_place_is_shown_as_that_place`: two tied players both get `rank_label === '#2'`.
  - `SeasonPanelTest::test_a_player_yet_to_score_is_told_how_to_be_ranked`: dashboard HTML contains `No points yet` and `Score points to be ranked.`, and not `pts per event`.
  - `PlayerProfileTest::test_an_unranked_player_is_told_so_in_the_third_person`: contains `A player is ranked once they score points.`
- [ ] **Step 2: Run, expect failures** — `taskset -c 0-9,12-31 bin/suite.sh "SeasonFigures|SeasonPanel|PlayerProfile"`.
- [ ] **Step 3: Implement.** `SeasonFigures::figures()` drops `per_event_label` and adds `is_ranked => $figures['rank'] !== null`. In `SeasonPanel.vue` the unranked branch keys on `!season.is_ranked` and uses the three copy strings from Global Constraints. The ranked branch puts `totals_label` in `.season__ratio-main` and `ranked_label` in `.season__ratio-sub`. Update the type and its doc comments. Update the comment in `SeasonFigures` that explains "8.4".
- [ ] **Step 4: Run** the same filter plus `taskset -c 0-9,12-31 npm run typecheck` → PASS.
- [ ] **Step 5: Hand-off checkpoint.** Message: `feat(rank): the season panel shows points and the field, not an average`.

### Task 3: The season page loses the toggle

**Files:**
- Modify: `app/Http/Controllers/Poker/PokerSeasonController.php` (`ORDERS`, `show()`), `resources/js/Pages/Poker/Seasons/Show.vue`, `resources/css/4-pages/_season-show.css` (delete `.season-show__order*` and `.season-show__ratio` blocks)
- Test: rewrite `tests/Feature/SeasonStandingsOrderTest.php`; adjust `SeasonStandingsLayoutTest`, `ContentPreservationTest`, `MergedStandingsPanelTest` only where they assert the toggle, ratio or a 0-point row

**Interfaces:**
- Consumes: `rankings()` from Task 1 (`place`, rank order).
- Produces: Inertia props for `Poker/Seasons/Show` lose `order`, `order_options` and `figure_heading`. Each standings row loses `rank_label` and `rank_title`; `rank` is the shared place. `meter_value` is always points.

- [ ] **Step 1: Rewrite `SeasonStandingsOrderTest`:**
  - `test_standings_follow_the_season_ranking`: the row order and each row's `rank` equal `rankings()`'s order and `place`, with a shared place in the data.
  - `test_a_player_on_zero_points_is_not_listed`: their name is absent from `standings`, and `players_label` counts only the listed rows.
  - `test_there_is_no_order_toggle`: the props have no `order_options`, and the HTML has no `season-show__order`.
  - `test_a_stale_rank_link_still_loads`: `?order=rank` → 200, same row order as without it.
  - `test_the_standings_rank_is_the_dashboards_rank`: for each row, `figuresFor()['rank'] === row.rank`.
  - Keep the venue-column and meter tests that still apply. Delete the ratio tests (`…per_event`, `…ratio…`, `…average…`, `…position_in_the_badge`) — their guarantee is now the first test.
- [ ] **Step 2: Run, expect failures** — `taskset -c 0-9,12-31 bin/suite.sh SeasonStandings`.
- [ ] **Step 3: Implement.** Remove `ORDERS` and the query-string read (an unknown `order` is simply ignored). Key `rankings()` by `user_id`, filter the leaderboard to rows present in it, and sort by the board's position (not by a fresh sort, so tie order is the board's). Set `rank` from `place` and `meter_value` from points, with the leader taken from the first row. In `Show.vue` remove the order group, `figure_heading` (the column heading is the literal `Points`) and the `season-show__ratio` binding. Delete the dead CSS blocks.
- [ ] **Step 4: Run** `taskset -c 0-9,12-31 bin/suite.sh "Season|ContentPreservation|MergedStandings|UnusedCss|VenuePointsPrivacy"` and `npm run typecheck` → PASS. `SeasonStandingsSelfRowTest` passes unedited.
- [ ] **Step 5: Hand-off checkpoint.** Message: `feat(rank): one order for the season standings, by rank`.

### Task 4: The home page's two cards become one

**Files:**
- Modify: `routes/web.php` (the home route's standings block), `resources/views/home.blade.php` (the `@auth` standings grid)
- Test: rewrite `tests/Feature/SeasonRankCardTest.php`

**Interfaces:**
- Consumes: `rankings()` from Task 1.
- Produces: the view gets `$topByRank` (rows with `place <= 3`, each with `user` resolved) and `$topByWins`; `$topByPoints` is gone.

- [ ] **Step 1: Rewrite `SeasonRankCardTest`:**
  - `test_the_boards_are_rank_then_wins`: card titles in order `Season Rank`, `Most Wins`, and no `Most Points`.
  - `test_the_rank_card_shows_places_and_points`: rows show the shared `place` badge and `120 pts`.
  - `test_a_tie_at_third_shows_everyone_tied`: places 1, 2, 3, 3 → four rows.
  - `test_the_landing_page_and_the_dashboard_agree_on_the_rank`: keep, against the new rule.
  - `test_a_player_with_a_result_but_no_entry_is_not_ranked`: keep.
  - `test_no_points_yet_shows_no_rank_card`: results all on 0 → no `Season Rank` card.
- [ ] **Step 2: Run, expect failures** — `taskset -c 0-9,12-31 php artisan test tests/Feature/SeasonRankCardTest.php`.
- [ ] **Step 3: Implement.** `$topByRank = rankings()->filter(place <= 3)` with users resolved, as today. Remove `$topByPoints`. The card shows `<x-rank :place="$row['place']" />` and `{{ number_format($row['points']) }} {{ __('pts') }}`. Remove the Most Points card. The grid's `@if` keys on `$topByRank->isNotEmpty()`. Rewrite the comments that argue for the per-event board.
- [ ] **Step 4: Run** `taskset -c 0-9,12-31 bin/suite.sh "SeasonRankCard|Home|ContentPreservation|PlayerLinkCoverage|MonogramCoverage|UnusedCss"` → PASS. Check the two-card grid at 375px and at desktop width (iframe method in RESUME-HERE).
- [ ] **Step 5: Hand-off checkpoint.** Message: `feat(rank): merge the home page's rank and points cards`.

### Task 5: Record the privacy exception and close out

**Files:**
- Modify: `tests/Feature/VenuePointsPrivacyTest.php` (add the recorded exception and one test), `docs/RESUME-HERE.md` (privacy section; the "standings read two ways" section is superseded), the memory `venue-points-privacy-rule.md`

- [ ] **Step 1: Add `VenuePointsPrivacyTest::test_ranking_reveals_order_not_figures`.** Two players tie on points and differ on venue points. As a third party, the season page, home page and the other player's profile contain neither venue figure, and the order puts the higher one first. Put the owner's exception (2026-11-14) in the test's docblock as the recorded reason.
- [ ] **Step 2: Run** `taskset -c 0-9,12-31 bin/suite.sh VenuePointsPrivacy` → PASS.
- [ ] **Step 3: Update docs.** In `RESUME-HERE.md`: add the exception to "A player's venue points are private"; mark "The season standings read two ways" as superseded 2026-11-14 with a pointer to the spec; update the suite figure. Update the memory file to match.
- [ ] **Step 4: Full gates, pinned:** `bin/suite.sh` → `ran N of N files, 0 failing`; `npm run typecheck` → 0; `bin/sweep.sh` → `no findings`.
- [ ] **Step 5: Hand-off checkpoint.** Message: `docs(rank): record the venue-points ordering exception`.
