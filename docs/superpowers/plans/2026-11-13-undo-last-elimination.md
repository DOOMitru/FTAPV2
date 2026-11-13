# Undo the Last Elimination — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An admin can take back the most recent elimination in an unpublished tournament, one at a time, repeatably.

**Architecture:** Places are handed out from the bottom up, so the most recent elimination is the result holding the **lowest place** in that tournament. Deleting that one row leaves every other finish untouched and returns `registrants − results`, so the next `eliminate()` hands out the same place at the same price. No repricing, no shifting, no second points calculation.

**Tech Stack:** Laravel 13, PHPUnit 12, Inertia + Vue 3 + TypeScript.

**Spec:** `docs/superpowers/specs/2026-11-13-undo-last-elimination-design.md`

## Global Constraints

- **Never run `git` commands.** The repository owner runs every git operation manually. Each task ends at a hand-off checkpoint: state what to commit and suggest a message. Do not stage, commit, branch or diff.
- **Admin-only**, by the existing `Route::middleware('admin')` group in `routes/web.php:264`.
- **Published is final.** Use `PokerTournament::publishedRefusal()`; do not restate the rule.
- **No inline CSS and no inline JavaScript**, enforced by `InlineStyleGuardTest`.
- **Check a new class name against the stylesheet before using it** — reuse existing hooks on the row; add no new CSS unless a test proves it unused-free.
- **Run the suite with `bin/suite.sh`**, never a bare full `phpunit` run (PHP 8.5.4 segfaults locally; a `FAIL` with *no summary line (crashed)* is a file to re-run alone before believing it).
- Flash messages are drawn by the shell as toasts. Controllers flash `status` or `error` and pages draw nothing of their own.
- Player names in flash and confirmation copy go through `emph()`, as `eliminate()` does.

## Review Focus

1. **A result with no `user_id`.** Rows can come from a result with no registration behind it (`PokerTournamentController.php:410`). The route addresses a player by `user_id`, so such a result is unaddressable — `can_undo` must be false for it rather than offering a button the route refuses. *(Task 2)*
2. **Two admins, or one stale page.** The `user_id` sent may no longer be the lowest place by the time it arrives. The server must refuse, not delete whatever is now last. *(Task 1)*
3. **Nothing eliminated yet.** Undo against a tournament with zero results must refuse with a reason, not 500 on a null. *(Task 1)*
4. **A late registration between render and undo.** Registering shifts every recorded place and reprices it, so the confirmation's place and points can be stale. The server recomputes from the row it deletes and never trusts the client's figures. *(Task 1)*
5. **A player, or a signed-out visitor, calling the route directly.** Refused by the admin middleware, like `eliminate`. *(Task 1)*

---

### Task 1: The undo route and action

**Files:**
- Modify: `app/Models/PokerTournament.php` — add `lastEliminated()`
- Modify: `app/Http/Controllers/Poker/PokerTournamentController.php` — add `uneliminate()`, beside `eliminate()` at `:574`
- Modify: `routes/web.php:271-272` — add the DELETE route beside the POST
- Test: `tests/Feature/UndoEliminationTest.php` (create)

**Interfaces:**
- Consumes: `PokerTournament::publishedRefusal(): ?string`; `PokerTournamentResult` with integer-cast `place` and `points`.
- Produces: `PokerTournament::lastEliminated(): ?PokerTournamentResult` — the result in this tournament holding the lowest `place`, or null when there are none. Task 2 uses it to decide `can_undo`.
- Produces: route name `poker.tournaments.uneliminate`, `DELETE poker/tournaments/{tournament}/eliminate`, body `user_id`.

- [x] **Step 1: Write the failing tests** in `tests/Feature/UndoEliminationTest.php`

Use the `BuildsTournaments` trait and `RefreshDatabase`, following `EliminatePlayerTest`'s helpers (`admin()`, `tournament()`, `field()`, `eliminate()`). Add a private `undo(PokerTournament $t, User $player, ?User $actor = null)` posting `$this->delete(route('poker.tournaments.uneliminate', $t), ['user_id' => $player->id])`.

```php
public function test_undoing_removes_the_most_recent_finish(): void
// field of 5; eliminate players[0] (5th) and players[1] (4th); undo players[1];
// assert PokerTournamentResult::where('user_id', $players[1]->id)->exists() is false
// assert players[0] still holds place 5

public function test_the_freed_place_is_handed_out_again_at_the_same_price(): void
// payTen(); field of 5; eliminate [0] then [1]; undo [1]; eliminate [2]
// assert [2]'s result has place 4 and the same points [1] had

public function test_undo_walks_back_one_at_a_time(): void
// field of 5; eliminate [0],[1],[2]; undo [2]; undo [1]
// assert only [0] has a result, holding place 5

public function test_only_the_most_recent_elimination_can_be_undone(): void
// field of 5; eliminate [0] then [1]; undo [0]
// assert [0] still has their result and the response flashes an error

public function test_undoing_is_refused_when_nobody_is_out(): void
// field of 5, no eliminations; undo [0]; assert an error flash and no exception

public function test_undoing_is_refused_once_results_are_published(): void
// complete field, publish, then undo the last; assert the result survives and
// the error names the tournament (publishedRefusal's sentence)

public function test_a_player_cannot_undo_an_elimination(): void
// acting as a non-admin; assert 403 and the result survives

public function test_a_finished_but_unpublished_field_can_still_be_undone(): void
// eliminate all 5, do not publish; undo the winner
// assert the result is gone and isComplete() is false again
// -- all out and unpublished is exactly when a wrong order gets noticed

public function test_a_late_registration_does_not_confuse_which_finish_is_last(): void
// field of 5; eliminate [0] (5th) then [1] (4th); register a sixth player,
// which shifts every recorded place down and reprices it; undo [1]
// assert [1]'s result is gone and [0] still holds the only remaining finish
// -- place, not created_at, decides which row is the most recent
```

- [x] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/phpunit tests/Feature/UndoEliminationTest.php`
Expected: FAIL — route `poker.tournaments.uneliminate` not defined.

- [x] **Step 3: Add `lastEliminated(): ?PokerTournamentResult` to `PokerTournament`**

Returns `$this->results()->orderBy('place')->first()`. Document why place and not `created_at`: a late registration shifts every recorded place, and place is the authority on order.

- [x] **Step 4: Add the route** in the admin group, directly under `tournaments.eliminate`

`Route::delete('tournaments/{tournament}/eliminate', [PokerTournamentController::class, 'uneliminate'])->name('tournaments.uneliminate');` — the same URI-pair shape `publish`/`unpublish` uses, with a comment saying why it is a DELETE on the same URI.

- [x] **Step 5: Implement `uneliminate(PokerTournament $tournament, Request $request): RedirectResponse`**

Validate `user_id` as `['required', 'string']`, as `eliminate()` does. Then, in order: `publishedRefusal()`; `lastEliminated()` null → refuse; that row's `user_id` not matching the request → refuse. Otherwise capture `place` and `points` from the row, delete it, and flash a success naming the player and the freed place. Each refusal returns `back()->with('error', ...)`; the success returns `back()->with('status', ...)`. Copy in the voice of `eliminate()`'s sentences — the player through `emph()`, the place through `Number::ordinal()`.

- [x] **Step 6: Run the tests**

Run: `./vendor/bin/phpunit tests/Feature/UndoEliminationTest.php`
Expected: PASS.

- [x] **Step 7: Hand-off checkpoint**

Do not commit. Report the files touched and suggest a message, e.g. `feat(tournaments): undo the last elimination`, with a body saying why place and not timestamp identifies it.

---

### Task 2: The Undo button on the row

**Files:**
- Modify: `app/Http/Controllers/Poker/PokerTournamentController.php:406-450` — two fields in the standings row map
- Modify: `resources/js/Pages/Poker/Tournaments/Show.vue` — the `entry__actions` block at `:244-253`, and an `undo()` beside `eliminate()` at `:84`
- Test: `tests/Feature/UndoEliminationTest.php` (extend)

**Interfaces:**
- Consumes: `PokerTournament::lastEliminated()` from Task 1; route `poker.tournaments.uneliminate`.
- Produces: row fields `can_undo: boolean` and `undo_confirm: string`, read by `Show.vue`.

- [x] **Step 1: Write the failing tests**

```php
public function test_only_the_last_eliminated_row_offers_undo(): void
// payTen(); field of 5; eliminate [0] then [1]
// assert the Inertia page's standings row for [1] has can_undo true
// and the row for [0] has can_undo false

public function test_a_row_still_in_the_field_cannot_be_undone(): void

public function test_undo_is_not_offered_once_results_are_published(): void

public function test_undo_is_not_offered_to_a_player(): void

public function test_a_result_with_no_registration_behind_it_is_not_undoable(): void
// a PokerTournamentResult created with user_id null; assert can_undo false
// -- the route addresses a player by user_id, so that row is unaddressable

public function test_the_confirmation_names_the_place_and_points_being_given_up(): void
// assert undo_confirm contains the ordinal place and the formatted points
```

Read props with Inertia's test assertions, as the existing Show-page tests do.

- [x] **Step 2: Run them and watch them fail**

Run: `./vendor/bin/phpunit tests/Feature/UndoEliminationTest.php`
Expected: FAIL — `can_undo` missing from the row.

- [x] **Step 3: Add `can_undo` and `undo_confirm` to the standings row**

Compute the undoable result once outside the map (`$tournament->lastEliminated()`), as `$nextPlace` and `$nextPlacePoints` already are, and close over it. `can_undo` is `$isAdmin && ! $tournament->isPublished() && $result && $result->user_id !== null && $result->is($undoable)`. `undo_confirm` names the player, the place given up and the points given up — the mirror of `eliminate_confirm` at `:435`.

- [x] **Step 4: Add the button and its handler to `Show.vue`**

An `undo(row)` beside `eliminate(row)`, asking through `useConfirm` with `row.undo_confirm` and issuing `router.delete(\`${base}/eliminate\`, { data: { user_id: row.user_id }, preserveScroll: true })`. In the template, a `Btn` with `variant="ghost" size="sm"` after the points `Badge`, shown on `row.can_undo` only — its own condition, not a branch of the `v-else-if` chain, because an undoable row shows its badge *and* its button. Comment why only one row ever has it.

- [x] **Step 5: Run the tests**

Run: `./vendor/bin/phpunit tests/Feature/UndoEliminationTest.php`
Expected: PASS.

- [x] **Step 6: See it in a browser**

Run: `out=$(bin/sweep.sh); status=$?` — the baseline is `141 pages ... no findings`. A mounted page and sound markup is all the sweep can tell you; press Undo by hand on a seeded tournament to see the toast and the row returning to the field.

- [x] **Step 7: Hand-off checkpoint**

Do not commit. Suggest a message, e.g. `feat(tournaments): offer undo on the last eliminated row`.

---

### Task 3: Record the decision and verify the whole suite

**Files:**
- Modify: `docs/RESUME-HERE.md` — a dated entry under **Decisions taken since the conversion that still bind** (`:567`)

- [x] **Step 1: Write the entry**

Dated 2026-11-13, in the voice of the entries around it: what the feature is, that the most recent elimination is the lowest place and not the newest row, that undo is refused for anything else so a stale page cannot delete a finish nobody saw, that it stops at publish, and that no repricing is involved because the freed place is the next one handed out.

- [x] **Step 2: Run the whole suite**

Run: `out=$(bin/suite.sh); status=$?; printf '%s\n' "$out" | tail -5`
Expected: `ran 119 of 119 files, 0 failing`. A `FAIL` with *no summary line (crashed)* is the local segfault — re-run that file alone before believing it.

- [x] **Step 3: Refresh the suite figure**

The count in `docs/RESUME-HERE.md:8` is the sum of each file's `Tests:` line, not something `bin/suite.sh` prints. Re-sum it over the same `find tests -name '*Test.php' | sort` list and update the figure and its date.

- [x] **Step 4: Hand-off checkpoint**

Do not commit. Suggest a message, e.g. `docs: record the undo-elimination decision`.
