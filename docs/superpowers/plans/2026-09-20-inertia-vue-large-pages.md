# Inertia + Vue Conversion — Plan 6: The two large pages

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert `/seasons/{season}` and `/tournaments/{tournament}` — the last
two authenticated pages — and close out the conversion.

**Architecture:** `p-event` becomes the twelfth duplicated component, because
two public Blade pages still draw it. The tournament page converts in two
halves: the page itself, then the register dialog, which is the only remaining
Alpine component of any size in the app.

**Tech Stack:** Laravel 13.32, Inertia 3, Vue 3.5 `<script setup lang="ts">`,
Vite 7, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-09-19-inertia-vue-conversion-design.md`

## Global Constraints

- **Props carry display-ready values (spec §5a).** PHP decides, Vue places. No
  `number_format`, ordinals, date formats, pluralisation or `__()` in
  TypeScript.
- **Semantic props, never stylistic.** `tier: "gold"`, never
  `tier: "notification--gold"`.
- **A player's venue points are readable only by that player and by admins.**
  On the season page the figure is **withheld from the props**, and the
  qualified/not verdict is computed in PHP from a figure that never leaves the
  controller. The controller already does this; do not undo it.
- **TypeScript throughout.** `npm run typecheck` must pass.
- **The flash is read through a computed**, never captured in a const.
  `FlashMessageParityTest` enforces both halves.
- **Write class names out in full.** A composed `` `rank rank--${place}` `` is
  invisible to `UnusedCssClassTest` and `ComponentParityTest`.
- **Delete the Blade view in the same commit as the page that replaces it** —
  except `components/p-event.blade.php`, which two public pages still render.
- **Never run git commands.** Every "commit" step is a hand-off.

---

## What makes these two different from Plans 3–5

**They are the pages with the most tests.** Twenty-two files touch
`seasons.show` and twenty-five touch `tournaments.show`. Most assert on
rendered HTML. Budget more time for porting than for writing.

**The tournament page is the one with real client state**, and more of it than
the venue-points picker: a search term, a filtered list, two `<dialog>`
elements, and a "stays open across registrations" behaviour that is currently a
SERVER fact — `register()` flashes `register_open`, and `x-init` reopens the
dialog on the way back. Inertia changes what that can be, and Task 4 says how.

**`p-event` cannot simply convert.** `events.blade.php` and `home.blade.php`
are public and stay Blade, so the card exists twice for good — a twelfth twin
alongside the eleven in spec §2. `ComponentParityTest` discovers it
automatically: `PEvent.vue` kebabs to `p-event.blade.php`.

**Two guards the spec expected to retire will not.** Spec §6 lists
`MarkupBalanceTest` and `AlpineAttributeGuardTest` as retiring "when their last
target converts". Their last targets are `components/{modal,topbar,dropdown}.blade.php`
and the public pages that render them — none of which convert, under the
hybrid boundary in §1. Task 5 records that rather than deleting two working
guards on a prediction that did not hold.

---

## File Structure

**Created:**

| File | Responsibility |
|---|---|
| `resources/js/Components/PEvent.vue` | The shared event card, Vue half. |
| `resources/js/Pages/Poker/Seasons/Show.vue` | Figures, finale panel, standings, venues, tournaments. |
| `resources/js/Pages/Poker/Tournaments/Show.vue` | Event card, merged standings panel, admin controls. |
| `resources/js/Pages/Poker/Tournaments/Partials/RegisterDialog.vue` | The player picker, as a dialog. |

**Modified:** `Poker/PokerSeasonController`, `Poker/PokerTournamentController`,
`tests/Feature/InertiaPropContractTest.php`, and the tests each task names.

**Deleted:** `poker/seasons/show.blade.php`, `poker/tournaments/show.blade.php`.
**Kept:** `components/p-event.blade.php`, which `/` and `/events` still render.

---

## Task 1: `PEvent.vue`, the twelfth twin

**Files:**
- Create: `resources/js/Components/PEvent.vue`
- Test: `tests/Feature/ComponentParityTest.php` (discovers it; no edit needed),
  `tests/Feature/EventCardTest.php`

**Interfaces:**
- Produces: a component taking one `tournament` prop of display-ready values
  plus `details` and `map` booleans, and a default slot for the description.

**The Blade computes eleven things in the template.** Read
`resources/views/components/p-event.blade.php` and move every one into the
prop, because the Vue card is handed values rather than a model:

```ts
interface EventCard {
    id: string;
    name: string;
    season_label: string;
    month_label: string;       // "Sep"
    day_label: string;         // "20"
    weekday_label: string;     // "Sat"
    time_label: string;        // "7:00 PM"
    venue_label: string;       // the venue's name, or "Location TBD"
    map_src: string | null;    // null unless map && venue && address
    map_title: string;
    address: string | null;
    href: string;
    season_href: string;
    venue_href: string | null; // admin only; null otherwise
    is_registered: boolean;
    can_register: boolean;
    can_unregister: boolean;
    awaiting_approval: boolean;
    unregister_confirm: string;
}
```

**Four conditional branches must agree with the controller**, and the Blade's
comments say why each exists: registration open, already registered, awaiting
approval, closed. `can_register` is
`! registered && isApproved() && (is_admin || ! hasStarted())` — asymmetric with
`can_unregister`, which is `registered && ! hasRecordedResults()`. Copy the
reasoning comments across; they are the record of four bugs.

**`$showMap` is not `map`.** A venue with no address never drew one, and the
card's min-height keys on the rendered map rather than on the prop — hence
`p-event--mapless`. Send `map_src: null` and key the class on that.

**Register and Unregister are `router.post` / `router.delete`, not forms.** The
Blade card posts them; a Vue card cannot, and the two paths are
`POST /tournaments/{tournament}/register` and
`DELETE /tournaments/{tournament}/unregister` — not one URL under two verbs.
`Dashboard.vue` already calls both and is the shape to copy, `preserveScroll`
included.

**The kebab menu is `Dropdown.vue` and `DropdownLink.vue`**, which exist from
Plan 2. Its two entries are Season Standings, and a Venue Report that is drawn
only for an admin with a venue — offering it to a player is offering a 403.

- [ ] **Step 1: Write the component**, copying every class hook and every
      explanatory comment from the Blade. `ComponentParityTest` will fail on
      the first missing hook, which is the fastest way to find them.

- [ ] **Step 2: Run the parity guard**

```bash
vendor/bin/phpunit tests/Feature/ComponentParityTest.php 2>&1 | sed -E 's/\x1b\[[0-9;]*[A-Za-z]//g' | tail -20
```

It discovers the pair with no edit. Expect a list of hooks to reconcile; work
through it until it is empty.

- [ ] **Step 3: Prove the pair is actually being checked**

Remove one class from `PEvent.vue` and watch the guard name it. A pair that has
never failed may not be a pair at all — the mapping is by filename.

- [ ] **Step 4: Type-check, build, full suite**

Nothing renders the component yet, so the suite should be unchanged. Two things
can still fail here:

- `UnusedCssClassTest`, finding a class the Vue file mentions that no
  stylesheet declares — the `badge--neutral` failure from Plan 2.
- `ConvertedViewsTest`, if a utility class came across with the markup.

Both now read `.vue` (the guard re-homing, 2026-09-20), so they see this file
the day it is written rather than the day something renders it.

- [ ] **Step 5: Commit (hand-off)**

```
feat: add the Vue half of the shared event card

The twelfth twin. events.blade.php and home.blade.php are public and stay
Blade, so this card exists twice for good rather than for the length of the
conversion -- ComponentParityTest discovers the pair by name and holds the two
to the same class hooks.
```

---

## Task 2: `/seasons/{season}`

**Files:**
- Create: `resources/js/Pages/Poker/Seasons/Show.vue`
- Modify: `app/Http/Controllers/Poker/PokerSeasonController.php`,
  `tests/Feature/InertiaPropContractTest.php`
- Delete: `resources/views/poker/seasons/show.blade.php`
- Test: `SeasonStandingsOrderTest` (11), `FinaleQualificationTest` (16),
  `SeasonStandingsLayoutTest`, `SeasonStandingsSelfRowTest`,
  `SeasonStandingsVenueColumnTest`, `VenuePointsPrivacyTest`,
  `ContentPreservationTest`, `EmphasisTest`, `IndexRowLinksTest`,
  `MonogramCoverageTest`, `PlayerLinkCoverageTest`, `VenueStatRowsTest`,
  `ConfirmDialogTest`, `AdminAccessTest`, `ViewAuthorizationTest`

**Props:**

```ts
interface Props {
    season: { id: string; name: string; delete_confirm: string };
    can_manage: boolean;              // is_admin
    tournaments_label: string;
    points_label: string;
    players_label: string;
    finale: null | {                  // null when no threshold is set at all
        wins_label: string;           // the figure, or "Not set"
        points_label: string;
        venue_points_label: string;
    };
    order: 'points' | 'rank';
    order_options: Array<{ value: string; label: string; href: string; current: boolean }>;
    /** "Per event" in rank order, "Points" otherwise. */
    figure_heading: string;
    shows_venue_points: boolean;
    standings: SeasonRow[];
    venues: Array<{ name: string; count: number; label: string; max: number }>;
    tournaments: Array<{ id: string; name: string; date_label: string; href: string }>;
}

/**
 * One row of the standings. Named for this page, because the tournament page
 * in Task 3 has a row of its own with a different shape -- one name for two
 * shapes is the drift this project keeps meeting.
 */
interface SeasonRow {
    key: string;                  // the user id, or the player name when there is none
    is_self: boolean;
    rank: number;                 // the position, 1-based
    rank_label: string | null;    // "#3" in rank order, null otherwise
    rank_title: string | null;    // "8.4 points per event", the badge's tooltip
    shown_name: string;           // the nickname when there is one
    full_name: string | null;     // only when shown_name is a nickname
    player_id: string | null;
    initials: string;
    meter_value: number;
    meter_max: number;            // $leaderValue, the same for every row
    meter_label: string;          // a whole sentence, different per order
    meter_value_text: string | null;   // the ratio, rank order only
    played_label: string;
    wins_label: string;
    venue_points_label: string | null; // absent when withheld -- see the rule
    finale_state: 'none' | 'qualified' | 'not-qualified';
}
```

**Nine decisions the template currently makes, and where each goes.**

1. **`$showsVenuePoints = auth()->user()->is_admin`** — becomes a prop. The
   figure itself is ALREADY withheld from the data by the controller
   (`$readsVenuePoints`), and that stays: the column flag decides whether to
   draw a column, not whether a figure is safe.
2. **`$isSelf`** — per row, and the Blade's comment explains the guard: a
   result can carry no linked account, so both sides must have an id before
   they are compared. Send `is_self` per row.
3. **`$rank = $index + 1`** and `$ratio = number_format($row['ratio'] ?? 0, 1)`
   — both per row, both PHP's.
4. **`$shownName`** — the nickname when there is one, otherwise the full name,
   and **deliberately not `display_name`**, which falls back to the first name
   alone and would drop surnames. Send `shown_name` and `full_name`, the second
   only when the first is a nickname (it becomes the link's `title`).
5. **The meter's accessible label** — a whole sentence in PHP, different in
   each order. Send `meter_label` and `meter_value_text`.
6. **`$leaderValue`** — already computed; send it as `meter_max` on each row.
   It is the same value for every row, and putting it there keeps the meter's
   two numbers together rather than making the template reach outside its
   `v-for` for one of them.
7. **The finale cell's three states** — no thresholds at all (`—`), qualified
   (a tick plus visually-hidden "Qualified"), not qualified (visually-hidden
   "Not qualified"). Send `finale_state: 'none' | 'qualified' | 'not-qualified'`.
8. **The order links** — `route('seasons.show', ['season' => …, 'order' => …])`
   for each option, with `current`. They are LINKS and must stay links: the
   order is part of the address, so it survives a reload and can be sent to
   somebody. Use `<Link>`, not a click handler.
9. **The venue meters' max** — `$venueStats->max('count')`, computed once.

**The three-state finale cell is the one to get right.** `hasThresholds()` is
true when ANY of the three is set, so a partly decided season reaches the
table, and `number_format(null)` renders `0` — a target nobody chose and
everybody has met. The Blade guards each figure separately; keep that.

- [ ] **Step 1: Change the controller** to `Inertia::render('Poker/Seasons/Show')`,
      mapping every row through the props above. Leave the query work exactly
      as it is: the `$entered` flip, the grouped venue-points query, the
      `rankings()` tie-break and the `(int)` cast on the SUM all have comments
      recording the bug each one fixed.

- [ ] **Step 2: Write the page.** Class hooks to keep: `season-show__order`,
      `season-show__order-option`, `season-show__order-option--current`,
      `season-show__standings`, `season-show__standings-card`,
      `season-show__self-row`, `season-show__rank`, `season-show__player`,
      `season-show__player-row`, `season-show__player-name`,
      `season-show__meter-cell`, `season-show__ratio`, `season-show__stat`,
      `season-show__stat--played`, `season-show__stat--won`,
      `season-show__stat--venue`, `season-show__finale`,
      `season-show__qualified`, `season-show__tournament`, `stat-rows`,
      `stat-rows--boxed`, `l-grid--trio`.

      **`data-label` on the three stat cells feeds a `::after` in the phone
      layout** — "12 played", not "played 12". Keep the attributes.

- [ ] **Step 3: Register and delete the Blade view**

```php
        'Poker/Seasons/Show' => ['seasons.show', 'season'],
```

`seasons.show` is the PUBLIC-prefixed name for this admin-loaded page; check
`php artisan route:list --path=seasons` before assuming otherwise.

- [ ] **Step 4: Type-check, build, full suite; port what breaks**

Fifteen files. Work by kind, as Plan 5's dashboard task did: figures become
prop equalities, markup questions read the page file, and anything asserting
"a player does not see X" becomes an assertion about the PROPS, which is
stronger.

**`VenuePointsPrivacyTest` is the one to be most careful with.** It is the
owner's rule. Every assertion there must end up asserting absence from the
props, never absence from the markup.

- [ ] **Step 5: Prove each ported assertion fails**, and specifically mutate
      the privacy rule: make `$readsVenuePoints` return true and watch
      `VenuePointsPrivacyTest` fail.

- [ ] **Step 6: Verify in a browser at 1440, 960 and 375**

- both orders, via the links, with the address changing and surviving a reload
- as a player: **no Venue points column at all**
- as an admin: the column, with figures
- the self row tinted, and carrying "(you)" for a screen reader
- a season with no thresholds: the panel says so rather than being absent
- a partly decided season: "Not set" for the undecided ones, never `0`
- the meters scale against the leader in whichever figure is shown

- [ ] **Step 7: Commit (hand-off)**

---

## Task 3: `/tournaments/{tournament}` — everything but the register dialog

**Files:**
- Create: `resources/js/Pages/Poker/Tournaments/Show.vue`
- Modify: `app/Http/Controllers/Poker/PokerTournamentController.php`,
  `tests/Feature/InertiaPropContractTest.php`
- Test: `MergedStandingsPanelTest` (25), `RegistrantRemovalTest` (13),
  `PublishOfferTest`, `PublishResultsTest`, `EliminatePlayerTest`, `PodiumTest`,
  `RegisteredPlayersOrderTest`, `RegistrationWindowTest`, `EventCardTest`,
  `DeleteConfirmationTest`, `TournamentArchiveTest`,
  `TournamentShowQueryShapeTest`, `PlayerApprovalTest`

**Do NOT delete the Blade view in this task.** The register dialog is still in
it. Task 4 deletes it.

**Props:** the tournament card's shape from Task 1, plus:

```ts
interface Props {
    tournament: EventCard & {
        description: string | null;
        is_published: boolean;
        publish_confirm: string;
        unpublish_confirm: string;
        delete_confirm: string;
    };
    can_manage: boolean;
    can_publish: boolean;          // ! published && complete
    standings_title: string;       // "Final Standings" | "Standings" | "Registered Players"
    registrants_label: string;
    standings: StandingsRow[];
    /** Shown after the last elimination, once. */
    offer_publish: boolean;
    field_locked_note: string | null;
}

interface StandingsRow {
    key: string;                   // registrant id, or result id -- see below
    name: string;
    nickname: string | null;
    player_id: string | null;
    initials: string;
    place: number | null;          // null while still in
    points_label: string | null;
    can_eliminate: boolean;
    eliminate_confirm: string;
    user_id: string | null;
    can_remove: boolean;
    remove_confirm: string;
    registrant_id: string | null;
}
```

**`key` needs care.** A row can come from a registrant OR from a result with no
registration behind it — the results screen creates those. Neither id alone is
present on every row, so build a key that is: `'r'.$registrant->id` or
`'x'.$result->id`. A `v-for` with a duplicate or undefined key reorders rows
silently.

**`can_eliminate` and `can_remove` are not the same condition**, and the
Blade's comment says `! $result` is load-bearing on the second: without it an
eliminated player gets a points badge AND a remove button the controller then
refuses. `can_remove` is
`is_admin && ! result && ! published && registrant !== null`.

**The eliminate confirmation names the place and the points** — both fall out
of how many players are still in, and an administrator should see what they are
about to award. They are computed once in the controller (`$nextPlace`,
`$nextPlacePoints`), not per row.

**The publish-offer dialog** is flashed (`offer_publish`), shown once, and
declining posts nothing — `method="dialog"`. In Vue it is a `<dialog>` opened
from a watcher on the prop, with the decline button just closing it. It must
NOT reopen on a reload, which is what "flashed" buys.

**Two flashes are suppressed while the register dialog is reopening**, because
the dialog sits in the top layer with a backdrop and the page-level alerts
would be announcing to somebody who cannot see them. That condition survives
into Task 4; in this task, render the alerts unconditionally and add the
suppression when the dialog exists.

- [ ] **Step 1: Change the controller.** Keep `$isComplete` as a value — the
      comment records it being asked four times and costing two queries each.
      `TournamentShowQueryShapeTest` compares query counts between field sizes
      and will catch an N+1 introduced while mapping rows.

- [ ] **Step 2: Write the page**, rendering `<PEvent>` from Task 1 with
      `:details="false" :map="false"`.

- [ ] **Step 3: Register the page** as `'Poker/Tournaments/Show' => ['tournaments.show', 'tournament']`.

- [ ] **Step 4: Type-check, build, full suite; port what breaks**

- [ ] **Step 5: Prove each ported assertion fails**

- [ ] **Step 6: Verify in a browser at 1440, 960 and 375**

- eliminate a player: the confirmation names the place and the points, the row
  gains a place glyph and a points badge, and loses its controls
- the publish offer appears after the LAST elimination and not on reload
- publish: the header swaps to Unpublish, the field-locked note appears
- remove a still-in player: the confirmation explains what moves
- as a player: no admin controls at all

- [ ] **Step 7: Commit (hand-off)**

---

## Task 4: the register dialog

**Files:**
- Create: `resources/js/Pages/Poker/Tournaments/Partials/RegisterDialog.vue`
- Modify: `Poker/Tournaments/Show.vue`, `PokerTournamentController`
- Delete: `resources/views/poker/tournaments/show.blade.php`
- Test: `AdminRegisterDialogTest` (16), `MonogramTest`

**The Alpine version, and what each part becomes:**

| Alpine | Vue |
|---|---|
| `x-data="{ q: '', players: … }"` | `const q = ref('')`, the `candidates` prop |
| `get matches()` | a `computed` |
| `get shown()` | `matches.slice(0, 10)` |
| `x-ref="registerDialog"` + `showModal()` | a template ref and `showModal()` |
| `x-init` on `session('register_open')` | see below |
| `<template x-for>` / `x-if` | `v-for` / `v-if` |

**`matches` has two modes and both matter.** With no search term it excludes
anybody who cannot be picked — already registered, or awaiting approval —
because the ten slots were being spent on rows that did nothing. With a term it
returns everyone matching, pickable or not: that is when an administrator is
hunting one named person, and "already in" or "waiting for approval" is the
answer they came for. Three row states, three different `picker__meta` lines.

**"Stays open across registrations" changes.** In Blade each row posts a form,
the page reloads, and `register_open` reopens the dialog — a server fact.
With Inertia the visit is client-side and the component is REUSED, so the
dialog simply stays open on its own. Keep the flash prop anyway: it is what
tells the dialog to show its own `register__flash` line, which exists because
the page-level alert is behind the backdrop.

**`register_open` has exactly one producer and one consumer.** It is set at
`PokerTournamentController` line ~519, only when an administrator registers
somebody else — `$isAdmin && $request->has('user_id')` — which only this dialog
does. The dashboard and the event cards register the VIEWER and never carry
`user_id`, so they never set it. Once the dialog stays open on its own, the
`$reopen` closure has no reader left. Remove it in its own commit, after the
dialog is verified, rather than as part of this one.

**The monogram letters come from the payload**, computed by the same
`initials()` the component calls. `MonogramTest` holds that there is one
definition. Do not compute letters in TypeScript.

- [ ] **Step 1: Write the dialog component**

- [ ] **Step 2: Render it from `Show.vue`**, and add the two alert
      suppressions Task 3 deferred: while the dialog is open, the page-level
      status and error alerts are not drawn.

- [ ] **Step 3: Delete `poker/tournaments/show.blade.php`**

- [ ] **Step 4: Type-check, build, full suite; port what breaks**

- [ ] **Step 5: Prove each ported assertion fails**

- [ ] **Step 6: Verify in a browser at 1440 and 375**

- the dialog opens from the card header's + button, traps focus, closes on
  Escape
- with no term: only pickable players, capped at ten
- with a term: the already-registered and awaiting-approval rows appear, inert,
  each saying which gate is shut
- registering: the dialog STAYS OPEN, the flash line names who was registered,
  and the row that was picked becomes inert
- the note appears only when the cap actually hides somebody

- [ ] **Step 7: Commit (hand-off)**

---

## Task 5: close out the conversion

**Files:**
- Modify: `tests/Feature/VenueStatRowsTest.php`,
  `docs/superpowers/specs/2026-09-19-inertia-vue-conversion-design.md`,
  `docs/RESUME-HERE.md`

**`VenueStatRowsTest` is the last guard naming a `.blade.php` page view.**
Its `test_the_two_pages_use_the_same_component` pairs the venue page (already
Vue) with the season page; re-point the second.

**Then take stock, and write down what is actually true:**

- **`MarkupBalanceTest` and `AlpineAttributeGuardTest` stay.** Spec §6 predicted
  both would retire; they do not, because their targets are
  `components/{modal,topbar,dropdown}.blade.php` and the public pages that
  render them, none of which convert. Amend §6 rather than leaving a prediction
  that reads as a to-do.
- **Alpine stays**, for the same three components. `resources/js/app.ts` keeps
  importing it; `inertia-app.ts` never did.
- **`confirm.ts` stays** for the Blade half. The Vue half uses `useConfirm`.
- **`AppLayout` could take ownership of the flash** once the three controllers
  that flash sentinel strings are changed — `profile-updated` and friends. That
  is a separate piece of work and should be written down as one, not started
  here.

- [ ] **Step 1: Re-point `VenueStatRowsTest`** and run it.

- [ ] **Step 2: Amend spec §6** with what happened to the two guards, and §1
      with the final route count.

- [ ] **Step 3: Update `docs/RESUME-HERE.md`** — the suite figure, the
      conversion's state, and what remains Blade and why.

- [ ] **Step 4: Full suite, build, and a browser sweep of every converted page**

The sweep is worth its cost here: it is the last chance to catch a page that
renders but no longer mounts.

- [ ] **Step 5: Commit (hand-off)**

---

## What comes after this plan

**Nothing, for the conversion.** All 29 authenticated routes are Vue; the 18
public ones stay Blade by the boundary in spec §1.

**Two pieces of work this leaves behind**, both small and both optional:

1. **The flash in `AppLayout`.** Three controllers flash sentinel strings that
   pages match on rather than display. Change those three and the layout can
   own the alert, which removes a `computed` from every page that has one.
2. **The dashboard's silent actions.** Registering or unregistering from the
   dashboard shows no confirmation, and a refusal is equally silent — both
   end in `back()->with(...)` and neither the Blade page nor its replacement
   renders a flash. Recorded during Plan 5, unchanged, and still worth fixing.
