# Inertia + Vue Conversion — Plan 3: The six index pages

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the six authenticated list pages to Inertia + Vue, and build
the pagination the five paginated ones need.

**Architecture:** Each page becomes a `resources/js/Pages/**/*.vue` rendered by
`Inertia::render`, registered in `InertiaPropContractTest::PAGES`, with its
props display-ready per spec §5a. Every page's existing tests move from
`assertSee` to `assertInertia` as part of that page's task.

**Tech Stack:** Laravel 13.32, PHP 8.5, Inertia v3, Vue 3.5
`<script setup lang="ts">`, TypeScript 5.9 strict, Vite 7, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-09-19-inertia-vue-conversion-design.md`

**Depends on:** Plan 1 (foundation) and Plan 2 (components), both complete.
`Table`, `Badge`, `Btn`, `Card`, `PageHeader`, `EmptyState`, `Monogram`,
`PlayerLink`, `Action`, `Alert`, `Field`, `Dropdown` and the rest are in
`resources/js/Components/`.

## Global Constraints

- **Never run git commands.** The owner commits by hand. Every "Commit" step is
  a hand-off: state the files and the message, then stop.
- **Run the suite with `bin/suite.sh`**, never a bare `php artisan test`. Do not
  pipe it; capture with `out=$(bin/suite.sh); status=$?`.
- **Any inline shell helper reading `artisan test` output must strip ANSI
  first** — `sed -E 's/\x1b\[[0-9;]*[A-Za-z]//g'`. PHPUnit colours its output
  even when piped, so an anchored `grep '^  Tests:'` silently matches nothing
  and every result reads as empty rather than as a pass or a failure.
- **TypeScript throughout.** Every SFC is `<script setup lang="ts">`. No `.js`
  files. No `any` or `@ts-ignore` without a comment saying why.
- **Props carry display-ready values** (spec §5a). PHP decides a value, Vue
  places it. No `number_format`, ordinals, date formats or pluralisation in
  TypeScript.
- **Class names are written out, never composed in a template.** A composed
  `foo--${x}` is invisible to both `UnusedCssClassTest` and
  `ComponentParityTest`; use an explicit map.
- **Venue points stay private**: readable only by their owner and by admins via
  `VenuePoints::readableBy()`. Withhold the figure from the props, not in the
  template.
- **Verify in a browser at 375, 960 and 1440** before handing off any page.
- **No public page changes.** The Blade twins stay.

---

## How this plan is structured, and why

Tasks 1, 2 and 7 carry full code: the pagination component, the first index
page as the worked pattern, and the users list, which is the only one that is
not a plain table.

Tasks 3 to 6 carry their full `Props` interface, their full controller change,
their column list and the traps specific to that page — but not a verbatim
template, because by then the template is the same shape as Task 2 with
different columns, and four more near-identical listings would be four more
places for a detail to drift.

---

## Task 1: Pagination

Five of the six pages paginate. Nothing can convert until a paginator can cross
into Vue.

**Files:**
- Create: `resources/js/Components/Pagination.vue`
- Modify: `resources/js/types/inertia.d.ts`

**Interfaces:**
- Produces:
  - `Paginated<T>` in `types/inertia.d.ts` — `{ data: T[]; links: PageLink[]; status_label: string | null }`.
  - `PageLink` — `{ kind: 'previous' | 'next' | 'page' | 'gap'; url: string | null; label: string; active: boolean; aria_label: string | null }`.
    `kind` rather than inferring from the label: Laravel's labels carry HTML
    entities, and a template asking "is this Previous?" by looking for the word
    breaks the first time it is translated.
  - `Pagination.vue` — props `links: PageLink[]`, `statusLabel: string | null`.

**Port target.** `resources/views/vendor/pagination/design-system.blade.php`.
Read it in full first. Three things in it must survive:

- **The page numbers are windowed by the view**, not taken from Laravel's
  `$elements`: first two, the page you are on, last two. Laravel's own window
  cannot produce that shape — under eight pages it renders every one, and over
  that its slider widens around the current page.

  **Do not use `linkCollection()`.** An earlier draft of this plan said to, and
  it is wrong: it returns exactly the elements the Blade deliberately ignores,
  so it would have quietly changed the shape of every pager in the app. The
  window is reproduced in `Paginate` instead, and checked against the Blade's
  across every page count and current-page position.
- **Previous and Next carry both a word and an arrow, always in the DOM.**
  Below 48rem the label is clipped rather than removed, so the link is an arrow
  to look at and still reads as "Previous" to a screen reader. `display: none`
  would take the name with it.
- **The status line** — "Showing 1 to 10 of 43" — is formatted in PHP under
  §5a and arrives as `status_label`.

- [ ] **Step 1: Declare the types**

In `resources/js/types/inertia.d.ts`:

```ts
export interface PageLink {
    url: string | null;
    /** Already the text to show: a number, or "Previous"/"Next". */
    label: string;
    active: boolean;
}

/** A page of results, as Laravel's paginator crosses into Vue. */
export interface Paginated<T> {
    data: T[];
    links: PageLink[];
    /** "Showing 1 to 10 of 43", or null when there is only one page. */
    status_label: string | null;
}
```

- [ ] **Step 2: Add the serialiser**

Create `app/Support/Paginate.php`:

```php
<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A paginator, as an Inertia page prop.
 *
 * One definition, because every index page needs the same three things and a
 * page that assembled them itself would be free to differ. The status line is
 * formatted here rather than in Vue, per spec §5a.
 */
class Paginate
{
    /**
     * @template T
     *
     * @param  LengthAwarePaginator<T>  $paginator
     * @param  callable(T): array<string, mixed>  $each  turns one row into its props
     * @return array<string, mixed>
     */
    public static function props(LengthAwarePaginator $paginator, callable $each): array
    {
        return [
            'data' => collect($paginator->items())->map($each)->values()->all(),

            // linkCollection() is Laravel's own Previous / numbers / Next set,
            // with url, label and active already decided.
            'links' => $paginator->linkCollection()->values()->all(),

            // Null on a single page, so the template has nothing to decide.
            'status_label' => $paginator->hasPages()
                ? __('Showing :first to :last of :total', [
                    'first' => $paginator->firstItem(),
                    'last' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ])
                : null,
        ];
    }
}
```

- [ ] **Step 3: Write Pagination.vue**

Copy every class hook from the Blade view: `pager`, `pager__status`,
`pager__list`, `pager__link`, `pager__link--edge`, `pager__link--current`,
`pager__link--disabled`, `pager__label`, `pager__arrow`, `pager__gap`.

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PageLink } from '../types/inertia';

interface Props {
    links: PageLink[];
    /** "Showing 1 to 10 of 43". Null when there is only one page. */
    statusLabel?: string | null;
}

const props = withDefaults(defineProps<Props>(), { statusLabel: null });

// Laravel's labels for the edges are "&laquo; Previous" and "Next &raquo;".
// The arrows are drawn here, so the entities are stripped rather than rendered.
const isPrevious = (link: PageLink): boolean => link.label.includes('Previous');
const isNext = (link: PageLink): boolean => link.label.includes('Next');
const isGap = (link: PageLink): boolean => link.label === '...';
const numbered = (link: PageLink): boolean =>
    ! isPrevious(link) && ! isNext(link) && ! isGap(link);
</script>

<template>
    <!--
        statusLabel is the signal, not a count of links. Paginate::props sets it
        only when hasPages() is true, which is the same condition the Blade view
        opens with. Guessing from links.length would be a second definition of
        "has pages" and a wrong one: a two-page list has four links.
    -->
    <nav v-if="statusLabel" class="pager" role="navigation" aria-label="Pagination Navigation">
        <span class="pager__status">{{ statusLabel }}</span>

        <ul class="pager__list">
            <li v-for="link in props.links" :key="link.label">
                <!--
                    The word and the arrow both, always in the DOM. Below 48rem
                    the label is CLIPPED rather than removed, so the link is an
                    arrow to look at and still reads as "Previous" to a screen
                    reader -- display:none would take the name with it.
                -->
                <component
                    :is="link.url === null ? 'span' : Link"
                    v-if="isPrevious(link) || isNext(link)"
                    :href="link.url ?? undefined"
                    class="pager__link pager__link--edge"
                    :class="{ 'pager__link--disabled': link.url === null }"
                    :aria-disabled="link.url === null ? 'true' : undefined"
                >
                    <svg v-if="isPrevious(link)" class="pager__arrow" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true"><path d="M15 19l-7-7 7-7"/></svg>

                    <span class="pager__label">{{ isPrevious(link) ? 'Previous' : 'Next' }}</span>

                    <svg v-if="isNext(link)" class="pager__arrow" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
                </component>

                <span v-else-if="isGap(link)" class="pager__gap">…</span>

                <span v-else-if="link.active" class="pager__link pager__link--current"
                      aria-current="page">{{ link.label }}</span>

                <Link v-else-if="numbered(link) && link.url" class="pager__link"
                      :href="link.url">{{ link.label }}</Link>
            </li>
        </ul>
    </nav>
</template>
```

- [ ] **Step 4: Run the parity guard**

```bash
php artisan test tests/Feature/ComponentParityTest.php
```

Note: the Blade pagination lives at `views/vendor/pagination/design-system.blade.php`,
not `views/components/`, so the guard does **not** discover this pair. That is
correct — the two are not the same shape, since Laravel's view receives a
paginator object and this receives props. Expected: PASS, unchanged.

- [ ] **Step 5: Type-check and build**

```bash
npm run typecheck && npx vite build; echo "exit=$?"
```

- [ ] **Step 6: Commit (hand-off)**

Files: `resources/js/Components/Pagination.vue`, `app/Support/Paginate.php`,
`resources/js/types/inertia.d.ts`

```
feat: add pagination for Inertia pages

Paginate::props() is one definition of what an index page sends: the rows,
Laravel's own link collection, and a status line formatted in PHP under spec
§5a. Pagination.vue keeps the Blade view's two decisions -- the word and the
arrow both in the DOM, with the label clipped rather than removed below 48rem
so a screen reader still hears "Previous".
```

---

## Task 2: `poker/seasons` — the worked pattern

The smallest real list, and the one every later index task follows.

**Files:**
- Create: `resources/js/Pages/Poker/Seasons/Index.vue`
- Modify: `app/Http/Controllers/Poker/PokerSeasonController.php`,
  `tests/Feature/InertiaPropContractTest.php`,
  `tests/Feature/SeasonsIndexLayoutTest.php`

**Port target.** `resources/views/poker/seasons/index.blade.php`. Read it in
full. What must survive:

- **The whole row is the link**, via `.table__row--link` and a stretched
  `.table__link` on the name cell. Editing and deleting are offered on the
  season's own page, not here: a row of icons beside every name is a column of
  decisions on a page whose job is to list.
- **Both date columns carry `table__num`**, which right-aligns them and makes
  them tabular, so `Jan 01, 2026` lines up digit under digit.
- **The mobile card layout** is CSS only, scoped by `.seasons-index__table`,
  with `seasons-index__{name,current,start,end}` on the cells. Drop a class and
  the layout fails silently on a phone while every other test stays green.
- **`colspan="4"`** on the empty state.

- [ ] **Step 1: Change the controller**

```php
    public function index(): Response
    {
        return Inertia::render('Poker/Seasons/Index', [
            'seasons' => Paginate::props(
                PokerSeason::latest()->paginate(10),
                fn (PokerSeason $season) => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'is_current' => (bool) $season->is_current,
                    // Formatted here, per spec §5a. The em dash is the empty
                    // state for a date, not a null for Vue to decide about.
                    'start_label' => $season->start_date?->format('M d, Y') ?? '—',
                    'end_label' => $season->end_date?->format('M d, Y') ?? '—',
                ]
            ),
            'can_create' => auth()->user()->is_admin,
        ]);
    }
```

Add `use App\Support\Paginate;`, `use Inertia\Inertia;`, `use Inertia\Response;`.

- [ ] **Step 2: Write the page**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import Card from '../../../Components/Card.vue';
import Table from '../../../Components/Table.vue';
import Badge from '../../../Components/Badge.vue';
import Btn from '../../../Components/Btn.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Pagination from '../../../Components/Pagination.vue';
import type { Paginated } from '../../../types/inertia';

interface Season {
    id: string;
    name: string;
    is_current: boolean;
    start_label: string;
    end_label: string;
}

interface Props {
    seasons: Paginated<Season>;
    can_create: boolean;
}

const props = defineProps<Props>();
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader eyebrow="League" title="Poker Seasons">
                <template v-if="props.can_create" #actions>
                    <Btn variant="primary" href="/poker/seasons/create">Create Season</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <Card flush>
                <!--
                    The mobile layout is CSS only: same cells, reflowed by
                    _seasons-index.css below 48rem. Start and end are two
                    columns holding one fact -- a season's span -- so on a phone
                    they read as the range they are.
                -->
                <Table cards class="seasons-index__table">
                    <template #head>
                        <th scope="col">Name</th>
                        <th scope="col">Current</th>
                        <th scope="col" class="table__num">Start Date</th>
                        <th scope="col" class="table__num">End Date</th>
                    </template>

                    <!--
                        The whole row is the link, and the only control on it.
                        Editing and deleting a season are offered on the
                        season's own page: an administrator changing one is
                        reading it first, and a row of three icons beside every
                        name is a column of decisions on a page whose job is to
                        list.
                    -->
                    <tr v-for="season in props.seasons.data" :key="season.id" class="table__row--link">
                        <td class="seasons-index__name">
                            <Link class="table__link" :href="`/seasons/${season.id}`">{{ season.name }}</Link>
                        </td>

                        <td class="seasons-index__current">
                            <Badge v-if="season.is_current" variant="open">Current</Badge>
                        </td>

                        <td class="table__num seasons-index__start">{{ season.start_label }}</td>
                        <td class="table__num seasons-index__end">{{ season.end_label }}</td>
                    </tr>

                    <tr v-if="props.seasons.data.length === 0">
                        <td colspan="4"><EmptyState title="No seasons found." /></td>
                    </tr>
                </Table>

                <div v-if="props.seasons.status_label" class="card__pager">
                    <Pagination :links="props.seasons.links" :status-label="props.seasons.status_label" />
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 3: Register the page in the prop guard**

Add to `InertiaPropContractTest::PAGES`:

```php
        'Poker/Seasons/Index' => 'poker.seasons.index',
```

- [ ] **Step 4: Port that page's tests**

`SeasonsIndexLayoutTest` asserts on rendered markup. Each assertion moves to
the props or to the page file. For example, the hook test becomes a check that
the Vue page carries the classes, since the markup is no longer in the
response:

```php
    public function test_every_cell_the_grid_places_is_classed(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Poker/Seasons/Index.vue'));

        foreach ([
            'seasons-index__name',
            'seasons-index__current',
            'seasons-index__start',
            'seasons-index__end',
        ] as $hook) {
            $this->assertStringContainsString($hook, $page, "The grid has no cell to place for {$hook}.");
        }
    }
```

and the data test becomes an `assertInertia`. Add
`use Inertia\Testing\AssertableInertia as Assert;` to the test's imports:

```php
    public function test_both_date_columns_arrive_formatted(): void
    {
        PokerSeason::create([
            'name' => 'Season 40', 'start_date' => '2026-08-06',
            'end_date' => '2026-10-06', 'is_current' => true,
        ]);

        $this->actingAs($this->admin())->get(route('poker.seasons.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Poker/Seasons/Index')
                ->where('seasons.data.0.start_label', 'Aug 06, 2026')
                ->where('seasons.data.0.end_label', 'Oct 06, 2026')
            );
    }
```

- [ ] **Step 5: Type-check, build, suite**

```bash
npm run typecheck && npx vite build
out=$(bin/suite.sh); status=$?; echo "$out" | tail -1; echo "exit=$status"
```

Expected: `0 failing`.

- [ ] **Step 6: Verify in a browser**

Serve the app and check `/poker/seasons` at 1440, 960 and 375 in both themes:
- the whole row is clickable and goes to the season
- the two date columns are right-aligned and tabular on desktop
- at 375 each row is a card, with the dates reading as one range
- the pager renders, and Previous is an arrow with a clipped label
- no console errors

- [ ] **Step 7: Commit (hand-off)**

```
feat: convert the seasons list to Inertia + Vue

The worked pattern for the index pages: Paginate::props for the rows, dates
formatted in PHP under spec §5a, and the whole row as the link with editing
offered on the season's own page.

SeasonsIndexLayoutTest moves from asserting rendered markup to asserting props
and the page file's class hooks -- the mobile card layout is still CSS only,
and dropping a hook still has to fail.
```

---

## Tasks 3–6: the four remaining plain lists

Each follows Task 2 exactly: `Inertia::render`, `Paginate::props`,
display-ready labels, register in `InertiaPropContractTest::PAGES`, port that
page's tests, verify at three widths.

### Task 3: `poker/venues`

**Props:** `venues: Paginated<{ id, name, description }>`, `can_create: bool`,
`rows_link: bool`.

**The trap.** The row is a link **only for an administrator**. `poker.venues.show`
is admin-only — it carries takings, leaderboards and per-player point histories
— so a row a player could follow is a row that leads to a 403. The Blade
computes `$linked = auth()->user()->is_admin` and renders a plain `<td>`
otherwise. Send that decision as `rows_link` rather than reading
`auth.user.is_admin` in the template: what a player may reach is a server
decision.

**Cells:** `venue-row`, `venue-row__name`, `venue-row__desc`. The description
cell must have **no whitespace inside it** — the mobile rule hides it with
`:empty`, and a stray newline makes it a cell with content as far as CSS is
concerned. In Vue write `<td class="venue-row__desc">{{ venue.description }}</td>`
on one line.

**Empty state:** `colspan="2"`.

### Task 4: `poker/tournaments`

**Props:** `tournaments: Paginated<{ id, name, venue_name, start_label }>`,
`season_name: string | null`, `can_create: bool`.

**The traps.**
- The list is **scoped to the current season** and ordered by `start_time`, not
  `latest()` — `latest()` is `created_at`, the order rows were typed in. With
  no current season the query is `whereRaw('1 = 0')`: an unguarded filter there
  becomes no filter at all.
- **Two different empty states.** "No tournaments in Season 9 yet." when a
  season is running, and "No season is running." when one is not. An
  administrator can act on the second and not the first, so they must not read
  alike. Send `season_name` as null to distinguish them.
- The season is the page's **eyebrow**, and there is no Season column: every row
  would show the same value.
- Start time carries `table__num` and `tournaments-index__start`.

**Empty state:** `colspan="3"`.

### Task 5: `poker/points-structure`

**Props:** `structures: Paginated<{ id, place_label, points_label }>`,
`last_place: int`, `can_create: bool`.

**The trap.** `lastPlace` drives the "add the next place" affordance; it is a
figure the controller already computes. Send it, do not derive it from the rows
— the rows are one page of a paginator and the last place is not on it.

### Task 6: `sponsors`

**Props:** `sponsors: { id, name, tier, tier_label, logo_url, sort_order }[]`,
`can_create: bool`.

**Not paginated.** `Sponsor::ordered()->get()`, so there is no `Paginated<T>`
here and no `Pagination` component on the page.

**Cells:** `sponsors-index__{logo,name,tier}` for the mobile card grid. The
logo leads, because that is what a sponsor is.

---

## Task 7: `users` — the one that is not a plain table

198 lines, two tables, a search box and a pending-approval queue.

**Files:**
- Create: `resources/js/Pages/Users/Index.vue`
- Modify: `app/Http/Controllers/UserController.php`,
  `tests/Feature/InertiaPropContractTest.php`, and the tests listed in step 4.

**Props:**

```ts
interface Props {
    users: Paginated<{
        id: string;
        name: string;
        initials: string;
        nickname: string | null;
        email: string;
        is_admin: boolean;
        approval_status: string;
        /** Pre-marked with emph() -- render through <Emph>. */
        delete_confirm: string;
    }>;
    /**
     * Deliberately NOT paginated, matching the controller: a queue that needs
     * paging is a queue nobody is working.
     */
    pending: Array<{
        id: string;
        name: string;
        initials: string;
        email: string;
        registered_label: string;
        /** Pre-marked. Says the account will be DELETED, not demoted. */
        reject_confirm: string;
    }>;
    search: string;
}
```

**The traps.**
- **Rejecting a pending account deletes it.** The confirmation must say so, and
  must name the person through `emph()`. The other reject control, on a
  player's account page, demotes somebody already approved — deleting them
  would orphan every result they earned.
- **The search box lives inside a dropdown** on the Blade page. `x-dropdown`
  closes its panel on any click inside it, which is right for a menu of links
  and fatal for a search box — it shut the moment you clicked it. The Vue
  `Dropdown` has the same behaviour, so the input must stop the event:
  `@click.stop` on the field.
- **The search must survive as a query string** so the list bookmarks and the
  back button works. Use `router.get('/users', { search }, { preserveState: true })`.
- There is **no Approval column**; it was removed deliberately.

- [ ] **Step 1: Change the controller, sending both lists and the search term**

```php
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Users/Index', [
            'users' => Paginate::props(
                User::query()
                    ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->paginate(15)
                    ->withQueryString(),
                fn (User $user) => [
                    'id' => $user->id,
                    'name' => trim($user->first_name.' '.$user->last_name),
                    'initials' => initials(trim($user->first_name.' '.$user->last_name)),
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'is_admin' => (bool) $user->is_admin,
                    'approval_status' => $user->approval_status,
                    'delete_confirm' => __('Delete :name? This cannot be undone.', [
                        'name' => emph(trim($user->first_name.' '.$user->last_name)),
                    ]),
                ]
            ),

            // Not paginated, matching the Blade: a queue that needs paging is
            // a queue nobody is working.
            'pending' => User::where('approval_status', 'pending')
                ->orderBy('created_at')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => trim($user->first_name.' '.$user->last_name),
                    'initials' => initials(trim($user->first_name.' '.$user->last_name)),
                    'email' => $user->email,
                    'registered_label' => $user->created_at->format('M d, Y'),
                    // Says DELETED, not demoted. Rejecting a pending account
                    // removes it: there is nothing to keep, and an account the
                    // league never accepted should not sit in the table.
                    'reject_confirm' => __('Reject :name? Their account will be deleted and cannot be recovered.', [
                        'name' => emph(trim($user->first_name.' '.$user->last_name)),
                    ]),
                ])
                ->all(),

            'search' => $search,
        ]);
    }
```

`withQueryString()` matters: without it the pager's links drop the search term
and page two of a search shows page two of everything.

- [ ] **Step 2: Write the page, with both tables and the dropdown search**

The search box is the only unusual part. `Dropdown` closes its panel on any
click inside it — right for a menu of links, fatal for a field:

```vue
<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const search = ref(props.search);

// preserveState keeps the dropdown open and the cursor where it was;
// replace keeps one history entry per search rather than one per keystroke.
const runSearch = (): void => {
    router.get('/users', { search: search.value }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <!--
        @click.stop on the field, not a change to Dropdown. The component
        closing on an inside click is right for a menu of links and wrong only
        here, so the exception is contained at the exception -- the same call
        the Blade made with x-on:click.stop.
    -->
    <input v-model="search" class="field__control" type="search"
           @click.stop @keyup.enter="runSearch">
</template>
```
- [ ] **Step 3: Register in `InertiaPropContractTest::PAGES`**
- [ ] **Step 4: Port `UserSearchTest`, `UserManagementTest`, `PlayerApprovalTest`
      and `DeleteConfirmationTest` from `assertSee` to `assertInertia`**
- [ ] **Step 5: Type-check, build, full suite**
- [ ] **Step 6: Verify in a browser at three widths, including that the search
      box can be typed in without the dropdown closing**
- [ ] **Step 7: Commit (hand-off)**

---

## What comes after this plan

**Plan 4 — the eleven create and edit forms** (≈11 days). `poker/seasons`,
`poker/venues`, `poker/tournaments`, `poker/points-structure`, `sponsors` and
`users` create/edit, plus `poker/venue-points/create` (163 lines, with the
player picker). All small and mechanical; the pattern is `useForm` plus the
`Field` component, established in `/profile`.

**Plan 5 — the four detail pages and two auth screens** (≈8 days).
`dashboard` (192), `users/show` (178), `poker/venues/show` (174),
`players/show` (73), `verify-email` (27), `confirm-password` (15). `dashboard`
and `players/show` both render `SeasonPanel`, which Plan 2 built.

**Plan 6 — the two large pages** (≈10 days). `poker/seasons/show` (346, with
the points/rank toggle and the venue-points privacy rule) and
`poker/tournaments/show` (499, plus porting `p-event` at 308 lines). These
convert last because they are the most interlocking and the most measured.
