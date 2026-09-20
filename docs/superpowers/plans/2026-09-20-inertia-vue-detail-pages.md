# Inertia + Vue Conversion — Plan 5: The detail pages and two auth screens

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the four detail pages and the two authenticated auth screens
to Inertia + Vue, leaving only `poker/seasons/show` and `poker/tournaments/show`
in Blade.

**Architecture:** Each controller method swaps `view()` for `Inertia::render()`
and sends display-ready props. Two pages — the dashboard and a player's profile
— render the same `SeasonPanel.vue`, built in Plan 2 and not yet used by
anything; the props it needs are built once in a new `App\Support\SeasonFigures`
rather than twice in two controllers. The two auth screens need a
`GuestLayout.vue`, which does not exist yet.

**Tech Stack:** Laravel 13.32, Inertia 3, Vue 3.5 `<script setup lang="ts">`,
Vite 7, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-09-19-inertia-vue-conversion-design.md`

## Global Constraints

- **Props carry display-ready values (spec §5a).** PHP decides, Vue places. No
  `number_format`, ordinals, date formats, pluralisation or `__()` in
  TypeScript. Every one of these six pages formats something.
- **Semantic props, never stylistic.** `tier: "gold"`, never
  `tier: "notification--gold"`.
- **A player's venue points are readable only by that player and by admins**,
  everywhere in the app. The figure is removed from the PROPS by the
  controller, never hidden in the template. `VenuePoints::readableBy()` is the
  one definition. This is the owner's hard rule and Task 1 is where it lives
  for these pages.
- **TypeScript throughout.** `npm run typecheck` must pass; no `.js`.
- **The flash is read through a computed**, never captured in a const:
  `const flash = computed(() => usePage<PageProps>().props.flash)`. Inertia
  reuses a component when a visit renders the same page, so a value read once
  at setup goes stale. `FlashMessageParityTest` enforces both halves of this.
- **Write class names out in full.** A composed name like
  `` `rank rank--${place}` `` is invisible to `UnusedCssClassTest` and to
  `ComponentParityTest`, and four notification tier classes went missing that
  way.
- **Delete the Blade view in the same commit as the page that replaces it.**
  A converted view left on disk keeps the classes its replacement uses looking
  used, and `UnusedCssClassTest` then says nothing about dead CSS.
- **Never run git commands.** Every "commit" step is a hand-off: report what
  changed and stop.

---

## What `figuresFor()` becomes, and why it needs its own task

`PokerSeason::figuresFor()` returns raw numbers:

```php
['events' => 26, 'points' => 218, 'venuePoints' => 40, 'first' => 2,
 'second' => 1, 'third' => 3, 'rank' => 4, 'ranked' => 41, 'perEvent' => 8.38…]
```

`SeasonPanel.vue` takes labels:

```ts
{ rank_label: '#4', per_event_label: '8.4 pts per event',
  totals_label: '218 pts over 26 events', ranked_label: 'of 41 ranked players',
  first: '2', second: '1', third: '3',
  events_label: '26', points_label: '218', venue_points_label: '40' }
```

Everything between those two shapes is a decision PHP has to make: one decimal
place, thousands separators, two `trans_choice()` calls, and the `—` an
unranked player gets. Two controllers need it. **One definition, in
`App\Support\SeasonFigures`** — this project has `PokerSeason::current()`,
`VenuePoints::readableBy()` and `initials()` because a second definition drifted
or was about to.

**The trap.** `figuresFor()` always returns `venuePoints` as an integer. A null
there means *the viewer may not read them*, and the panel omits the row
entirely. So the withholding happens in `SeasonFigures::props()` — given the
viewer and the subject — rather than being left to each caller to remember.
`PlayerController` currently does it inline; that line moves into the support
class and the controller stops deciding.

---

## File Structure

**Created:**

| File | Responsibility |
|---|---|
| `app/Support/SeasonFigures.php` | `figuresFor()` output → `SeasonPanel.vue` props, with the venue-points rule applied. |
| `resources/js/Layouts/GuestLayout.vue` | The `.guest` panel and brand, for Inertia pages with no navigation. |
| `resources/js/Pages/Players/Show.vue` | A player's profile: the panel and five career results. |
| `resources/js/Pages/Dashboard.vue` | The panel, the next five tournaments with their actions, and five career results. |
| `resources/js/Pages/Users/Show.vue` | Personal information, account details, and the four admin actions. |
| `resources/js/Pages/Poker/Venues/Show.vue` | Map, two figures, the venue-points leaderboard, recent tournaments. |
| `resources/js/Pages/Auth/VerifyEmail.vue` | Resend and log out. |
| `resources/js/Pages/Auth/ConfirmPassword.vue` | One password field. |
| `tests/Feature/SeasonFiguresTest.php` | The support class, including the privacy rule. |

**Modified:** `DashboardController`, `PlayerController`, `UserController`,
`Poker/VenueController`, `Auth/EmailVerificationPromptController`,
`Auth/ConfirmablePasswordController`, `tests/Feature/InertiaPropContractTest.php`,
and the tests each task names.

**Deleted:** `dashboard.blade.php`, `players/show.blade.php`,
`users/show.blade.php`, `poker/venues/show.blade.php`,
`auth/verify-email.blade.php`, `auth/confirm-password.blade.php` — each with
the task that replaces it.

---

## Task 1: `App\Support\SeasonFigures`

The shared piece, first, because two pages depend on it and the venue-points
rule lives in it. No page converts in this task.

**Files:**
- Create: `app/Support/SeasonFigures.php`
- Create: `tests/Feature/SeasonFiguresTest.php`

**Interfaces:**
- Consumes: `PokerSeason::figuresFor(?PokerSeason, string): array`,
  `PokerSeason::current(): ?PokerSeason`,
  `VenuePoints::readableBy(?User, string): bool`.
- Produces: `SeasonFigures::props(?PokerSeason $currentSeason, string $subjectId, ?User $viewer, bool $self): array`
  returning `['season_name' => string, 'season_href' => ?string, 'season' => ?array, 'self' => bool]`
  — the four props `SeasonPanel.vue` declares.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SeasonFiguresTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use App\Support\SeasonFigures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * The season panel's props, built once.
 *
 * The panel is rendered by the dashboard and by a player's profile, about
 * different people. Its props are labels rather than numbers -- one decimal,
 * thousands separators, two pluralisations and the dash an unranked player
 * gets -- and building them in two controllers would be two definitions of one
 * thing, which is how PokerSeason::current() and initials() came to exist.
 */
class SeasonFiguresTest extends TestCase
{
    // enter() and score() from the concern, because registrants and results
    // both carry a player_name and a registered_at that are easy to forget and
    // fail as a database error rather than as an assertion. Its tournament()
    // is NOT used: it firstOrCreate()s a season of its own, and these tests
    // need the season they made.
    use BuildsTournaments, RefreshDatabase;

    private function season(): PokerSeason
    {
        return PokerSeason::create([
            'name' => 'Season 12',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);
    }

    public function test_with_no_season_the_panel_is_told_there_is_none(): void
    {
        $player = User::factory()->create();

        $props = SeasonFigures::props(null, $player->id, $player, self: true);

        // Null season, not a zeroed one: the panel draws its empty state from
        // this, and a row of zeros would be a claim about the player.
        $this->assertNull($props['season']);
        $this->assertNull($props['season_href']);
        $this->assertSame('Current Season', $props['season_name']);
    }

    public function test_an_unentered_player_has_no_average_and_no_rank(): void
    {
        $season = $this->season();
        $player = User::factory()->create();

        $props = SeasonFigures::props($season, $player->id, $player, self: true);

        $this->assertSame('Season 12', $props['season_name']);
        $this->assertSame(route('seasons.show', $season), $props['season_href']);
        $this->assertSame('—', $props['season']['rank_label']);
        $this->assertNull($props['season']['per_event_label'],
            'No events means no average; 0.0 reads as a bad one.');
        $this->assertNull($props['season']['ranked_label']);
    }

    public function test_the_labels_are_finished_sentences(): void
    {
        $season = $this->season();
        $player = User::factory()->create();

        $this->scored($season, $player, points: 218, events: 26, rank: true);

        $figures = SeasonFigures::props($season, $player->id, $player, self: true)['season'];

        // One decimal. These run to a couple of hundred points over a couple
        // of dozen events, so the second decimal is noise that changes nightly.
        $this->assertSame('8.4 pts per event', $figures['per_event_label']);
        $this->assertStringContainsString('218 pts over 26 events', $figures['totals_label']);
    }

    public function test_one_event_is_said_in_the_singular(): void
    {
        $season = $this->season();
        $player = User::factory()->create();

        $this->scored($season, $player, points: 10, events: 1, rank: true);

        $figures = SeasonFigures::props($season, $player->id, $player, self: true)['season'];

        $this->assertStringContainsString('over 1 event', $figures['totals_label']);
        $this->assertStringNotContainsString('1 events', $figures['totals_label']);
        $this->assertSame('of 1 ranked player', $figures['ranked_label']);
    }

    public function test_a_stranger_is_not_told_the_venue_points(): void
    {
        // The owner's rule, for the whole app: a player's venue tally is read
        // by that player and by admins, nobody else. Withheld from the PROPS,
        // because a gate in a template stops today's markup and not tomorrow's.
        $season = $this->season();
        $player = User::factory()->create();
        $stranger = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->scored($season, $player, points: 10, events: 1, rank: true);

        $this->assertNull(
            SeasonFigures::props($season, $player->id, $stranger, self: false)['season']['venue_points_label'],
            'A stranger must not be sent another player\'s venue points.'
        );

        $this->assertNotNull(
            SeasonFigures::props($season, $player->id, $player, self: true)['season']['venue_points_label']
        );

        $this->assertNotNull(
            SeasonFigures::props($season, $player->id, $admin, self: false)['season']['venue_points_label']
        );
    }

    /** A player with results and registrations in a season. */
    private function scored(PokerSeason $season, User $player, int $points, int $events, bool $rank): void
    {
        $venue = Venue::create(['name' => 'Fixture Room']);

        for ($i = 0; $i < $events; $i++) {
            $tournament = PokerTournament::create([
                'name' => 'Night '.$i,
                'start_time' => $season->start_date->copy()->addDays($i),
                'season_id' => $season->id,
                'venue_id' => $venue->id,
            ]);

            $this->enter($tournament, $player);

            // The whole total on the first night, so the arithmetic in the
            // labels is a figure this test chose rather than one it derived.
            $this->score($tournament, $player, place: $i === 0 ? 1 : 9, points: $i === 0 ? $points : 0);
        }

        VenuePoints::create([
            'venue_id' => $venue->id,
            'user_id' => $player->id,
            'user_name' => trim($player->first_name.' '.$player->last_name),
            'event_date' => $season->start_date->copy()->addDay(),
            'season_id' => $season->id,
            'amount' => 40,
        ]);
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

```bash
vendor/bin/phpunit tests/Feature/SeasonFiguresTest.php 2>&1 | sed -E 's/\x1b\[[0-9;]*[A-Za-z]//g' | tail -20
```

Expected: `Error: Class "App\Support\SeasonFigures" not found`.

Every fixture column above is checked against the models:
`VenuePoints::$fillable` is `event_date, amount, user_id, user_name, venue_id,
season_id`, and `season_id` is STORED rather than inferred — the season used to
be whichever one's dates contained `event_date`, so editing those dates moved
venue points between seasons and changed who qualified for the finale.

`VenuePoints::readableBy(?User $viewer, ?string $ownerId)` takes a nullable
viewer and returns false for a guest, so `SeasonFigures` can hand it
`auth()->user()` without checking first.

- [ ] **Step 3: Write the class**

`app/Support/SeasonFigures.php`:

```php
<?php

namespace App\Support;

use App\Models\PokerSeason;
use App\Models\User;
use App\Models\VenuePoints;

/**
 * One season's figures for one player, as the props SeasonPanel.vue declares.
 *
 * The panel is drawn by the dashboard and by a player's profile, about
 * different people. Everything here is a decision rather than a number -- one
 * decimal place, thousands separators, two pluralisations, and the dash an
 * unranked player gets -- and spec §5a puts those in PHP. Built once, because
 * two copies of this would be free to disagree about what "8.4" means.
 */
class SeasonFigures
{
    /**
     * @return array<string, mixed>
     */
    public static function props(
        ?PokerSeason $currentSeason,
        string $subjectId,
        ?User $viewer,
        bool $self,
    ): array {
        return [
            // The season IS the title. It was a badge beside the button, under
            // a heading reading "Current Season" -- true of every season, and
            // so of no help in telling them apart.
            'season_name' => $currentSeason?->name ?? __('Current Season'),
            'season_href' => $currentSeason ? route('seasons.show', $currentSeason) : null,
            'season' => $currentSeason
                ? self::figures($currentSeason, $subjectId, $viewer)
                : null,
            'self' => $self,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function figures(PokerSeason $season, string $subjectId, ?User $viewer): array
    {
        $figures = PokerSeason::figuresFor($season, $subjectId);

        // The owner's rule, applied here so that no page has to remember it: a
        // player's venue tally is read by that player and by admins, nobody
        // else. Null means WITHHELD, and the panel leaves the row out
        // altogether -- a zero would be a fact about the player, and this is a
        // fact about the reader.
        $mayReadVenuePoints = VenuePoints::readableBy($viewer, $subjectId);

        return [
            'rank_label' => $figures['rank'] ? '#'.$figures['rank'] : '—',

            // Null rather than "0.0 pts per event": there is no average of no
            // events, and a zero reads as a bad one.
            'per_event_label' => $figures['perEvent'] === null
                ? null
                : number_format($figures['perEvent'], 1).' '.__('pts per event'),

            'totals_label' => trans_choice(
                '{1}:points pts over 1 event|[2,*]:points pts over :events events',
                $figures['events'],
                [
                    'points' => number_format($figures['points']),
                    'events' => number_format($figures['events']),
                ]
            ),

            'ranked_label' => $figures['rank']
                ? trans_choice('{1}of 1 ranked player|[2,*]of :count ranked players', $figures['ranked'])
                : null,

            // Small counts, printed as they are: the Blade twin does not
            // separate thousands here, and a player with a thousand wins is
            // not the case to design for.
            'first' => (string) $figures['first'],
            'second' => (string) $figures['second'],
            'third' => (string) $figures['third'],

            'events_label' => number_format($figures['events']),
            'points_label' => number_format($figures['points']),
            'venue_points_label' => $mayReadVenuePoints
                ? number_format($figures['venuePoints'])
                : null,
        ];
    }
}
```

- [ ] **Step 4: Run it green**

```bash
vendor/bin/phpunit tests/Feature/SeasonFiguresTest.php 2>&1 | sed -E 's/\x1b\[[0-9;]*[A-Za-z]//g' | tail -8
```

- [ ] **Step 5: Prove the privacy assertion fails when the rule is broken**

Temporarily replace `VenuePoints::readableBy($viewer, $subjectId)` with `true`
and run the test again. Expect
`test_a_stranger_is_not_told_the_venue_points` to fail. Put it back. A guard
that has not been seen to fail is not yet a guard, and this is the one the
owner asked for by name.

- [ ] **Step 6: Full suite, then commit (hand-off)**

```bash
out=$(bin/suite.sh); echo "$out" | grep -E "FAIL|crash|ran "
```

```
feat: build the season panel's props in one place

SeasonPanel.vue takes labels and figuresFor() returns numbers, and everything
between the two is a decision: one decimal, thousands separators, two
pluralisations and the dash an unranked player gets. Two pages draw the panel,
so the props are built once.

The venue-points rule lives here rather than in either controller. Null means
withheld and the panel leaves the row out; a zero would be a fact about the
player, and this is a fact about the reader.
```

---

## Task 2: `/players/{player}`

The smallest consumer of the panel, so the panel is proven before the dashboard
leans on it too.

**Files:**
- Create: `resources/js/Pages/Players/Show.vue`
- Modify: `app/Http/Controllers/PlayerController.php`, `tests/Feature/InertiaPropContractTest.php`
- Delete: `resources/views/players/show.blade.php`
- Test: `tests/Feature/PlayerProfileTest.php` (17 HTML assertions), `tests/Feature/VenuePointsPrivacyTest.php`

**Interfaces:**
- Consumes: `SeasonFigures::props()` from Task 1.
- Produces: component `Players/Show` with props
  `eyebrow, title, is_self, season_name, season_href, season, self, results`.

- [ ] **Step 1: Change the controller**

```php
    public function show(User $player): Response
    {
        $currentSeason = PokerSeason::current();

        // Career, matching the dashboard: Recent Results is a history, and a
        // season's worth of it is what the panel above already says.
        $results = PokerTournamentResult::where('user_id', $player->id)
            ->with(['tournament.season'])
            ->latest()
            ->get();

        return Inertia::render('Players/Show', [
            // The nickname leads where a name has one. The full name titles
            // the page -- display_name prefers a nickname and falls back to
            // the first name alone, which is right beside an avatar and wrong
            // at the head of a profile.
            'eyebrow' => filled($player->nickname) ? $player->nickname : __('Player'),
            'title' => trim($player->first_name.' '.$player->last_name),
            'is_self' => auth()->id() === $player->id,

            // Five, chosen here. The Blade took ->take(5) in the template and
            // asked ->count() of the whole collection for the note; both facts
            // come over as props so the page places rather than decides.
            'results' => $results->take(5)->map(fn (PokerTournamentResult $result) => [
                'id' => $result->id,
                'tournament_name' => $result->tournament->name,
                'tournament_href' => route('tournaments.show', $result->tournament),
                'date_label' => $result->tournament->start_time->format('M d, Y'),
                'place' => $result->place,
                'points_label' => number_format($result->points),
            ])->values()->all(),
            'has_more_results' => $results->count() > 5,

            ...SeasonFigures::props($currentSeason, $player->id, auth()->user(), self: auth()->id() === $player->id),
        ]);
    }
```

The `VenuePoints::readableBy()` block in this method **goes**: Task 1 owns that
decision now. Leaving both is two definitions of the rule.

- [ ] **Step 2: Write `resources/js/Pages/Players/Show.vue`**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Card from '../../Components/Card.vue';
import Table from '../../Components/Table.vue';
import Rank from '../../Components/Rank.vue';
import Btn from '../../Components/Btn.vue';
import EmptyState from '../../Components/EmptyState.vue';
import SeasonPanel from '../../Components/SeasonPanel.vue';

interface Result {
    id: string;
    tournament_name: string;
    tournament_href: string;
    date_label: string;
    place: number;
    points_label: string;
}

interface Props {
    eyebrow: string;
    title: string;
    is_self: boolean;
    results: Result[];
    has_more_results: boolean;
    season_name: string;
    season_href: string | null;
    season: Record<string, unknown> | null;
    self: boolean;
}

const props = defineProps<Props>();
</script>

<template>
    <AppLayout>
        <template #header>
            <!-- The name is the title. Whose figures these are is the whole
                 question this page answers, so it is not an eyebrow above a
                 heading that would read "Player" on every one of them. -->
            <PageHeader :eyebrow="eyebrow" :title="title">
                <template v-if="is_self" #actions>
                    <Btn variant="ghost" size="sm" href="/dashboard">Your dashboard</Btn>
                </template>
            </PageHeader>
        </template>

        <div class="l-container l-stack">
            <!-- The same panel the dashboard draws, about somebody else. It
                 takes self so the empty-rank copy can speak about a third
                 party, and it leaves out venue points entirely when the
                 controller has withheld them. -->
            <SeasonPanel :season-name="season_name" :season-href="season_href"
                         :season="season" :self="self" />

            <Card title="Recent Results" flush>
                <Table>
                    <template #head>
                        <th scope="col">Tournament</th>
                        <th scope="col">Place</th>
                        <th scope="col" class="table__num">Points</th>
                    </template>

                    <!-- The whole row is the link, as on the dashboard: three
                         short cells offer a poor target otherwise, and three
                         separate anchors to one tournament would read as
                         fifteen links down a list of five. -->
                    <tr v-for="result in results" :key="result.id" class="table__row--link">
                        <td>
                            <div class="entry__title">
                                <Link class="table__link" :href="result.tournament_href">
                                    {{ result.tournament_name }}
                                </Link>
                            </div>
                            <div class="entry__meta">
                                <span>{{ result.date_label }}</span>
                            </div>
                        </td>

                        <td><Rank :place="result.place" /></td>

                        <td class="table__num">{{ result.points_label }}</td>
                    </tr>

                    <tr v-if="results.length === 0">
                        <td colspan="3">
                            <!-- About them, not to them: this page is read by
                                 somebody else more often than not. -->
                            <EmptyState title="No results recorded yet." />
                        </td>
                    </tr>
                </Table>

                <p v-if="has_more_results" class="card__note">Showing latest 5 career results</p>
            </Card>
        </div>
    </AppLayout>
</template>
```

`Table.vue` renders `<thead>` from a `head` slot and `<tbody>` from its default
slot, so rows are written as direct children — the same shape as the Blade
component.

- [ ] **Step 3: Register the page and delete the Blade view**

In `tests/Feature/InertiaPropContractTest.php`, add to `PAGES`:

```php
        'Players/Show' => ['players.show', 'player'],
```

and an arm to `fixture()`:

```php
            'player' => \App\Models\User::factory()->create([
                'email' => 'fixture.player@example.test',
            ])->id,
```

Then `rm resources/views/players/show.blade.php`.

- [ ] **Step 4: Type-check, build, full suite**

```bash
npm run typecheck && npx vite build
out=$(bin/suite.sh); echo "$out" | grep -E "FAIL|crash|ran "
```

`PlayerProfileTest` has 17 assertions reading rendered HTML and will fail.
Port each to the prop it is really about — a season figure is an equality
against `$props['season']['…']`, a piece of markup is read from the page file.
`VenuePointsPrivacyTest` asserts the rule from the outside; keep it asserting
the rule, now against the props.

**Port, do not delete.** Every ported assertion in Plans 3 and 4 came out
stronger than the one it replaced, and two of them caught real faults while
being ported.

- [ ] **Step 5: Prove each ported assertion fails**

For each one, break the thing it describes and watch it fail, then put it back.
A ported assertion that has never failed is a comment.

- [ ] **Step 6: Verify in a browser at 1440, 960 and 375**

- the panel shows rank, the three medals and the totals
- a player with no events reads "Not entered yet" and **"A player is ranked
  once they enter a tournament."** — the third-party copy, not the "Enter a
  tournament to be ranked." a person gets on their own dashboard
- as a non-admin viewing another player, **no Venue Points row at all**
- a result row is one link, not three: `document.querySelectorAll('a a').length`
  is 0 and the row has one anchor

- [ ] **Step 7: Commit (hand-off)**

```
feat: convert a player's profile to Inertia + Vue

The first page to draw SeasonPanel, which has existed unused since Plan 2. The
venue-points rule moved out of this controller and into SeasonFigures with it,
so the two pages that draw the panel cannot come to disagree about who may read
a tally.
```

---

## Task 3: `/dashboard`

The panel again, plus the only list in this plan with actions on it.

**Files:**
- Create: `resources/js/Pages/Dashboard.vue`
- Modify: `app/Http/Controllers/DashboardController.php`, `tests/Feature/InertiaPropContractTest.php`
- Delete: `resources/views/dashboard.blade.php`
- Test: `DashboardPanelsTest`, `DashboardRecentResultsLinkTest`,
  `DashboardTournamentActionsTest`, `SeasonPanelTest`, `SeasonRankCardTest`,
  `UpcomingEventsCardTest`, `DashboardGutterTest`

**Props:**

```ts
interface Props {
    eyebrow: string;            // the viewer's full name
    upcoming: Tournament[];     // at most five
    upcoming_note: string | null;   // "The next 5 of 12 scheduled.", or null
    results: Result[];          // at most five, same shape as Players/Show
    has_more_results: boolean;
    // plus the four from SeasonFigures::props()
}

interface Tournament {
    id: string;
    name: string;
    month_label: string;        // "Sep"
    day_label: string;          // "20"
    time_label: string;         // "7:00 PM"
    venue_label: string;        // the venue's name, or "TBD"
    href: string;
    is_registered: boolean;
    can_unregister: boolean;    // registered AND no results recorded
    unregister_confirm: string; // the emph()-marked sentence
    can_register: boolean;      // not registered AND the account is approved
}
```

**Four things the Blade decides that must become props.**

1. `$isReg` — `$tournament->registrants->contains('user_id', Auth::id())`.
2. `! $tournament->hasRecordedResults()` — the controller already loads
   `withCount('results')` for exactly this.
3. `auth()->user()->isApproved()` — an unapproved account is shown an
   "Awaiting approval" badge instead of a button, because the controller
   refuses the registration anyway and offering a click that cannot work is
   worse than saying why.
4. The note under the list, which is a `trans_choice` over two counts and is
   rendered **only when the cap actually hides something**: "Showing the next 5
   of 3" is worse than saying nothing.

**The confirmation sentence carries emphasis markers.** The Blade writes
`data-confirm="… Unregister from :tournament? …"` with `emph($tournament->name)`.
Send that string as `unregister_confirm` and pass it to `ask()` — `Emph.vue` and
`confirm.ts` share `emph.ts`, so the markers render the same on both sides.

- [ ] **Step 1: Change the controller**

Keep every query as it is — `take(5)` is on the QUERY and must stay there, and
`withCount('results')` exists so `hasRecordedResults()` does not run a query per
row. Map to props:

```php
        return Inertia::render('Dashboard', [
            'eyebrow' => trim($user->first_name.' '.$user->last_name),

            'upcoming' => $upcomingTournaments->map(function (PokerTournament $tournament) use ($user) {
                $isRegistered = $tournament->registrants->contains('user_id', $user->id);

                return [
                    'id' => $tournament->id,
                    'name' => $tournament->name,

                    // The date you PLAY. This chip once read the registration
                    // deadline, an hour earlier, so it could show the wrong
                    // day either side of midnight.
                    'month_label' => $tournament->start_time->format('M'),
                    'day_label' => $tournament->start_time->format('d'),
                    'time_label' => $tournament->start_time->format('g:i A'),
                    'venue_label' => $tournament->venue->name ?? __('TBD'),
                    'href' => route('tournaments.show', $tournament),
                    'is_registered' => $isRegistered,
                    'can_unregister' => $isRegistered && ! $tournament->hasRecordedResults(),
                    'unregister_confirm' => __('Unregister from :tournament? You can enter again any time before results are recorded.', [
                        'tournament' => emph($tournament->name),
                    ]),
                    'can_register' => ! $isRegistered && $user->isApproved(),
                ];
            })->values()->all(),

            // Only when the cap hides something, and null otherwise: a
            // standing note on a complete list is noise.
            'upcoming_note' => $upcomingCount > $upcomingTournaments->count()
                ? trans_choice(
                    '{1}The next one of :count scheduled.|[2,*]The next :shown of :count scheduled.',
                    $upcomingTournaments->count(),
                    ['shown' => $upcomingTournaments->count(), 'count' => $upcomingCount]
                )
                : null,

            'results' => $userResults->take(5)->map(fn (PokerTournamentResult $result) => [
                'id' => $result->id,
                'tournament_name' => $result->tournament->name,
                'tournament_href' => route('tournaments.show', $result->tournament),
                'date_label' => $result->tournament->start_time->format('M d, Y'),
                'place' => $result->place,
                'points_label' => number_format($result->points),
            ])->values()->all(),
            'has_more_results' => $userResults->count() > 5,

            ...SeasonFigures::props($currentSeason, $user->id, $user, self: true),
        ]);
```

- [ ] **Step 2: Write `resources/js/Pages/Dashboard.vue`**

The registration actions, which is the part with no precedent in Plans 3–4:

```vue
const { ask } = useConfirm();

const register = (tournament: Tournament): void => {
    router.post(`/tournaments/${tournament.id}/register`, {}, { preserveScroll: true });
};

const unregister = async (tournament: Tournament): Promise<void> => {
    if (await ask(tournament.unregister_confirm)) {
        router.delete(`/tournaments/${tournament.id}/unregister`, { preserveScroll: true });
    }
};
```

Both paths are from `route:list`, and they are **not** the same URL under two
verbs: `POST /tournaments/{tournament}/register` and
`DELETE /tournaments/{tournament}/unregister`. Writing the second as a DELETE
to `/register` is the obvious guess and is wrong.

`preserveScroll` on both: the list is below the fold on a phone, and a
registration that jumps the page to the top loses the reader's place.

The rest is the Blade's markup with Vue's control flow. Keep every class:
`.entry`, `.date-chip`, `.date-chip__month`, `.date-chip__day`, `.entry__body`,
`.entry__title`, `.entry__meta`, `.entry__actions`, `.card__note`,
`.card__note--split`.

- [ ] **Step 3: Register the page, delete the Blade view**

`'Dashboard' => ['dashboard', null],` in `PAGES`, then
`rm resources/views/dashboard.blade.php`.

- [ ] **Step 4: Type-check, build, full suite; port what breaks**

Seven test files read this page's HTML. Expect 40-odd assertions to move.
`DashboardGutterTest` already reads `.vue` and may need only its path updating.

- [ ] **Step 5: Prove the ported assertions fail**

- [ ] **Step 6: Verify in a browser at 1440, 960 and 375**

- Register on an upcoming tournament: the row becomes "Registered" with an
  Unregister button, and the page does not jump
- Unregister: the confirmation names the tournament **in bold** — the emph
  markers survived — and declining leaves the registration alone
- A tournament with results recorded shows the badge and **no** Unregister
- Signed in as an unapproved account: "Awaiting approval", no Register button
- With three upcoming tournaments and a cap of five: **no note at all**

- [ ] **Step 7: Commit (hand-off)**

```
feat: convert the dashboard to Inertia + Vue

Four decisions the template was making move into props: whether the viewer is
registered, whether a withdrawal is still possible, whether the account is
approved, and whether the cap under the list is hiding anything. All four are
the controller's to answer -- it refuses the registration either way -- and a
template deciding them again is a second definition of each.
```

---

## Task 4: `/users/{user}`

**Files:**
- Create: `resources/js/Pages/Users/Show.vue`
- Modify: `app/Http/Controllers/UserController.php`, `tests/Feature/InertiaPropContractTest.php`
- Delete: `resources/views/users/show.blade.php`
- Test: `UserManagementTest`, `UserSearchTest`, `MonogramCoverageTest`, `FormActionAlignmentTest`

**Props:** one `user` object of finished values, plus `decided_by_label`.

```ts
interface Props {
    title: string;              // "User Details: Dana Whitlock"
    user: {
        id: string;
        full_name: string;
        nickname_label: string;     // the nickname, or "—"
        email: string;
        display_name: string;
        initials: string;           // from initials(), never derived in Vue
        role_label: string;         // "Administrator" or "Player"
        role_badge: string;         // "Admin" or "Player"
        is_admin: boolean;
        member_since_label: string; // "Sep 20, 2026", or "—"
        approval_label: string;     // "Approved" | "Pending" | "Rejected"
        approval_tone: 'open' | 'neutral' | 'primary';
        decided_label: string | null;   // "Sep 20, 2026 by Admin User", or null
        can_verify: boolean;        // the address is not verified yet
        can_approve: boolean;
        can_reject: boolean;
        reject_confirm: string;
    };
    invite_url: string | null;
    verification_url: string | null;
}
```

**`approval_tone` is semantic, and the mapping is the Blade's:** approved →
`open`, pending → the default badge, rejected → `primary`. That last one looks
wrong and is not: read `users/show.blade.php` before changing it, and if it is
a mistake, fix it in its own commit rather than silently while converting.

**`invite_url` and `verification_url` are page props read from the session**,
exactly as `Users/Index` already does — `session('invite_url')` in the
controller, not the shared flash. Both are shown **as well as** emailed because
`MAIL_MAILER` is log, so a link that is only sent reaches nobody.

**The four actions** are `users.invite` (POST), `users.verification` (POST),
`users.approve` (PATCH) and `users.reject` (PATCH, confirmed). Send password
link is offered unconditionally — whether an account has ever set a password is
not knowable from the schema. Send verification link is drawn only while it can
do something.

**The alerts sit in their own `.l-container`, above the `.l-sidebar` grid.** A
direct child of `.l-sidebar` becomes one of its two columns, so an alert placed
inside renders as a sidebar. Keep the two containers separate.

- [ ] **Step 1: Change the controller** — `decidedBy` stays resolved by
  `User::find()`, because `approval_decided_by` is a plain string column rather
  than a foreign key: `users.id` is a ULID and the row it names may since have
  been deleted. Send `decided_label` already joined.

- [ ] **Step 2: Write the page**, with `Monogram`, `Badge`, the two `rows`
  definition lists, and the action cluster. Class names to keep: `.rows`,
  `.row`, `.row__label`, `.row__value`, `.field__media`, `.entry__title`,
  `.entry__meta`, `.l-sidebar`, `.l-cluster`, `.u-mono`.

- [ ] **Step 3: Register the page and delete the Blade view**

- [ ] **Step 4: Type-check, build, full suite; port what breaks**

`MonogramCoverageTest::LISTS` names `views/users/show.blade.php`; re-point it
at `js/Pages/Users/Show.vue`. `FormActionAlignmentTest` skips this page's
action row deliberately — it is a toolbar of independent actions, not a form's
Save and Cancel — so check its floor counts still hold.

- [ ] **Step 5: Prove the ported assertions fail**

- [ ] **Step 6: Verify in a browser at 1440, 960 and 375**

- approve a pending account: the badge changes and the buttons swap
- reject an approved one: the confirmation names the player in bold
- the password link alert shows a URL that actually works
- at 375 the sidebar grid stacks and the alerts stay above it

- [ ] **Step 7: Commit (hand-off)**

---

## Task 5: `/poker/venues/{venue}`

**Files:**
- Create: `resources/js/Pages/Poker/Venues/Show.vue`
- Modify: `app/Http/Controllers/Poker/VenueController.php`, `tests/Feature/InertiaPropContractTest.php`
- Delete: `resources/views/poker/venues/show.blade.php`
- Test: `VenuePointsFromVenuePageTest`, `VenueStatRowsTest`, `LinkedRowTest`,
  `PlayerLinkCoverageTest`, `EmphasisTest`, `IndexRowLinksTest`, `EmptyStateSmokeTest`

**Props:**

```ts
interface Props {
    venue: {
        id: string;
        name: string;
        description: string | null;
        address: string | null;
        map_src: string | null;     // null when there is no address
        delete_confirm: string;
    };
    tournaments_label: string;      // the count, for the badge and the stat
    venue_points_label: string;     // number_format()ed
    leaderboard: LeaderboardRow[];
    tournaments: TournamentRow[];   // at most ten, newest first
    has_more_tournaments: boolean;
    add_points_href: string;        // carries ?venue_id=
}
```

**The map URL is built in PHP.** The Blade interpolates
`urlencode($venue->address)` into a `maps.google.com` URL; building it in Vue
would put an encoding decision in the template, and an address with an
apostrophe is exactly the sort of thing that then breaks. Send `map_src`, null
when there is no address, and let the page render the `.map` block only when it
is present.

**The leaderboard rows use `PlayerLink`.** `PlayerLinkCoverageTest` has
`views/poker/venues/show.blade.php` in its `LINKED` list; re-point it at the
Vue path. The component refuses to link a name with no account behind it —
`user_id` is nullable with `nullOnDelete` — so send `player_id: string | null`
and let `PlayerLink` decide.

**The recent-tournament rows are `entry entry--link`**, a whole-row anchor with
the `.entry__go` chevron. `LinkedRowTest` now checks `<Link>` as well as `<a>`,
so the `entry--link` class must be on the `<Link>` itself. And because the row
is one big anchor, **nothing inside it may be another link** — `NestedAnchorTest`
holds that, and it is the fault that spilled 45 archive cards into 135 grid
children.

**Deleting a venue is offered here**, with `emph()` in the confirmation, because
an administrator removing one has its takings and its leaderboard in front of
them.

- [ ] **Step 1: Change the controller** — keep the existing `load()` and the
  leaderboard grouping exactly as they are. The comment on that method records
  that `tournaments.results` was dropped deliberately: it loaded every result
  of every night to sum once and throw away.

- [ ] **Step 2: Write the page**

- [ ] **Step 3: Register the page and delete the Blade view**

- [ ] **Step 4: Type-check, build, full suite; port what breaks**

`VenueStatRowsTest` reads both this view and `poker/seasons/show.blade.php`;
re-point the first entry and leave the second, which converts in Plan 6.

- [ ] **Step 5: Prove the ported assertions fail**

- [ ] **Step 6: Verify in a browser at 1440, 960 and 375**

- the map renders, and a venue with no address shows the body full-width
- "Add points" opens the picker with this venue already chosen
- ten rows, each one link: `document.querySelectorAll('a a').length` is 0
- delete asks first, naming the venue in bold

- [ ] **Step 7: Commit (hand-off)**

---

## Task 6: `GuestLayout.vue`, `/verify-email` and `/confirm-password`

Last, and independent of everything above.

**Files:**
- Create: `resources/js/Layouts/GuestLayout.vue`,
  `resources/js/Pages/Auth/VerifyEmail.vue`,
  `resources/js/Pages/Auth/ConfirmPassword.vue`
- Modify: `Auth/EmailVerificationPromptController`,
  `Auth/ConfirmablePasswordController`, `tests/Feature/InertiaPropContractTest.php`
- Delete: `resources/views/auth/verify-email.blade.php`,
  `resources/views/auth/confirm-password.blade.php`
- Test: `tests/Feature/Auth/EmailVerificationTest.php`,
  `tests/Feature/Auth/PasswordConfirmationTest.php`

**`GuestLayout.vue` is new, and it is small.** `layouts/guest.blade.php` is a
`.guest` wrapper, a `.guest__panel.l-stack`, a `.guest__brand` holding
`<x-brand>`, and the slot. The Vue twin renders the same four elements around
`<slot />` with `Brand.vue` — which already exists. It has **no** `Topbar` and
no `ConfirmDialog`: neither screen asks a question.

**`/login`, `/register`, `/forgot-password` and `/reset-password` stay Blade**
and keep using `layouts/guest.blade.php`. Both layouts exist until those four
convert, which the spec says may never happen — `/register` carries the
reCAPTCHA gate that is verified end to end, and leaving it alone is the point.

**`verify-email` renders its own alert on one sentinel value.** The Blade reads
`session('status') === 'verification-link-sent'` and shows a fixed sentence —
the session value is a SENTINEL, not the message. This is one of the three
places the spec's §3 says the layout cannot own the flash. Send a boolean prop
`link_sent` rather than passing the sentinel to `<Alert>`, which would print
the word `verification-link-sent` on screen.

**`EmailVerificationPromptController` returns `RedirectResponse|View` today.**
It becomes `RedirectResponse|Response` — the redirect for an already-verified
account stays exactly as it is, including `redirect()->intended()`.

- [ ] **Step 1: Write `GuestLayout.vue`**

```vue
<script setup lang="ts">
import Brand from '../Components/Brand.vue';
</script>

<template>
    <!-- The guest shell: no navigation, because a person on these screens has
         somewhere to be and nowhere else to go. The Blade twin is
         layouts/guest.blade.php, which the four public auth screens still use;
         ComponentParityTest does not pair layouts, so the classes here are
         copied from it by hand. -->
    <div class="guest">
        <div class="guest__panel l-stack">
            <div class="guest__brand"><Brand /></div>
            <slot />
        </div>
    </div>
</template>
```

- [ ] **Step 2: Convert both controllers**

```php
    public function __invoke(Request $request): RedirectResponse|Response
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->intended(route('dashboard', absolute: false))
            : Inertia::render('Auth/VerifyEmail', [
                // A BOOLEAN, not the session value. session('status') holds
                // the sentinel 'verification-link-sent'; passing it to an
                // alert would print that word on the screen.
                'link_sent' => $request->session()->get('status') === 'verification-link-sent',
            ]);
    }
```

```php
    public function show(): Response
    {
        return Inertia::render('Auth/ConfirmPassword');
    }
```

- [ ] **Step 3: Write both pages**

`VerifyEmail.vue`: the `.u-muted` paragraph, the alert when `link_sent`, and a
`.l-cluster.l-cluster--between` holding two actions — Resend (primary) and Log
Out (ghost). Both are `router.post` calls now rather than forms;
`/email/verification-notification` and `/logout` are the paths, and
`UpdateProfileInformationForm.vue` already posts the first of them.

`ConfirmPassword.vue`: one `useForm({ password: '' })`, a `Field` with
`type="password"`, `required`, `autocomplete="current-password"`, and a
`.l-cluster.l-cluster--end` with a primary Confirm. Posts to `/confirm-password`.

**`autofocus` is not on the Blade's password field** — do not add one.

- [ ] **Step 4: Register both pages and delete both Blade views**

```php
        'Auth/VerifyEmail' => ['verification.notice', null],
        'Auth/ConfirmPassword' => ['password.confirm', null],
```

Both need an **unverified** and an **authenticated** user respectively;
`InertiaPropContractTest` acts as a verified admin, so check both routes
actually render for that user rather than redirecting. If `verification.notice`
redirects a verified account — it does — either give the test a second acting
user or leave `Auth/VerifyEmail` out of `PAGES` and cover its props in
`EmailVerificationTest` instead. **Do not** weaken the "every converted page is
listed" assertion to make this fit.

- [ ] **Step 5: Type-check, build, full suite; port what breaks**

- [ ] **Step 6: Verify in a browser at 1440 and 375**

- `/confirm-password` as a signed-in user: the panel is centred, the brand is
  above it, a wrong password shows the error under the field
- `/verify-email` as an unverified account: Resend shows the alert with the
  **sentence**, not the sentinel; Log Out signs out

- [ ] **Step 7: Commit (hand-off)**

---

## What comes after this plan

**Plan 6 — the two large pages** (≈10 days): `poker/seasons/show` (346 lines,
the points/rank toggle and the venue-points privacy rule) and
`poker/tournaments/show` (499, plus porting `p-event` at 308 lines).

**Then the removal**, which spec §7 step 5 describes. After Plan 6 the only
Alpine left is in `components/modal.blade.php`, `components/topbar.blade.php`
and `components/dropdown.blade.php` — all three shared with the public side, so
Alpine goes only when their Blade callers do, which is not planned.
`MarkupBalanceTest` and `AlpineAttributeGuardTest` retire when their last
target does. `VenueStatRowsTest` is the last guard still naming a `.blade.php`
page view, and re-points in Plan 6.
