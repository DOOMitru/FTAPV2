# Phase 4 — dashboard and the showcase detail pages

Spec: `docs/superpowers/specs/2026-08-30-design-system-design.md`, §4, §4.1 (two registers),
§4.2 (auth takes the dashboard register).
Prerequisite: Phases 0–3 complete and committed.
Baseline suite: **107 passed, 0 failed.**

## What this phase converts

Three views — but the three heaviest in the app.

| View | Utilities | Lines | Data it consumes |
|---|---|---|---|
| `poker/tournaments/show` | 553 | 324 | 13 variables incl. `$podium`, `$orderedResults`, `$availableUsers` |
| `dashboard` | 325 | 191 | 12 incl. `$seasonRank`, `$podiums`, `$userResults` |
| `poker/venues/show` | 311 | 199 | 8 incl. `$venueLeaderboard`, `$totalVenuePoints` |

**1,189 utility instances — more than Phase 3's ten views combined.** All three use
`<x-app-layout>`, so this is the dashboard register throughout: flat surfaces, 1px
hairlines, `--shadow-overlay` only.

## The finding that shapes the phase

**The map block is in the wrong register, and two of these three views need it.**

`.p-map`, `.p-map__pin` and the dark-mode inversion were built for `events` in Phase 2 and
live in `resources/css/5-public/_register.css`. Both showcase pages embed the same venue map.

`.p-map__pin` uses **`box-shadow: var(--shadow-float)`** — a public-register token. Putting
that class on a dashboard page would break §4.1's rule while **no test would object**:
`PublicRegisterTest` checks which *stylesheet* references a fenced token, not which *view*
uses the resulting class. The fence has a blind spot at the view layer.

**Task 1 resolves it:** move the map to `3-components/_map.css` as a shared block, with
`--shadow-overlay` (the dashboard's only permitted shadow) instead of `--shadow-float`, and
leave any public-only flourish behind as a `5-public` modifier if `events` still wants one.

Consider whether `PublicRegisterTest` can grow a third assertion — that no view rendered by
`layouts/app` carries a class defined in `5-public/`. That is harder than it sounds (Blade
class attributes are dynamic) and may not be worth it; **record the decision either way**.

## What already exists and must be reused

Every component these views need is built. The dashboard is the striking case: it uses
**none** of them — `grep -c "x-stat|x-meter|x-rank"` returns 0 — while `poker/seasons/show`
uses six. The dashboard's stat tiles, rank and progress bars are all hand-rolled Tailwind
re-implementations of components that already exist.

```
card   table   rank   meter   stat   badge   empty-state   btn   avatar   page-header
```

**Expect this phase to add very few new blocks.** If a task wants one, check first whether
`seasons/show` already solved the same problem — it is the closest analogue and was built to
the system from the start.

## Global constraints

1. **Never run git commands.** The owner runs every git operation manually, read-only ones
   included. Use `find resources -newer <file>` to see what changed.
2. **Screenshot every page before calling it converted**, both themes. Every bug the owner
   found in Phase 2 passed all computed-style assertions. Chromium under snap cannot write to
   `/tmp`; screenshot to `$HOME`.
3. **Dashboard register only.** No `--gradient-*`, no `--shadow-raised`/`--shadow-float`,
   no `--radius-lg` in anything these three views touch.
4. **A modifier always accompanies its base** — `ModifierClassGuardTest`.
5. **No inline CSS** — `InlineStyleGuardTest`, both forms.
6. **Update `ConvertedViewsTest::NOT_YET_CONVERTED` per task**, not in a lump at the end.

## The safety net

| Test | What it guards |
|---|---|
| `ContentPreservationTest` | `test_tournament_show_preserves_registrant_and_result_data`, `test_dashboard_preserves_career_figures` — asserts on data, never markup |
| `EmptyStateSmokeTest` | all three: venue with no tournaments, tournament with no registrants or results, dashboard for a user with nothing |
| `RouteSmokeTest` | every route as admin, guest and player |
| `ViewAuthorizationTest` / `AdminAccessTest` | the admin-only blocks below |

**`venues/show` has no content test.** `EmptyStateSmokeTest` proves it renders empty;
nothing asserts the leaderboard's contents survive. **Task 4 must add one** before
converting, the way Phase 2 Task 1 added the rules-page assertions.

---

## Task 1: Move the map into the shared layer

**Files:** create `resources/css/3-components/_map.css`; modify `5-public/_register.css`,
`app.css`, `events.blade.php` if a modifier is needed.

- [ ] Move `.p-map`, `.p-map iframe`, the two dark-inversion rules and `.p-map__pin` into
      `3-components/_map.css`, renamed to drop the `p-` prefix — it is no longer
      public-only.
- [ ] Swap `--shadow-float` for `--shadow-overlay` on the pin.
- [ ] Verify `events` still renders correctly; it is the only current consumer.
- [ ] Decide and record whether `PublicRegisterTest` gains a view-layer assertion.

---

## Task 2: `dashboard`

**Files:** `dashboard.blade.php` (325)

The page the owner looks at most. Six sections: quick stats, current season and ranking,
points-structure reference, upcoming tournaments, recent career results.

**Reuse, do not rebuild:** `<x-stat>` for the quick stats row, `<x-rank>` for the season
placing, `<x-meter>` for any progress bar, `<x-table>` for the results list,
`<x-empty-state>` for the empty arms. `poker/seasons/show` uses all of these and is the
reference implementation.

**Watch for:** `test_dashboard_preserves_career_figures` asserts on the *collapsed text*
`'Career Points 645'`, `'Events Played 4'`, `'Tournament Wins 2'` — label and value adjacent
with whitespace collapsed. The test carries a note saying a failure means a label was
renamed, and that renaming user-facing copy should be a deliberate decision rather than a
side effect of restyling. Honour that: if a label must change, say so. `<x-stat>` renders the label then the value, so this should hold,
but check it rather than assume: the test was written that way because bare `assertSee('4')`
matched Tailwind spacing classes and SVG path data.

---

## Task 3: `poker/tournaments/show`

**Files:** the view (553) — the largest single view in the app.

Sections: identity header, map, a **2-1-3 podium display**, a stats ribbon, final standings,
registered players, and an admin sidebar.

**Three admin-gated blocks** at lines 4, 67 and 263 — the last is
`auth()->user()->is_admin && $availableUsers->isNotEmpty()`, an add-registrant form.
`AdminAccessTest` and `ViewAuthorizationTest` cover the gating; do not change the conditions.

**The podium is the interesting problem, and it is currently wrong.** Pre-flight confirms
the source order is literally 2nd (line 125), 1st (134), 3rd (141) — the *markup* is
sequenced for the visual arrangement, so a screen reader announces the runner-up first and
the winner second.

The conversion should fix that: **1-2-3 in the DOM, 2-1-3 on screen**, using `order` on the
flex children. That is exactly what `order` exists for, and CSS reordering does not affect
the accessibility tree. Note this as a deliberate behaviour improvement in the commit, not a
silent side effect.

---

## Task 4: `poker/venues/show`

**Files:** the view (311), plus a new content test.

- [ ] **First**, add `test_venue_show_preserves_leaderboard` to `ContentPreservationTest`:
      venue name, the leaderboard's player names and point totals, `$totalTournaments` and
      `$totalVenuePoints`. **It must pass against the unconverted view** — otherwise it is
      describing the rewrite rather than guarding it.
- [ ] Then convert. `$venueLeaderboard` is a table; `$totalTournamentPoints`,
      `$totalVenuePoints`, `$uniqueVenuePointPlayers` are `<x-stat>` tiles.

---

## Task 5: Sweep and close

- [ ] `ConvertedViewsTest::NOT_YET_CONVERTED` down to **25 entries**, all admin CRUD.
- [ ] Full audit: all three at 375/768/1280 in both themes, **fresh load per theme**.
- [ ] Reduced motion; theme toggle on the dashboard.
- [ ] `php artisan test`, `npm run build`.
- [ ] Update `docs/RESUME-HERE.md` for the Phase 5 handoff.

## Phase 4 exit criteria

- [ ] Suite green; `ConvertedViewsTest` at 25 entries.
- [ ] None of the three contains a Tailwind utility class.
- [ ] No horizontal scroll at 375px on any of them.
- [ ] Both themes verified by screenshot on all three.
- [ ] The map block shared, using `--shadow-overlay`, with `events` still correct.
- [ ] `venues/show` has a content test that passed before the conversion.
- [ ] The podium keeps DOM order 1-2-3 while displaying 2-1-3.
