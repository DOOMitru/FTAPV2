# Finale Qualification Thresholds — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A season carries three qualification thresholds an administrator can edit, and the season page shows both the targets and which players meet them.

**Architecture:** Three nullable integer columns on `seasons`, a model method that evaluates one player's standing against them, and a rewrite of `PokerSeasonController@show`'s leaderboard to carry venue points and a qualification verdict. No new tables and no new routes — the season resource and its show page already exist.

**Tech Stack:** Laravel 12 · PHP 8.5 · SQLite · Blade · PHPUnit 11

## Design decisions

Settled with the owner on 2026-09-01, before this plan was written:

1. **All three thresholds must be met**, not any one of them. The brief says
   "they must" of each in turn.
2. **The season page shows the targets AND who meets them**, with the reason a
   player falls short named rather than left as a bare cross. The page already
   computes points and wins per player; only venue points are new, and a
   threshold nobody can check themselves against is a number on a page.
3. **Thresholds are nullable.** The home page already tells visitors the
   thresholds are still being set, and a season created before this feature has
   none. A null threshold is "not published", never "zero".

## The three facts this depends on

Established by reading the schema, and each one shapes a task:

- **`venue_points` has no `season_id`.** Its columns are `id, event_date,
  amount, user_id, user_name, venue_id`. "Venue points accumulated during the
  season" can therefore only mean rows whose `event_date` falls within the
  season's `start_date`–`end_date`. **This means the figure moves if a season's
  dates are later edited**, which is a real consequence and belongs in the UI
  copy, not just in a comment.
- **`venue_points` is empty — 0 rows.** Until venue points are actually
  recorded, that threshold fails for everyone, so on a live season with a
  venue-points target nobody will qualify. Task 4 must not read that as a bug.
- **The existing leaderboard already carries what two thirds of this needs.**
  `PokerSeasonController@show` groups results per user with `points` summed and
  `wins` counted as `place === 1`. Reuse it; do not add a second definition of
  either word.

## Global Constraints

- **NEVER RUN GIT COMMANDS.** Every "commit" step is a hand-off: state the files
  and the message, then stop. Use `find . -newer .git/COMMIT_EDITMSG` to see
  what changed.
- **One definition of qualification.** `PokerSeason::qualifies()` is the only
  place the rule lives. The page renders its verdict; it does not re-derive one.
- **A null threshold is not zero.** Not published means not enforced, and the
  page must say "not set" rather than showing a 0 that reads as a real target.
- **No inline CSS or JavaScript** — `InlineStyleGuardTest` enforces it.
- **Every new guard test must be proven to fail** before it is trusted.
- **Screenshot before declaring the page done.** Every visual defect this
  project has shipped passed a green suite.

## Verification

```bash
php artisan test        # 205 passing before this plan starts
php artisan migrate
npm run build
```

## File structure

| File | Responsibility | Task |
|---|---|---|
| `database/migrations/*_add_finale_thresholds_to_seasons_table.php` | the three columns | 1 |
| `app/Models/PokerSeason.php` | `hasThresholds()`, `qualifies()`, `unmetBy()` | 1 |
| `database/factories/PokerSeasonFactory.php` | a `withThresholds()` state | 1 |
| `app/Http/Controllers/Poker/PokerSeasonController.php` | validation on store/update; venue points and verdicts in `show` | 2, 3 |
| `resources/views/poker/seasons/create.blade.php`, `edit.blade.php` | the three inputs | 2 |
| `resources/views/poker/seasons/show.blade.php` | the targets panel and the standings column | 4 |
| `resources/css/4-pages/_season-show.css` | the qualification cell | 4 |
| `tests/Feature/FinaleQualificationTest.php` | the rule and the page | 1–4 |

---

### Task 1: The thresholds and the rule

**Files:**
- Create: `database/migrations/<timestamp>_add_finale_thresholds_to_seasons_table.php`
- Modify: `app/Models/PokerSeason.php`, `database/factories/PokerSeasonFactory.php`
- Test: `tests/Feature/FinaleQualificationTest.php` (new)

**Interfaces produced:** columns `finale_points_required`, `finale_wins_required`, `finale_venue_points_required`; methods `PokerSeason::hasThresholds(): bool`, `PokerSeason::qualifies(int $points, int $wins, int $venuePoints): bool`, `PokerSeason::unmetBy(int $points, int $wins, int $venuePoints): array`. Tasks 2–4 consume these by name.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinaleQualificationTest extends TestCase
{
    use RefreshDatabase;

    private function season(array $thresholds = []): PokerSeason
    {
        return PokerSeason::create(array_merge([
            'name' => 'Season 9',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ], $thresholds));
    }

    public function test_a_season_starts_with_no_thresholds(): void
    {
        // Null is "not published", never zero: every season that existed before
        // this feature has none, and the home page already tells visitors the
        // thresholds are still being set.
        $season = $this->season();

        $this->assertNull($season->finale_points_required);
        $this->assertFalse($season->hasThresholds());
    }

    public function test_no_thresholds_means_nobody_is_judged(): void
    {
        // Not enforced, rather than trivially satisfied. The page must not show
        // a green tick against a rule nobody has written.
        $this->assertFalse($this->season()->hasThresholds());
    }

    public function test_all_three_thresholds_must_be_met(): void
    {
        $season = $this->season([
            'finale_points_required' => 300,
            'finale_wins_required' => 2,
            'finale_venue_points_required' => 50,
        ]);

        $this->assertTrue($season->qualifies(points: 300, wins: 2, venuePoints: 50));
        $this->assertFalse($season->qualifies(points: 299, wins: 2, venuePoints: 50));
        $this->assertFalse($season->qualifies(points: 300, wins: 1, venuePoints: 50));
        $this->assertFalse($season->qualifies(points: 300, wins: 2, venuePoints: 49));
    }

    public function test_meeting_a_threshold_exactly_qualifies(): void
    {
        // The boundary, stated once so nobody "tidies" >= into >.
        $season = $this->season([
            'finale_points_required' => 100,
            'finale_wins_required' => 1,
            'finale_venue_points_required' => 10,
        ]);

        $this->assertTrue($season->qualifies(points: 100, wins: 1, venuePoints: 10));
    }

    public function test_a_null_threshold_is_not_a_barrier(): void
    {
        // A season with only a points target should judge only points.
        $season = $this->season(['finale_points_required' => 100]);

        $this->assertTrue($season->qualifies(points: 100, wins: 0, venuePoints: 0));
        $this->assertFalse($season->qualifies(points: 99, wins: 0, venuePoints: 0));
    }

    public function test_the_unmet_criteria_are_named(): void
    {
        // So the page can say WHICH one a player is short on rather than
        // showing a bare cross.
        $season = $this->season([
            'finale_points_required' => 300,
            'finale_wins_required' => 2,
            'finale_venue_points_required' => 50,
        ]);

        $this->assertSame([], $season->unmetBy(points: 300, wins: 2, venuePoints: 50));
        $this->assertSame(['wins'], $season->unmetBy(points: 300, wins: 1, venuePoints: 50));
        $this->assertSame(
            ['points', 'venue_points'],
            $season->unmetBy(points: 10, wins: 5, venuePoints: 0)
        );
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=FinaleQualificationTest`
Expected: FAIL — the columns and `hasThresholds()` do not exist.

- [ ] **Step 3: The migration**

`php artisan make:migration add_finale_thresholds_to_seasons_table`, then:

```php
Schema::table('seasons', function (Blueprint $table) {
    // Nullable, and null means "not published" rather than zero. Seasons that
    // existed before this feature have no thresholds, and the public site
    // already tells visitors the numbers are still being decided -- a
    // defaulted 0 would turn that into a target everybody has already met.
    $table->unsignedInteger('finale_points_required')->nullable()->after('is_current');
    $table->unsignedInteger('finale_wins_required')->nullable()->after('finale_points_required');
    $table->unsignedInteger('finale_venue_points_required')->nullable()->after('finale_wins_required');
});
```

The `down()` drops all three.

- [ ] **Step 4: The model**

Add the three columns to `$fillable`, cast them to `integer`, and add:

```php
/**
 * Whether this season publishes any qualification target at all.
 *
 * A season with none is not "everybody qualifies" -- it is a season whose
 * rules have not been set, and the page must say so rather than showing a
 * tick against a rule nobody has written.
 */
public function hasThresholds(): bool
{
    return $this->finale_points_required !== null
        || $this->finale_wins_required !== null
        || $this->finale_venue_points_required !== null;
}

/**
 * The single definition of qualifying. Every screen calls this rather than
 * comparing the columns, so the season page and anything added later cannot
 * disagree about who is in.
 *
 * All three must be met. A NULL threshold is not a barrier: a season may
 * publish a points target and nothing else.
 */
public function qualifies(int $points, int $wins, int $venuePoints): bool
{
    return $this->unmetBy($points, $wins, $venuePoints) === [];
}

/**
 * Which criteria a player falls short on, in a fixed order.
 *
 * @return array<int, string> any of 'points', 'wins', 'venue_points'
 */
public function unmetBy(int $points, int $wins, int $venuePoints): array
{
    $unmet = [];

    // >=, not >: meeting the number exactly is meeting it.
    if ($this->finale_points_required !== null && $points < $this->finale_points_required) {
        $unmet[] = 'points';
    }

    if ($this->finale_wins_required !== null && $wins < $this->finale_wins_required) {
        $unmet[] = 'wins';
    }

    if ($this->finale_venue_points_required !== null && $venuePoints < $this->finale_venue_points_required) {
        $unmet[] = 'venue_points';
    }

    return $unmet;
}
```

- [ ] **Step 5: The factory state**

Check whether `PokerSeasonFactory` exists (`ls database/factories`). If it does,
add:

```php
public function withThresholds(int $points = 300, int $wins = 2, int $venuePoints = 50): static
{
    return $this->state(fn () => [
        'finale_points_required' => $points,
        'finale_wins_required' => $wins,
        'finale_venue_points_required' => $venuePoints,
    ]);
}
```

If it does not exist, skip it — the tests above build seasons directly, and a
factory nothing uses is dead code.

- [ ] **Step 6: Migrate and test**

```bash
php artisan migrate
php artisan test
```

- [ ] **Step 7: Prove the rule bites**

Change `>=` to `>` in one branch of `unmetBy()` and confirm
`test_meeting_a_threshold_exactly_qualifies` fails. Restore it.

- [ ] **Step 8: HAND-OFF — do not run git**

```
feat(seasons): add finale qualification thresholds

Three nullable targets on a season: points accumulated, tournaments won and
venue points. All three must be met to qualify.

Null means the threshold is not published, never zero. Every season that
existed before this has none, and the public site already tells visitors the
numbers are still being decided -- a defaulted 0 would turn that into a
target everybody has already cleared.

qualifies() is the single definition, and unmetBy() names which criteria a
player falls short on so a screen can say why rather than showing a bare
cross. Meeting a number exactly qualifies.
```

---

### Task 2: Administrators can set them

**Files:**
- Modify: `app/Http/Controllers/Poker/PokerSeasonController.php` (both `validate` blocks, lines ~35 and ~114)
- Modify: `resources/views/poker/seasons/create.blade.php`, `resources/views/poker/seasons/edit.blade.php`
- Test: `tests/Feature/FinaleQualificationTest.php`

- [ ] **Step 1: Write the failing tests**

```php
    public function test_an_admin_can_set_the_thresholds(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('poker.seasons.store'), [
            'name' => 'Season 10',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'finale_points_required' => 300,
            'finale_wins_required' => 2,
            'finale_venue_points_required' => 50,
        ])->assertRedirect();

        $season = PokerSeason::where('name', 'Season 10')->first();

        $this->assertSame(300, $season->finale_points_required);
        $this->assertTrue($season->hasThresholds());
    }

    public function test_thresholds_are_optional(): void
    {
        // A season can be created before its rules are decided.
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('poker.seasons.store'), [
            'name' => 'Season 11',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
        ])->assertRedirect();

        $this->assertFalse(PokerSeason::where('name', 'Season 11')->first()->hasThresholds());
    }

    public function test_a_negative_threshold_is_rejected(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('poker.seasons.store'), [
            'name' => 'Season 12',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'finale_points_required' => -5,
        ])->assertSessionHasErrors('finale_points_required');
    }

    public function test_clearing_a_threshold_sets_it_back_to_null(): void
    {
        // Not to zero. An administrator withdrawing a target must be able to
        // return the season to "not published".
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);
        $season = $this->season(['finale_points_required' => 300]);

        $this->actingAs($admin)->put(route('poker.seasons.update', $season), [
            'name' => $season->name,
            'start_date' => $season->start_date->toDateString(),
            'end_date' => $season->end_date->toDateString(),
            'finale_points_required' => '',
        ]);

        $this->assertNull($season->fresh()->finale_points_required);
    }
```

- [ ] **Step 2: Run them and watch them fail**

- [ ] **Step 3: Validation, on BOTH writes**

The controller has two identical `validate` blocks — store around line 35 and
update around line 114. **Add to both**, or an edit will silently drop what
create accepts:

```php
            // nullable, so a threshold can be withdrawn as well as set. An
            // empty input arrives as '' rather than null, so it is converted
            // below rather than failing the integer rule.
            'finale_points_required' => 'nullable|integer|min:0',
            'finale_wins_required' => 'nullable|integer|min:0',
            'finale_venue_points_required' => 'nullable|integer|min:0',
```

**An empty text input posts `''`, not null**, and `''` fails `integer`. Add
before each `validate` call:

```php
        // '' means "cleared", which is null, not zero.
        foreach (['finale_points_required', 'finale_wins_required', 'finale_venue_points_required'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
```

- [ ] **Step 4: The form inputs**

Add to both `create.blade.php` and `edit.blade.php`, after the date fields.
Model the markup on the existing `<x-field>` usage in those files:

```blade
                <div>
                    <h3 class="card__title">{{ __('Finale Qualification') }}</h3>

                    <p class="field__hint">
                        {{ __('A player must meet all three to qualify. Leave any of them empty while the number is still being decided — an empty field is not published, and is not enforced.') }}
                    </p>
                </div>

                <x-field name="finale_points_required" type="number" min="0"
                         :label="__('Season points required')"
                         :value="old('finale_points_required', $season->finale_points_required ?? null)" />

                <x-field name="finale_wins_required" type="number" min="0"
                         :label="__('Tournament wins required')"
                         :value="old('finale_wins_required', $season->finale_wins_required ?? null)" />

                <x-field name="finale_venue_points_required" type="number" min="0"
                         :label="__('Venue points required')"
                         :value="old('finale_venue_points_required', $season->finale_venue_points_required ?? null)" />
```

`create.blade.php` has no `$season`, which is why each `old()` uses `?? null`.
**Check `<x-field>` forwards arbitrary attributes** (`min`) before relying on
it — read `resources/views/components/field.blade.php`; if it does not, drop
`min` and let the `min:0` validation rule carry it alone.

- [ ] **Step 5: Test, then set thresholds through the real form**

- [ ] **Step 6: HAND-OFF — do not run git**

```
feat(seasons): let admins set the finale thresholds

Three optional numbers on the season form, validated on both store and
update -- the controller carries two identical validate blocks, and adding
to one only would have let an edit silently drop what create accepts.

An empty input posts '' rather than null, which fails an integer rule, so
each field is normalised to null before validation. Clearing a threshold
must return the season to "not published", not set a target of zero.
```

---

### Task 3: Venue points per player, per season

**Files:**
- Modify: `app/Http/Controllers/Poker/PokerSeasonController.php` (`show`)
- Test: `tests/Feature/FinaleQualificationTest.php`

**Interfaces produced:** each `$leaderboard` row gains `venue_points` (int), `qualified` (bool) and `unmet` (array<string>).

- [ ] **Step 1: Write the failing test**

```php
    public function test_venue_points_are_counted_only_inside_the_season_dates(): void
    {
        // venue_points has no season_id -- only an event_date -- so the season
        // boundary is the only attribution available. A row from before the
        // season must not count toward it.
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);
        $player = \App\Models\User::factory()->create();
        $season = $this->season(['finale_venue_points_required' => 10]);
        $venue = \App\Models\Venue::create(['name' => 'Room', 'address' => 'x']);

        \App\Models\VenuePoints::create([
            'user_id' => $player->id, 'user_name' => 'In Season',
            'venue_id' => $venue->id, 'amount' => 30,
            'event_date' => now()->subDays(3)->toDateString(),
        ]);

        \App\Models\VenuePoints::create([
            'user_id' => $player->id, 'user_name' => 'Before Season',
            'venue_id' => $venue->id, 'amount' => 999,
            'event_date' => now()->subYear()->toDateString(),
        ]);

        // Give the player a result so they appear in the standings at all.
        $this->resultFor($season, $player, place: 1, points: 500);

        $response = $this->actingAs($admin)->get(route('seasons.show', $season));

        $row = collect($response->viewData('leaderboard'))->firstWhere('user.id', $player->id);

        $this->assertSame(30, $row['venue_points'], 'Only the in-season row counts.');
    }
```

Add a `resultFor(PokerSeason $season, User $user, int $place, int $points)`
helper that creates a tournament in the season and a `PokerTournamentResult`
for that user — model it on the one in `tests/Feature/HomePageTest.php`, which
already solves the same problem.

- [ ] **Step 2: Run it and watch it fail**

- [ ] **Step 3: Sum venue points per player, in one query**

In `show()`, before the leaderboard is built:

```php
        // venue_points carries no season_id, only an event_date, so the
        // season's own dates are the only attribution available. One grouped
        // query rather than a lookup per player.
        //
        // A consequence worth knowing: editing a season's dates moves this
        // figure. That is inherent to the schema, not a bug in this query.
        $venuePoints = \App\Models\VenuePoints::query()
            ->whereBetween('event_date', [$season->start_date, $season->end_date])
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(amount) as total')
            ->pluck('total', 'user_id');
```

- [ ] **Step 4: Carry it into each leaderboard row**

Inside the existing `->map(...)` that builds each row, add:

```php
                    'venue_points' => (int) ($venuePoints[$results->first()->user_id] ?? 0),
```

and after the map, decorate each row with the verdict:

```php
        // The rule is evaluated here, once, so the view renders a verdict
        // rather than deriving one. A template that re-implements the
        // comparison is a second definition waiting to drift.
        $leaderboard = $leaderboard->map(function (array $row) use ($season) {
            $row['unmet'] = $season->unmetBy(
                points: $row['points'],
                wins: $row['wins'],
                venuePoints: $row['venue_points'],
            );
            $row['qualified'] = $row['unmet'] === [];

            return $row;
        });
```

**`$venuePoints` must be captured by the map's `use`** — check the existing
closure signature before editing.

- [ ] **Step 5: Test**

- [ ] **Step 6: HAND-OFF — do not run git**

```
feat(seasons): count venue points per player within the season

venue_points carries no season_id, only an event_date, so the season's own
dates are the only attribution available -- which means the figure moves if
a season's dates are later edited. That is inherent to the schema.

One grouped query rather than a lookup per player, and the qualification
verdict is computed in the controller so the view renders a result rather
than re-implementing the comparison.
```

---

### Task 4: Show it on the season page

**Files:**
- Modify: `resources/views/poker/seasons/show.blade.php`
- Modify: `resources/css/4-pages/_season-show.css`
- Test: `tests/Feature/FinaleQualificationTest.php`

- [ ] **Step 1: Write the failing tests**

```php
    public function test_the_page_states_the_targets(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);
        $season = $this->season([
            'finale_points_required' => 300,
            'finale_wins_required' => 2,
            'finale_venue_points_required' => 50,
        ]);

        $this->actingAs($admin)->get(route('seasons.show', $season))
            ->assertOk()
            ->assertSee('Finale Qualification')
            ->assertSee('300');
    }

    public function test_a_season_without_thresholds_says_so(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('seasons.show', $this->season()))
            ->assertOk()
            ->assertSee('not been set', false)
            ->assertDontSee('Qualified');
    }

    public function test_the_standings_name_what_a_player_is_short_on(): void
    {
        // A bare cross tells a player they failed without telling them what to
        // do about it.
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);
        $player = \App\Models\User::factory()->create();
        $season = $this->season(['finale_wins_required' => 5]);

        $this->resultFor($season, $player, place: 2, points: 100);

        $this->actingAs($admin)->get(route('seasons.show', $season))
            ->assertOk()
            ->assertSee('Wins', false);
    }
```

- [ ] **Step 2: Run them and watch them fail**

- [ ] **Step 3: The targets panel**

Above the standings card, rendered either way — a season with no thresholds
must say so rather than showing nothing, or a reader cannot tell the rules from
a rendering failure:

```blade
        <x-card :title="__('Finale Qualification')">
            @if ($season->hasThresholds())
                <p class="field__hint">
                    {{ __('A player must meet all three to play the finale.') }}
                </p>

                <div class="l-grid">
                    <x-stat :label="__('Season points')"
                            :value="$season->finale_points_required !== null ? number_format($season->finale_points_required) : __('Not set')" />

                    <x-stat :label="__('Tournament wins')"
                            :value="$season->finale_wins_required !== null ? (string) $season->finale_wins_required : __('Not set')" />

                    <x-stat :label="__('Venue points')"
                            :value="$season->finale_venue_points_required !== null ? number_format($season->finale_venue_points_required) : __('Not set')" />
                </div>
            @else
                <x-empty-state :title="__('No thresholds yet')">
                    {{ __('The qualification thresholds for this season have not been set. Until they are, no player is measured against them.') }}
                </x-empty-state>
            @endif
        </x-card>
```

- [ ] **Step 4: The standings columns**

The head slot currently has Rank, Player, Points, Played, Won. Add two:

```blade
                            <th scope="col" class="table__num">{{ __('Venue pts') }}</th>
                            <th scope="col">{{ __('Finale') }}</th>
```

and in the row, after the `Won` cell:

```blade
                                <td class="table__num">{{ $row['venue_points'] }}</td>

                                <td>
                                    @if (! $season->hasThresholds())
                                        &mdash;
                                    @elseif ($row['qualified'])
                                        <x-badge variant="open">{{ __('Qualified') }}</x-badge>
                                    @else
                                        {{-- The reason, not a bare cross: a player
                                             who is short needs to know on what. --}}
                                        <x-badge>{{ __('Needs :what', [
                                            'what' => collect($row['unmet'])->map(fn ($k) => match ($k) {
                                                'points' => __('points'),
                                                'wins' => __('wins'),
                                                'venue_points' => __('venue points'),
                                            })->join(', ', __(' and ')),
                                        ]) }}</x-badge>
                                    @endif
                                </td>
```

**The `@empty` / empty-state `colspan` in this table must grow by 2.** Find it
before editing — a stale colspan misaligns silently.

- [ ] **Step 5: Test, build, screenshot**

```bash
php artisan test
npm run build
```

Screenshot `/seasons/{id}` as an admin in **both themes**, with a season that
has thresholds and one that does not. Snap-confined Chromium cannot write to
`/tmp` or a dot-directory — use `$HOME/ftap-shots`.

Check the standings table does not overflow horizontally at 375px now that it
has seven columns. Headless Chromium enforces a 500px minimum window width, so
test 375 through a same-origin iframe or the check is meaningless.

- [ ] **Step 6: HAND-OFF — do not run git**

```
feat(seasons): show finale qualification on the season page

States the three targets, and marks each player in the standings against
them. A player who falls short is told which criterion they are short on
rather than shown a bare cross.

A season with no thresholds says so explicitly rather than rendering
nothing, so a reader can tell "not decided yet" from a rendering failure.
```

---

### Task 5: Publish the thresholds on the home page

The home page's season section currently tells visitors: *"The exact thresholds
are still being set and will be published here once they are."* Once a season
carries real numbers that sentence is false, and the page that made the promise
is the page that has to keep it.

**Files:**
- Modify: `resources/views/home.blade.php` (the Season Finale panel)
- Modify: `tests/Feature/HomePageTest.php`
- Test: `tests/Feature/HomePageTest.php`

**Interfaces consumed:** `PokerSeason::hasThresholds()` from Task 1. `$currentSeason` is already passed to this view — no route change is needed.

- [ ] **Step 1: Write the failing tests**

```php
    public function test_the_home_page_publishes_the_thresholds_once_they_are_set(): void
    {
        $season = $this->season();
        $season->update([
            'finale_points_required' => 300,
            'finale_wins_required' => 2,
            'finale_venue_points_required' => 50,
        ]);

        $this->get('/')->assertOk()
            ->assertSee('300')
            ->assertDontSee('still being set', false);
    }

    public function test_the_home_page_admits_when_the_thresholds_are_not_set(): void
    {
        // The honest state, and the one every season is in until an
        // administrator fills the numbers in.
        $this->season();

        $this->get('/')->assertOk()->assertSee('still being set', false);
    }

    public function test_a_partially_set_season_shows_only_the_numbers_it_has(): void
    {
        // A season may publish a points target before the other two are
        // decided. Rendering "0" for the undecided ones would state a target
        // nobody chose and that everybody has already met.
        $season = $this->season();
        $season->update(['finale_points_required' => 300]);

        $this->get('/')->assertOk()
            ->assertSee('300')
            ->assertSee('not set yet', false);
    }
```

`season()` here is `HomePageTest`'s own existing helper — it already creates a
current season. Do not add a second one.

- [ ] **Step 2: Run them and watch them fail**

Run: `php artisan test --filter=HomePageTest`
Expected: the three new cases FAIL, and
`test_the_finale_criteria_are_named` still passes — it pins the sentence naming
the three criteria, which stays.

- [ ] **Step 3: Replace the panel's second paragraph**

In `home.blade.php`, the Season Finale panel currently reads:

```blade
                {{-- Said plainly rather than left vague. The thresholds are not
                     set yet, and a page that implies a number nobody has chosen
                     is worse than one that admits the number is coming. --}}
                <p class="p-panel__text">
                    {{ __('The exact thresholds are still being set and will be published here once they are.') }}
                </p>
```

Replace that block with:

```blade
                {{-- The numbers once they exist, the admission until they do.
                     This page promised to publish them here, so it is the page
                     that has to keep that promise. --}}
                @if ($currentSeason && $currentSeason->hasThresholds())
                    <dl class="p-finale">
                        <div class="p-finale__row">
                            <dt class="p-finale__label">{{ __('Season points') }}</dt>
                            <dd class="p-finale__value">
                                {{ $currentSeason->finale_points_required !== null
                                    ? number_format($currentSeason->finale_points_required)
                                    : __('not set yet') }}
                            </dd>
                        </div>

                        <div class="p-finale__row">
                            <dt class="p-finale__label">{{ __('Tournament wins') }}</dt>
                            <dd class="p-finale__value">
                                {{ $currentSeason->finale_wins_required !== null
                                    ? $currentSeason->finale_wins_required
                                    : __('not set yet') }}
                            </dd>
                        </div>

                        <div class="p-finale__row">
                            <dt class="p-finale__label">{{ __('Venue points') }}</dt>
                            <dd class="p-finale__value">
                                {{ $currentSeason->finale_venue_points_required !== null
                                    ? number_format($currentSeason->finale_venue_points_required)
                                    : __('not set yet') }}
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="p-panel__text">
                        {{ __('The exact thresholds are still being set and will be published here once they are.') }}
                    </p>
                @endif
```

**Each figure is guarded individually, not just the block.** `hasThresholds()`
is true when *any* of the three is set, so a season with only a points target
reaches this branch — and printing `number_format(null)` there would render `0`,
stating a target nobody chose and that everybody has already met.

- [ ] **Step 4: Style the list**

`.p-finale` is new. Check it does not already exist
(`grep -rn "p-finale" resources/css/`), then add to
`resources/css/5-public/_register.css`, near the other `.p-panel` rules:

```css
/* The published thresholds, inside the finale panel. A definition list rather
   than three .p-stat tiles: this sits on the gradient panel, where the tile's
   own surface and hairline would fight the ground it is drawn on. */
.p-finale__row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--space-4);
    padding-block: var(--space-2);
}

.p-finale__row + .p-finale__row {
    /* Against the panel's own ink rather than --c-border: the hairline token is
       tuned for --c-surface and disappears on this gradient. */
    border-block-start: var(--border-width) solid rgb(255 255 255 / 0.18);
}

.p-finale__label {
    font-size: var(--step--1);
    opacity: 0.85;
}

.p-finale__value {
    font-family: var(--font-mono);
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}
```

**Check the contrast of that 0.18 white rule and of `opacity: 0.85` on the
label against `--gradient-panel`.** This project has twice shipped panel text
that lost AA to an opacity — the label must still clear 4.5:1 against both
stops of the ramp, and if it does not, drop the opacity rather than the size.

- [ ] **Step 5: Test, build, screenshot**

```bash
php artisan test
npm run build
```

Screenshot the home page in **both themes**, with a season that has all three
thresholds, one that has a single threshold, and one that has none. The
three-branch behaviour is the whole point of this task and only one branch is
visible at a time.

- [ ] **Step 6: HAND-OFF — do not run git**

```
feat(home): publish the finale thresholds once a season sets them

The season section promised visitors that the exact thresholds would be
published here once they were decided. Now that a season can carry them,
this is the page that keeps that promise.

Each figure is guarded on its own, not just the block. hasThresholds() is
true when any one of the three is set, so a season with only a points target
reaches this branch -- and number_format(null) would render 0 there, stating
a target nobody chose and that everybody has already met.

A season with none keeps the original admission, which stays the honest
answer until an administrator fills the numbers in.
```

## Risks

- **Venue points move if season dates change.** Inherent to a schema with no
  `season_id` on `venue_points`. Worth telling the owner rather than burying:
  editing a season's dates silently re-scores every player against that
  threshold.
- **`venue_points` is empty today.** A season that publishes a venue-points
  threshold will show nobody qualifying until those rows exist. That is correct
  behaviour and will look like a bug.
- **The standings table grows to seven columns.** It already scrolls inside its
  own container on narrow screens; confirm that still holds rather than assuming.
- **`PokerSeasonController@show` is the app's densest method.** It already
  computes totals, a leaderboard and venue stats. Adding to it is right for now,
  but if it grows again it wants extracting before it does.

## Out of scope

- Enforcing qualification anywhere — this feature *reports*, it does not gate.
  Nothing stops an unqualified player entering the finale.
- Notifying players when they qualify.
- Thresholds that vary per player or per tier.
- Showing per-player qualification on the public home page. Task 5 publishes the
  season's *targets* there; who meets them stays behind the login, on the season
  page.
