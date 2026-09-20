# Inertia + Vue Conversion — Plan 4: The create and edit forms

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the thirteen create and edit forms to Inertia + Vue.

**Architecture:** Each page becomes a `resources/js/Pages/**/*.vue` rendered by
`Inertia::render`, registered in `InertiaPropContractTest::PAGES`, built on
`useForm` and the `Field` component established by `/profile`. Validation
errors arrive as `form.errors`; old input is Inertia's job, not `old()`'s.

**Tech Stack:** Laravel 13.32, PHP 8.5, Inertia v3, Vue 3.5
`<script setup lang="ts">`, TypeScript 5.9 strict, Vite 7, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-09-19-inertia-vue-conversion-design.md`

**Depends on:** Plans 1–3, complete. `/profile` is the worked example for
`useForm`; `Field`, `Btn`, `Card`, `PageHeader`, `Alert`, `InputError` and
`Monogram` are in `resources/js/Components/`.

## Global Constraints

- **Never run git commands.** Every "Commit" step is a hand-off: state the
  files and the message, then stop.
- **Run the suite with `bin/suite.sh`.** Do not pipe it; capture with
  `out=$(bin/suite.sh); status=$?`.
- **Any inline shell helper reading `artisan test` output must strip ANSI
  first** — `sed -E 's/\x1b\[[0-9;]*[A-Za-z]//g'`. An anchored
  `grep '^  Tests:'` otherwise matches nothing and every result reads as empty.
- **TypeScript throughout.** `<script setup lang="ts">`, no `.js`, no `any`
  without a comment.
- **Props carry display-ready values** (spec §5a). No formatting in TypeScript.
- **Class names written out, never composed in a template.**
- **A "must not contain" assertion against a `.vue` or `.blade.php` file must
  strip comments first.** These files explain their own rules in prose, and
  three assertions in Plan 3 tripped on a comment describing the very thing
  they forbade.
- **Assert membership, not position.** The user list orders by first name and a
  factory admin's is random; `data.0` is a coin toss that
  `DeleteConfirmationTest` records losing about once in fifty runs.
- **Import `Inertia\Testing\AssertableInertia as Assert`** in any test using
  it. Without the import PHP resolves `Assert` to the test's own namespace and
  the failure is a `TypeError`, not a missing-class error.
- **No `->component()` assertions.** Inertia's helper verifies the page file
  through a view finder reading `config('inertia.testing.*')`, which the
  published config does not define — and publishing that config turns
  `ssr.enabled` on, which this app deliberately does not use. Assert the
  component name off `viewData('page')['component']` instead.
- **Verify in a browser at 375, 960 and 1440** before handing off.
- **No public page changes.** `auth/` stays Blade.

---

## What `old()` becomes, and why it matters

Every one of these forms is written with `old('field', $model->field)`. That
pairing does two jobs: seed the form from the record, and re-fill it from the
session after a failed validation round trip.

Inertia does the second job differently. `useForm` holds the values client-side
and a failed submission returns errors **without re-rendering the page**, so the
form still has what the person typed — there is nothing to restore. So
`old(...)` disappears entirely and the controller sends the record's values
once.

**The trap:** a controller that keeps `old()` in its props would restore stale
session input over what is on screen. Every task below sends plain model values.

---

## File Structure

**Created**, under `resources/js/Pages/`:

| File | Responsibility |
|---|---|
| `Poker/Seasons/{Create,Edit}.vue` | Name, dates, current flag, three finale thresholds. |
| `Poker/Venues/{Create,Edit}.vue` | Name, address, description. |
| `Poker/Tournaments/{Create,Edit}.vue` | Name, venue, start time; season is fixed on create. |
| `Poker/PointsStructure/{Create,Edit}.vue` | One points field; the place is assigned, not typed. |
| `Poker/VenuePoints/Create.vue` | Venue, date, amount, and the player picker. |
| `Sponsors/{Create,Edit}.vue` + `Sponsors/Partials/SponsorForm.vue` | Shared form, including the logo upload. |
| `Users/{Create,Edit}.vue` | Name, email, nickname, admin flag. |

**Modified:** the matching controllers, and
`tests/Feature/InertiaPropContractTest.php`.

---

## Task 1: Venues create and edit — the worked pattern

The smallest pair (27 and 28 lines), and the shape every later form follows.

**Files:**
- Create: `resources/js/Pages/Poker/Venues/Create.vue`, `.../Edit.vue`
- Modify: `app/Http/Controllers/Poker/VenueController.php`,
  `tests/Feature/InertiaPropContractTest.php`

**Interfaces:**
- Produces: `Poker/Venues/Create` — no props beyond shared.
  `Poker/Venues/Edit` — `venue: { id, name, address, description }`.

- [x] **Step 1: Change the controller**

```php
    public function create(): Response
    {
        return Inertia::render('Poker/Venues/Create');
    }

    public function edit(Venue $venue): Response
    {
        // The record's values, once. No old(): useForm keeps what was typed,
        // and a failed submission returns errors without re-rendering, so
        // there is nothing to restore -- and re-seeding from the session would
        // overwrite what is on screen.
        return Inertia::render('Poker/Venues/Edit', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'address' => $venue->address,
                'description' => $venue->description,
            ],
        ]);
    }
```

- [x] **Step 2: Write Create.vue**

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Field from '../../../Components/Field.vue';
import Btn from '../../../Components/Btn.vue';

const form = useForm({
    name: '',
    address: '',
    description: '',
});

const submit = (): void => { form.post('/poker/venues'); };
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" title="Add Venue" />
        </template>

        <div class="l-container">
            <Card>
                <form class="l-stack" @submit.prevent="submit">
                    <Field v-model="form.name" name="name" label="Name"
                           required autofocus :error="form.errors.name" />

                    <Field v-model="form.address" name="address" label="Physical Address"
                           hint="Optional: Providing an address will enable a Google Maps preview."
                           :error="form.errors.address" />

                    <Field v-model="form.description" name="description" label="Description"
                           :error="form.errors.description" />

                    <div class="l-cluster l-cluster--end">
                        <Btn variant="ghost" href="/poker/venues">Cancel</Btn>
                        <Btn variant="primary" :disabled="form.processing">Save</Btn>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
```

The labels above are copied from `resources/views/poker/venues/create.blade.php`:
"Name", "Physical Address" with its Google Maps hint, "Description", and a
button reading "Save". Read that file first and check them again — an earlier
draft of this plan invented "Venue Name", "Address" and "Save Venue", none of
which appear anywhere in the app. Address is NOT required there either.

- [x] **Step 3: Write Edit.vue**

Same shape, seeded from the prop and submitting with `patch`:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
// ...same imports as Create.vue

interface Props {
    venue: {
        id: string;
        name: string;
        address: string;
        description: string | null;
    };
}

const props = defineProps<Props>();

const form = useForm({
    name: props.venue.name,
    address: props.venue.address,
    description: props.venue.description ?? '',
});

const submit = (): void => { form.patch(`/poker/venues/${props.venue.id}`); };
</script>
```

- [x] **Step 4: Teach the prop guard about parameterised routes**

`InertiaPropContractTest` calls `route($routeName)` with no arguments, which
works for every page converted so far because all six take no parameters. An
edit route does, and `route('poker.venues.edit')` throws rather than failing an
assertion.

So the list gains a fixture per parameterised page. Change `PAGES` to carry a
route name and an optional binding factory:

```php
    /**
     * Inertia component => [route name, a closure making its route parameter].
     *
     * The closure rather than a fixed id: RefreshDatabase gives each test an
     * empty database, so the record has to be made inside the test that needs
     * it. A page with no parameter passes null.
     */
    private const PAGES = [
        'Profile/Edit' => ['profile.edit', null],
        'Poker/Seasons/Index' => ['poker.seasons.index', null],
        'Poker/Venues/Index' => ['poker.venues.index', null],
        'Poker/Tournaments/Index' => ['poker.tournaments.index', null],
        'Poker/PointsStructure/Index' => ['poker.points-structure.index', null],
        'Sponsors/Index' => ['sponsors.index', null],
        'Users/Index' => ['users.index', null],
        'Poker/Venues/Create' => ['poker.venues.create', null],
        'Poker/Venues/Edit' => ['poker.venues.edit', 'venue'],
    ];
```

and resolve the parameter in the loop:

```php
            [$routeName, $binding] = $page;

            $url = $binding === null
                ? route($routeName)
                : route($routeName, [$binding => $this->fixture($binding)]);
```

with one fixture maker:

```php
    /** A record for a parameterised page to be rendered against. */
    private function fixture(string $binding): string
    {
        return match ($binding) {
            'venue' => Venue::create(['name' => 'Fixture Room', 'address' => '1 Test St'])->id,
        };
    }
```

Each later task adds its own arm to that match. An unhandled binding throws
`\UnhandledMatchError`, which names the missing arm — better than a null id
producing a 404 that reads as a routing problem.

- [x] **Step 5: Type-check, build, full suite**

```bash
npm run typecheck && npx vite build
out=$(bin/suite.sh); status=$?; echo "$out" | tail -1; echo "exit=$status"
```

Expect failures in any test asserting the old markup; port each to props or to
the page file as Plan 3 did.

- [x] **Step 6: Verify in a browser at 1440, 960 and 375**

- the form saves and redirects
- a validation failure shows the message under the right field, and **what was
  typed is still there** — this is the behaviour `old()` used to provide
- Cancel returns to the list

- [x] **Step 7: Commit (hand-off)**

```
feat: convert the venue forms to Inertia + Vue

The worked pattern for the forms: useForm, the Field component, and the
record's values sent once. old() is gone -- useForm keeps what was typed and a
failed submission returns errors without re-rendering, so there is nothing to
restore and re-seeding from the session would overwrite what is on screen.
```

---

## Tasks 2–6: the forms that follow the pattern

Each is Task 1's shape with its own fields. Read the Blade first and copy its
labels, hints and button text verbatim — tests assert on them.

### Task 2: `poker/seasons` create and edit — DONE

**Props:** create — none beyond shared. edit —
`season: { id, name, description, start_date, end_date, is_current, finale_points_required, finale_wins_required, finale_venue_points_required }`.

**Both pages have the same fields**, `is_current` included — this plan said
twice, in both directions, that they did not. Read
`resources/views/poker/seasons/{create,edit}.blade.php`: name, description, the
two dates, the three finale thresholds, and an `is_current` checkbox, on each.

**`is_current` on Create is not what it looks like.** The checkbox is only
POSTed when ticked, and `PokerSeason::booted()` defaults an unset `is_current`
to **true** — so creating a season with the box clear makes it current anyway.
`useForm` sends every field every time, which would silently turn that into
"clear means not current". Preserve the behaviour: `form.transform()` drops
`is_current` when it is false, exactly as an unticked checkbox does. The
oddity is pre-existing and is not this plan's to fix.

**Dates** are `<input type="date">`, so they need `Y-m-d` strings:
`$season->start_date?->format('Y-m-d')`. A Carbon instance serialises to ISO
8601 and the input silently shows nothing.

**`is_current`, on Edit only, is a checkbox.** Seed it as a real boolean, not
`0`/`1` — Vue's `v-model` on a checkbox needs a boolean or it renders unchecked
and posts the wrong value.

**The three finale thresholds are nullable.** Null means "no requirement", which
is not the same as zero. Send `null` and let the number input be empty.

### Task 3: `poker/tournaments` create and edit — DONE

**Props:** create — `venues: { id, name }[]`, `season_label: string`.
edit — `tournament: { id, name, description, venue_id, season_id, start_time }`,
`venues`, `seasons`.

`season_label`, not `current_season_name`: the box shows the season's name or
the words "No active season", and choosing between them is a translated string
decided in PHP (spec §5a). The create view's `$seasons` goes away — it was
fetched and never read.

**The season is not chosen on create.** It is fixed to the current season and
shown as static text in a `.field__control--static` box — styled to look almost
like an input but not editable. Send `current_season_name` and render it
read-only. If no season is running the controller already refuses with an
error; keep that.

**`start_time` is `datetime-local`**, so it needs `Y-m-d\TH:i`.

### Task 4: `poker/points-structure` create and edit — DONE

**Props:** create — `next_place_label: string` ("3rd"). edit —
`structure: { id, place_label, points }`.

**The place is assigned, never typed.** The create page shows which place it
will add — "3rd" — and posts no `place` field at all;
`PointsStructureAdminTest` asserts both (`assertSee('3rd')` and
`assertDontSee('name="place"')`). Port the second to a check that the page file
has no `name="place"` input, stripping comments first.

**The form returns to itself, and two things Blade gave it for free must be
done by hand.** `old()` is only refilled after a FAILED submission, so the
points box came back empty; `useForm` keeps what was typed, so the success
handler calls `reset()` or the same figure sits there ready to be saved twice.
And `autofocus` fires when an input enters the document — once. What comes back
from a save is the SAME component re-rendered, so the component focuses the
input itself.

### Task 5: `users` create and edit

**Props:** create — none beyond shared. edit —
`user: { id, first_name, last_name, nickname, email, is_admin }`.

**Creating a player returns an invite URL**, flashed as `invite_url` and
rendered by the users index — already handled in Plan 3. Nothing to do here
beyond not breaking it.

### Task 6: `sponsors` create and edit

**Props:** create — none. edit —
`sponsor: { id, name, website_url, tier, sort_order, logo_url }`.

**The shared partial becomes a shared component**:
`resources/js/Pages/Sponsors/Partials/SponsorForm.vue`, taking
`sponsor: {...} | null`. Both pages render it, as both Blade pages `@include`
`sponsors._form`.

**The logo is a file upload, and this is the one real trap in the task.** The
Blade carries a comment calling `enctype` "load-bearing": without it the browser
sends the field names but no file, validation reports a missing logo, and the
form gives no clue why.

Inertia's equivalent is that `useForm` must be given a `File`, and the visit
must be a `POST`. Two consequences:

- **An edit cannot use `form.patch()`.** PHP does not parse a multipart body on
  PUT or PATCH, so the file never arrives. Post to the update route with
  `_method: 'patch'` in the form data — the same method-spoofing the Blade does
  with `@method('patch')`.
- The file input is not `v-model`-able. Bind it by hand:

```vue
<input type="file" class="field__control" accept="image/*"
       @input="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null">
```

**On edit the logo is optional** — "Leave this empty to keep the current logo."
Send `null` and let the controller keep what it has.

---

## Task 7: `poker/venue-points/create` — the player picker

163 lines, and the only form with real client state. Convert it last.

**Files:**
- Create: `resources/js/Pages/Poker/VenuePoints/Create.vue`
- Modify: `app/Http/Controllers/Poker/VenuePointsController.php`,
  `tests/Feature/InertiaPropContractTest.php`

**Props:**

```ts
interface Props {
    venues: Array<{ id: string; name: string }>;
    /** Pre-selected when arriving from a venue's page. */
    venue_id: string | null;
    players: Array<{
        id: string;
        name: string;
        /** Lowercased name, nickname and email in one string. */
        search: string;
    }>;
}
```

**What the Alpine version does**, and must keep doing:

- `x-data="{ q: '', chosen: null }"` — a search term and the chosen player.
  Both become `ref`s.
- Once chosen, **the list gives way to the name** with a Change button, rather
  than staying open beneath it.
- Two hidden inputs carry `user_id` and `user_name`. In Vue these are form
  fields on the `useForm` object, not hidden inputs.
- **`user_name` is sent as well as `user_id`** deliberately: venue points
  snapshot the name, because `user_id` is nullable with `nullOnDelete` and a
  departed player must leave a readable row behind.
- The searchable haystack is **built in PHP** — one lowercase string per player
  covering name, nickname and email — because doing it in the template would
  repeat four fields per row. Keep that: it is spec §5a.

**The filter is the only computation in the template**, and it is a
`String.includes` against the prepared haystack:

```ts
const matches = computed(() => {
    const needle = q.value.trim().toLowerCase();

    return needle === ''
        ? props.players
        : props.players.filter((player) => player.search.includes(needle));
});
```

- [ ] **Step 1: Change the controller to send the three props**
- [ ] **Step 2: Write the page, with `q` and `chosen` as refs**
- [ ] **Step 3: Register in `InertiaPropContractTest::PAGES`**
- [ ] **Step 4: Port the tests that assert on the picker's markup**
- [ ] **Step 5: Type-check, build, full suite**
- [ ] **Step 6: Verify in a browser**, including that choosing a player
      replaces the list with the name, Change reopens it, and the points save
      against the right person
- [ ] **Step 7: Commit (hand-off)**

---

## What comes after this plan

**Plan 5 — four detail pages and two auth screens** (≈8 days): `dashboard`
(192), `users/show` (178), `poker/venues/show` (174), `players/show` (73),
`verify-email` (27), `confirm-password` (15). `dashboard` and `players/show`
both render `SeasonPanel`, built in Plan 2.

**Plan 6 — the two large pages** (≈10 days): `poker/seasons/show` (346, with
the points/rank toggle and the venue-points privacy rule) and
`poker/tournaments/show` (499, plus porting `p-event` at 308 lines).

**Then the guard re-homing and Alpine's removal**, which the spec's §6 and §7
describe: `MarkupBalanceTest` and `AlpineAttributeGuardTest` retire, twelve
guards re-point at `.vue`, and `AppLayout` takes ownership of flash once the
sentinel-flashing controllers can be changed.
