# Phase 5 — admin CRUD, and the removal of Tailwind

Spec: `docs/superpowers/specs/2026-08-30-design-system-design.md`, §4, §4.1, §4.2.
Prerequisite: Phases 0–4 complete and committed.
Baseline suite: **108 passed, 0 failed.**

**This is the last phase.** It ends with Tailwind deleted from the project.

## What this phase converts

25 views, 1,930 utility instances — but far more repetitive than the count suggests.

| Kind | Count | Utilities | Shape |
|---|---|---|---|
| **Index pages** | 8 | 829 | header + table + pagination + a confirmed delete |
| **Create/edit forms** | 15 | 1,002 | label + text input + select, in varying counts |
| **Other** | 2 | 99 | `users/show`, `components/tournament-badge` |

Verified uniform, not assumed:

```
all 8 index pages   1 table   1 ->links()   1 delete form   1 confirm()
all 15 forms        <x-input-label> + <x-text-input> + <select>, 2–6 fields each
```

Every component needed already exists: `<x-page-header>`, `<x-table>`, `<x-field>` (with the
`bag` prop from Phase 3), `<x-btn>`, `<x-badge>`, `<x-rank>`, `<x-empty-state>`,
`<x-card>`, `.rows`, `.entry`, and the pagination view registered in Phase 2 Task 6.

**Expect to add almost nothing.** If a task wants a new block, check `dashboard`,
`tournaments/show` or `seasons/show` first — one of them has almost certainly solved it.

## Global constraints

1. **Never run git commands.** The owner runs every git operation manually, read-only ones
   included. Use `find resources -newer <file>`.
2. **Screenshot every page before calling it converted**, both themes. Chromium under snap
   cannot write to `/tmp`; write to `$HOME`.
3. **Dashboard register throughout.** No `--gradient-*`, `--shadow-raised`, `--shadow-float`
   or `--radius-lg` anywhere these views touch.
4. **A modifier always accompanies its base** — `ModifierClassGuardTest`.
5. **No inline CSS** — `InlineStyleGuardTest`. And the custom-property exception is for
   genuinely data-driven values; do not leave a `style="--x: 1"` behind because the guard
   happens to permit it.
6. **Update `ConvertedViewsTest::NOT_YET_CONVERTED` per task.**

## Two things only this phase can do

**Delete the legacy Breeze components.** Their remaining call sites are all in these views:

```
input-label ~20   text-input ~20   input-error ~20   primary-button ~20
secondary-button 1   danger-button 1   auth-session-status 2
```

They cannot go until the last form converts. Deleting them is Task 5, not Task 1.

**Remove Tailwind.** `app.css` still ends with `@tailwind base/components/utilities`, loading
*after* the design system so unconverted views keep rendering. That is Task 6.

> **Pre-flight note on the JS trap.** Phase 1 Task 12 found the modal adding Tailwind's
> `.overflow-y-hidden` to `<body>` from inside a JS string — nothing in a Blade class
> attribute named it, so removing Tailwind would have silently disabled the scroll lock.
> **That specific hazard is already clear**: `resources/js/` names no Tailwind class, and the
> only `classList` calls in views use `is-modal-open`, which is ours. Re-run both greps at
> Task 6 anyway; something may be added between now and then.

## The safety net

| Test | What it guards |
|---|---|
| `RouteSmokeTest` | every GET route as admin, guest and player; no 5xx, no leaked Blade artifact |
| `EmptyStateSmokeTest` | the `@forelse` empty arms across these index pages |
| `AdminAccessTest` / `ViewAuthorizationTest` | `/poker` is admin-only in its entirety |
| `UserManagementTest`, `PokerSeasonTest`, `PokerTournamentTest`, `PokerTournamentRegistrantTest`, `PointsStructurePageTest`, `TournamentScheduleTest` | the CRUD behaviour itself |
| `ConvertedViewsTest` | the ledger, both directions |

**Behaviour here is well covered.** The risk is visual and structural.

---

## Task 1: One index page, then the other seven

**Files:** `poker/seasons/index` first (120), then `venues`, `tournaments`, `results`,
`registrants`, `venue-points`, `points-structure`, `users`.

- [ ] Convert `poker/seasons/index` alone and **screenshot it**. It sets the pattern for
      seven more, so getting the row actions, the delete confirm and the pagination right
      once is worth more than converting three quickly.
- [ ] Then apply that pattern to the rest, checking each renders.

**Watch for:**
- **`users/index` uses `@foreach`, not `@forelse`** — alone among the eight, it has no empty
  state at all. Add one; an admin list that renders as an empty table with no explanation is
  worse than one that says so.
- Every delete is wrapped in `confirm()`. **Keep it.** Nothing in the suite asserts the
  dialog exists, and a destructive action losing its confirmation is not something a test
  will tell you about.
- The pagination view is already the design system's; the views only call `->links()`.

---

## Task 2: The simple forms

**Files:** `points-structure` create/edit (29 each), `venues` create/edit (50 each),
`seasons` create/edit (68 each) — 6 views, 294 utilities.

Pure `<x-field>` work. `seasons` has date inputs; check `type="date"` passes through.

---

## Task 3: The forms with selects

**Files:** `registrants`, `venue-points`, `results`, `tournaments` create/edit — 8 views,
~620 utilities.

Each has 1–3 `<select>` elements. `<x-field>` takes a slot for those; `tournaments/show`'s
admin panel is the working example.

**Watch for:** these selects are populated from controller collections. Preserve the
`selected` logic on edit exactly — a select that silently loses its current value is the
classic form-conversion bug, and no smoke test catches it. **Assert one of them**: load an
edit page for a record with a known relation and check the right `<option selected>` renders.

---

## Task 4: `users/edit` and `users/show`

**Files:** `users/edit` (81), `users/show` (81)

`users/edit` carries the `is_admin` toggle — use `.field__check` from Phase 3. `users/show`
is a read-only profile view; `<x-card>` + `.rows` covers it.

---

## Task 5: Delete the legacy components

- [ ] Confirm zero call sites for each, then delete: `input-label`, `text-input`,
      `input-error`, `primary-button`, `secondary-button`, `danger-button`,
      `auth-session-status`.
- [ ] `components/tournament-badge` has **zero callers** and its own aesthetic contradicts
      the system. **This is an owner decision, not a conversion** — delete or redesign. Ask.

---

## Task 6: Remove Tailwind

- [ ] `ConvertedViewsTest::NOT_YET_CONVERTED` must be empty first.
- [ ] Re-run both greps from the pre-flight note above.
- [ ] Delete `@tailwind base; @tailwind components; @tailwind utilities;` from `app.css`.
- [ ] Remove `tailwindcss`, `@tailwindcss/forms` and `autoprefixer` if unused from
      `package.json`; delete `tailwind.config.js`; check `postcss.config.js`.
- [ ] `npm install`, `npm run build`, `php artisan test`.
- [ ] **Screenshot every converted page again.** This is the moment a forgotten utility
      stops rendering, and the suite will not tell you.
- [ ] Measure the CSS bundle before and after. Record it.

## Phase 5 exit criteria

- [ ] `ConvertedViewsTest::NOT_YET_CONVERTED` is empty.
- [ ] No `@tailwind` directive, no `tailwind.config.js`, no Tailwind dependency.
- [ ] Suite green; `npm run build` succeeds.
- [ ] No horizontal scroll at 375px on any page in the app.
- [ ] Both themes verified by screenshot across every page.
- [ ] The legacy Breeze components are deleted.
- [ ] The `tournament-badge` decision is made and recorded.
- [ ] `docs/RESUME-HERE.md` records the finished state and what remains open — the modal
      focus trap, the `MustVerifyEmail` gap, and the accent hue drift.
