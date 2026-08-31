# Phase 0 — Correctness & Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix six correctness and cleanup defects on the existing Tailwind markup, so that Phase 1 converts stable views exactly once.

**Architecture:** Six independent fixes plus a route smoke test, sequenced so the application works after every task. Route relocation happens *before* admin gating, because gating `/poker` first would break self-registration between tasks.

**Tech Stack:** Laravel 12, PHP 8.2, PHPUnit 11, SQLite (`:memory:` in tests), Blade, Vite.

**Spec:** `docs/superpowers/specs/2026-08-30-design-system-design.md` (sections 5.1–5.6, 8)

## Global Constraints

- **Never run `git` commands.** The user runs all git operations manually. Every task ends with a **Checkpoint** naming the files to stage and a suggested message — present it, do not execute it.
- Tests are PHPUnit class-style (not Pest): `class XTest extends TestCase`, `use RefreshDatabase;`, methods named `test_snake_case`.
- Test env is already configured in `phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `MAIL_MAILER=array`.
- `User::factory()` does **not** set `is_admin`; it defaults to `false`. Pass `['is_admin' => true]` explicitly for admins.
- Models use ULID primary keys (`HasUlids`). Never assume integer ids.
- Run the full suite with `php artisan test`. Run one file with `php artisan test --filter=ClassName`.
- Do not touch styling in this phase. Tailwind classes stay exactly as they are; Phase 1 replaces them.
- Copy rules from spec §7 apply to any user-facing string added here: plain language, active voice, errors say what happened and how to fix it.

---

## File Structure

**Created**
| File | Responsibility |
|---|---|
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Single admin authorisation check, replacing seven repeated `abort_unless` calls |
| `app/Http/Controllers/ContactController.php` | Accepts contact and sponsorship submissions |
| `app/Mail/ContactSubmission.php` | Mailable carrying one submission |
| `resources/views/mail/contact-submission.blade.php` | Markdown mail body |
| `tests/Feature/PointsStructurePageTest.php` | Regression guard for the `is_active` crash |
| `tests/Feature/AdminAccessTest.php` | Admin gating across `/poker` and `/users` |
| `tests/Feature/TournamentScheduleTest.php` | `scheduled_at` vs `start_time` semantics |
| `tests/Feature/ContactFormTest.php` | Contact submission, validation, honeypot |
| `tests/Feature/RouteSmokeTest.php` | Every GET route responds without a 5xx |

**Modified**
| File | Change |
|---|---|
| `routes/web.php` | Fix `is_active`; relocate 4 player routes; apply `admin` middleware; add `POST /contact` |
| `bootstrap/app.php` | Register the `admin` middleware alias |
| `app/Http/Controllers/UserController.php` | Remove 7 `abort_unless` calls |
| `app/Http/Controllers/DashboardController.php` | Upcoming filter keys off `start_time` |
| `app/Http/Controllers/Poker/PokerTournamentController.php` | `$isPast` keys off `start_time`; validation; error copy |
| `config/mail.php` | Add `league_contact` |
| `.env`, `.env.example` | `APP_NAME`, `LEAGUE_CONTACT_EMAIL` |
| `resources/views/contact.blade.php`, `resources/views/about/index.blade.php` | Wire forms to the new endpoint |
| 8 Blade views | Route rename call sites |
| `package.json`, `vite.config.js`, `resources/js/app.ts` | Remove Vue/PrimeVue |
| `README.md` | Real project readme |

**Deleted**
`resources/views/welcome.blade.php`, `resources/views/about/mission.blade.php`, `resources/views/about/sponsors.blade.php`, `resources/js/components/Dashboard.vue`, `resources/js/vue-shims.d.ts`

---

## Task 1: Fix the `is_active` crash

`routes/web.php:63` queries a column that does not exist. `/rules/points-structure` throws a `QueryException` for every visitor. This is the only `is_active` reference in the codebase.

**Files:**
- Modify: `routes/web.php:63`
- Test: `tests/Feature/PointsStructurePageTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: nothing consumed by later tasks

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PointsStructurePageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointsStructurePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_structure_page_loads_when_a_current_season_exists()
    {
        PointsStructure::create(['place' => 1, 'points' => 100]);

        PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $response = $this->get(route('rules.points-structure'));

        $response->assertStatus(200);
    }

    public function test_points_structure_page_loads_when_no_season_exists()
    {
        PointsStructure::create(['place' => 1, 'points' => 100]);

        $response = $this->get(route('rules.points-structure'));

        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PointsStructurePageTest`
Expected: `test_points_structure_page_shows_the_current_season` FAILS on the `assertViewHas('currentSeason', ...)` assertion, because `$currentSeason` is `null`.

**Do not expect a SQL error.** Laravel's SQLite grammar emits `where "is_active" = ?`, and SQLite's double-quoted-string-literal misfeature degrades the unknown quoted identifier into the string `'is_active'` — the query returns 0 rows rather than raising `no such column`. (A *bare* identifier, `where is_active = 1`, does raise it; Eloquent never emits one.) So the bug is silent on SQLite: the season is never found and the page's top-3 leaders panel never renders. It would throw on MySQL/PostgreSQL, making this a latent crash if the driver ever changes. This is why the test must assert the view data, not merely a 200.

- [ ] **Step 3: Apply the fix**

In `routes/web.php`, inside the `rules.points-structure` closure, change:

```php
$currentSeason = \App\Models\PokerSeason::where('is_active', true)->first();
```

to:

```php
$currentSeason = \App\Models\PokerSeason::where('is_current', true)->first();
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PointsStructurePageTest`
Expected: 2 passed.

- [ ] **Step 5: Confirm nothing else references the wrong column**

Run: `grep -rn "is_active" app/ routes/ resources/ database/`
Expected: no output.

- [ ] **Step 6: Checkpoint — hand off for commit**

Stage: `routes/web.php tests/Feature/PointsStructurePageTest.php`
Suggested message: `fix: query is_current instead of nonexistent is_active on points structure page`

Present this to the user. Do not run git.

---

## Task 2: Relocate player-facing routes out of `/poker`

Spec §5.2. `/poker` becomes admin-only in Task 3. Four endpoints players need must move out first, or self-registration and every "details" link breaks.

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/dashboard.blade.php:62,127,134`
- Modify: `resources/views/events.blade.php:85,90,122`
- Modify: `resources/views/rules/points-structure.blade.php:99`
- Modify: `resources/views/poker/tournaments/index.blade.php:42`
- Modify: `resources/views/poker/tournaments/show.blade.php:81,100,110,273`
- Modify: `resources/views/poker/seasons/index.blade.php:48`
- Modify: `resources/views/poker/venues/show.blade.php:183`
- Test: `tests/Feature/PokerTournamentRegistrantTest.php` (existing — update route names)

**Interfaces:**
- Consumes: nothing
- Produces: route names `tournaments.show`, `tournaments.register`, `tournaments.unregister`, `seasons.show`. Task 3 and Task 8 depend on these names existing.

- [ ] **Step 1: Find every call site**

Run:
```bash
grep -rn "poker.tournaments.show\|poker.tournaments.register\|poker.tournaments.unregister\|poker.seasons.show" resources/ app/ tests/
```
Expected: the 12 view call sites listed above, plus any test references. Record the full list before editing — the line numbers above are from 2026-08-30 and may have drifted.

- [ ] **Step 2: Move the routes**

In `routes/web.php`, inside the existing `Route::middleware('auth')->group(...)`, **above** the `poker` prefix group, add:

```php
    // Player-facing tournament and season views. Deliberately outside the
    // /poker prefix, which is admin-only.
    Route::get('/tournaments/{tournament}', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'show'])
        ->name('tournaments.show');
    Route::post('/tournaments/{tournament}/register', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'register'])
        ->name('tournaments.register');
    Route::delete('/tournaments/{tournament}/unregister', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'unregister'])
        ->name('tournaments.unregister');
    Route::get('/seasons/{season}', [\App\Http\Controllers\Poker\PokerSeasonController::class, 'show'])
        ->name('seasons.show');
```

Then inside the `poker` prefix group, replace these four lines (the register and unregister routes sit directly below the tournaments resource):

```php
        Route::resource('seasons', \App\Http\Controllers\Poker\PokerSeasonController::class);
        Route::resource('tournaments', \App\Http\Controllers\Poker\PokerTournamentController::class);
        Route::post('tournaments/{tournament}/register', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'register'])->name('tournaments.register');
        Route::delete('tournaments/{tournament}/unregister', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'unregister'])->name('tournaments.unregister');
```

with:

```php
        Route::resource('seasons', \App\Http\Controllers\Poker\PokerSeasonController::class)->except(['show']);
        Route::resource('tournaments', \App\Http\Controllers\Poker\PokerTournamentController::class)->except(['show']);
```

`poker.venues.show` stays where it is — it is an admin report, not a player view.

- [ ] **Step 3: Update every call site**

Run:
```bash
grep -rl "poker\.tournaments\.\(show\|register\|unregister\)\|poker\.seasons\.show" resources/ | \
  xargs sed -i \
    -e "s/poker\.tournaments\.show/tournaments.show/g" \
    -e "s/poker\.tournaments\.register/tournaments.register/g" \
    -e "s/poker\.tournaments\.unregister/tournaments.unregister/g" \
    -e "s/poker\.seasons\.show/seasons.show/g"
```

Then verify nothing was missed:
```bash
grep -rn "poker.tournaments.show\|poker.tournaments.register\|poker.tournaments.unregister\|poker.seasons.show" resources/ app/ tests/
```
Expected: no output.

- [ ] **Step 4: Verify the route table**

Run: `php artisan route:list --name=tournaments`
Expected: `tournaments.show` at `GET tournaments/{tournament}`, `tournaments.register` at `POST tournaments/{tournament}/register`, `tournaments.unregister` at `DELETE tournaments/{tournament}/unregister`, and `poker.tournaments.{index,create,store,edit,update,destroy}` — **no** `poker.tournaments.show`.

Run: `php artisan route:list --name=seasons`
Expected: `seasons.show` at `GET seasons/{season}`, and `poker.seasons.{index,create,store,edit,update,destroy}`.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: all green. If `PokerTournamentRegistrantTest` fails on a route name, update the route name in that test to match the new name — do not change the assertion's intent.

- [ ] **Step 6: Checkpoint — hand off for commit**

Stage: `routes/web.php resources/views tests/`
Suggested message: `refactor: move player-facing tournament and season routes out of the /poker prefix`

---

## Task 3: Admin middleware, applied to `/poker` and `/users`

Spec §5.2. Today any authenticated user can create, edit and delete every league record. Only `/users` is gated, via five repeated `abort_unless` calls.

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsAdmin.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/UserController.php` (remove 5 `abort_unless` lines)
- Test: `tests/Feature/AdminAccessTest.php`

**Interfaces:**
- Consumes: route names from Task 2
- Produces: middleware alias `admin`, class `App\Http\Middleware\EnsureUserIsAdmin`. Task 8 relies on `/poker` returning 403 for non-admins.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AdminAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every index route inside the admin-only /poker prefix.
     */
    public static function adminRouteProvider(): array
    {
        return [
            'seasons' => ['poker.seasons.index'],
            'venues' => ['poker.venues.index'],
            'tournaments' => ['poker.tournaments.index'],
            'results' => ['poker.results.index'],
            'registrants' => ['poker.registrants.index'],
            'venue points' => ['poker.venue-points.index'],
            'points structure' => ['poker.points-structure.index'],
            'users' => ['users.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminRouteProvider')]
    public function test_non_admin_is_forbidden_from_admin_routes(string $routeName)
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route($routeName))->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminRouteProvider')]
    public function test_admin_can_reach_admin_routes(string $routeName)
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route($routeName))->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login_from_admin_routes()
    {
        $this->get(route('poker.seasons.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_can_still_view_a_tournament()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($user)->get(route('tournaments.show', $tournament))->assertStatus(200);
    }

    public function test_non_admin_can_still_view_a_season()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $this->actingAs($user)->get(route('seasons.show', $season))->assertStatus(200);
    }

    public function test_non_admin_can_still_register_and_unregister()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($user)
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('tournaments.unregister', $tournament))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
        ]);
    }

    private function makeTournament(): PokerTournament
    {
        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Weekly Freezeout',
            'scheduled_at' => now()->addDays(7),
            'start_time' => now()->addDays(7)->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AdminAccessTest`
Expected: the seven `poker.*` cases of `test_non_admin_is_forbidden_from_admin_routes` FAIL with `Expected status code 403 but received 200`. The `users.index` case already passes (it has `abort_unless`).

- [ ] **Step 3: Create the middleware**

Create `app/Http/Middleware/EnsureUserIsAdmin.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Allow the request through only for authenticated league administrators.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the alias**

In `bootstrap/app.php`, replace the empty middleware closure:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
```

with:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
```

- [ ] **Step 5: Apply it to the routes**

In `routes/web.php`, change the poker group opener from:

```php
    Route::prefix('poker')->name('poker.')->group(function () {
```

to:

```php
    Route::middleware('admin')->prefix('poker')->name('poker.')->group(function () {
```

and change the users resource from:

```php
    Route::resource('users', \App\Http\Controllers\UserController::class);
```

to:

```php
    Route::resource('users', \App\Http\Controllers\UserController::class)->middleware('admin');
```

Both already sit inside the outer `Route::middleware('auth')` group, so `auth` still runs first and guests are redirected to login rather than shown a 403.

- [ ] **Step 6: Remove the now-redundant checks**

In `app/Http/Controllers/UserController.php`, delete all five occurrences of:

```php
        abort_unless(auth()->user()->is_admin, 403);
```

Leave every other line, including the self-deletion guard in `destroy()`:

```php
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }
```

Verify: `grep -c "abort_unless" app/Http/Controllers/UserController.php` returns `0`.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=AdminAccessTest`
Expected: all pass.

Run: `php artisan test`
Expected: all green. `UserManagementTest` still passes — it already creates admins explicitly. If any other test now gets a 403, add `['is_admin' => true]` to that test's `User::factory()->create(...)`.

- [ ] **Step 8: Checkpoint — hand off for commit**

Stage: `app/Http/Middleware/EnsureUserIsAdmin.php bootstrap/app.php routes/web.php app/Http/Controllers/UserController.php tests/Feature/AdminAccessTest.php`
Suggested message: `feat: gate the /poker admin area and user management behind an admin middleware`

---

## Task 4: `scheduled_at` and `start_time` semantics

Spec §5.3. These are two real concepts — the tournament form labels them "Registration Closes (Scheduled At)" and "Start Date & Time" — but the code uses them interchangeably.

Canonical meaning: **`scheduled_at` is the registration cutoff. `start_time` is when play begins.**

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php` (upcoming filter)
- Modify: `app/Http/Controllers/Poker/PokerTournamentController.php` (`$isPast`, validation, error copy)
- Test: `tests/Feature/TournamentScheduleTest.php`

**Interfaces:**
- Consumes: route names from Task 2, admin gating from Task 3
- Produces: nothing consumed by later tasks

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TournamentScheduleTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_closes_at_scheduled_at_even_though_play_has_not_started()
    {
        $user = User::factory()->create(['is_admin' => false]);

        // Registration closed an hour ago; cards go in the air in an hour.
        $tournament = $this->makeTournament(
            scheduledAt: now()->subHour(),
            startTime: now()->addHour(),
        );

        $response = $this->actingAs($user)->post(route('tournaments.register', $tournament));

        $response->assertSessionHas('error', 'Registration has closed for this tournament.');
        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_dashboard_lists_a_tournament_whose_registration_has_closed_but_has_not_started()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $tournament = $this->makeTournament(
            scheduledAt: now()->subHour(),
            startTime: now()->addHour(),
        );

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('upcomingTournaments', function ($tournaments) use ($tournament) {
            return $tournaments->contains('id', $tournament->id);
        });
    }

    public function test_dashboard_excludes_a_tournament_that_has_already_started()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $tournament = $this->makeTournament(
            scheduledAt: now()->subHours(3),
            startTime: now()->subHours(2),
        );

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertViewHas('upcomingTournaments', function ($tournaments) use ($tournament) {
            return ! $tournaments->contains('id', $tournament->id);
        });
    }

    public function test_start_time_cannot_precede_registration_close()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('poker.tournaments.store'), [
            'name' => 'Weekly Freezeout',
            'scheduled_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'start_time' => now()->addDays(6)->format('Y-m-d H:i:s'),
            'venue_id' => $venue->id,
        ]);

        $response->assertSessionHasErrors('start_time');
        $this->assertEquals(0, PokerTournament::count());
    }

    private function makeTournament(\DateTimeInterface $scheduledAt, \DateTimeInterface $startTime): PokerTournament
    {
        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Weekly Freezeout',
            'scheduled_at' => $scheduledAt,
            'start_time' => $startTime,
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TournamentScheduleTest`
Expected:
- `test_registration_closes_at_scheduled_at...` FAILS — the session error is currently the old wording.
- `test_dashboard_lists_a_tournament_whose_registration_has_closed...` FAILS — the dashboard currently filters on `scheduled_at`, so this tournament is excluded.
- `test_start_time_cannot_precede_registration_close` FAILS — no such validation rule yet.

- [ ] **Step 3: Fix the dashboard filter**

In `app/Http/Controllers/DashboardController.php`, change:

```php
        $upcomingTournaments = PokerTournament::with(['venue', 'registrants'])
            ->where('scheduled_at', '>', $now)
            ->orderBy('scheduled_at', 'asc')
            ->take(5)
            ->get();
```

to:

```php
        $upcomingTournaments = PokerTournament::with(['venue', 'registrants'])
            ->where('start_time', '>', $now)
            ->orderBy('start_time', 'asc')
            ->take(5)
            ->get();
```

- [ ] **Step 4: Fix `$isPast` and the registration copy**

In `app/Http/Controllers/Poker/PokerTournamentController.php`:

In `show()`, change:

```php
        $isPast = \Illuminate\Support\Carbon::parse($tournament->scheduled_at)->isPast();
```

to:

```php
        // "Past" means play has begun, which is start_time — not the
        // registration cutoff held in scheduled_at.
        $isPast = \Illuminate\Support\Carbon::parse($tournament->start_time)->isPast();
```

In `register()`, change:

```php
        if (!$isAdmin && \Illuminate\Support\Carbon::parse($tournament->scheduled_at)->isPast()) {
            return back()->with('error', 'Cannot register for a tournament that has already started or passed.');
        }
```

to:

```php
        if (!$isAdmin && \Illuminate\Support\Carbon::parse($tournament->scheduled_at)->isPast()) {
            return back()->with('error', 'Registration has closed for this tournament.');
        }
```

In `unregister()`, change:

```php
        if (\Illuminate\Support\Carbon::parse($tournament->scheduled_at)->isPast()) {
            return back()->with('error', 'Cannot unregister from a tournament that has already started or passed.');
        }
```

to:

```php
        if (\Illuminate\Support\Carbon::parse($tournament->scheduled_at)->isPast()) {
            return back()->with('error', 'Registration has closed, so this entry can no longer be withdrawn.');
        }
```

Both guards stay on `scheduled_at` — that is correct, it is the cutoff. Only the wording changes, because "has already started" was describing the wrong field.

- [ ] **Step 5: Add the ordering validation**

In `app/Http/Controllers/Poker/PokerTournamentController.php`, in **both** `store()` and `update()`, change:

```php
            'start_time' => 'required|date',
```

to:

```php
            'start_time' => 'required|date|after_or_equal:scheduled_at',
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=TournamentScheduleTest`
Expected: 4 passed.

Run: `php artisan test`
Expected: all green. `PokerTournamentTest` passes both fields as the same timestamp, which satisfies `after_or_equal`.

- [ ] **Step 7: Checkpoint — hand off for commit**

Stage: `app/Http/Controllers/DashboardController.php app/Http/Controllers/Poker/PokerTournamentController.php tests/Feature/TournamentScheduleTest.php`
Suggested message: `fix: treat scheduled_at as the registration cutoff and start_time as when play begins`

---

## Task 5: Contact and sponsorship form backend

Spec §5.4. Both forms currently post to `action="#"` and silently do nothing.

Existing field names — do not rename them without updating both views:
- `contact.blade.php`: `name`, `email`, `subject` (a `<select>` whose options have no `value` attributes), `message`
- `about/index.blade.php`: `name`, `email`, `message` (no subject field)

This task unifies them on `name`, `email`, `topic`, `message`.

**Files:**
- Create: `app/Http/Controllers/ContactController.php`
- Create: `app/Mail/ContactSubmission.php`
- Create: `resources/views/mail/contact-submission.blade.php`
- Modify: `config/mail.php`
- Modify: `.env`, `.env.example`
- Modify: `routes/web.php`
- Modify: `resources/views/contact.blade.php:69` (form tag, select options, honeypot)
- Modify: `resources/views/about/index.blade.php:89` (form tag, hidden topic, honeypot)
- Test: `tests/Feature/ContactFormTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: route `contact.store` (`POST /contact`); `App\Mail\ContactSubmission` with constructor `(string $senderName, string $senderEmail, string $topic, string $body)`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ContactFormTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Mail\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_send_a_general_message()
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), [
            'name' => 'Mara Vasquez',
            'email' => 'mara@example.com',
            'topic' => 'general',
            'message' => 'When does Season 6 start?',
        ]);

        $response->assertSessionHas('status');

        Mail::assertSent(ContactSubmission::class, function (ContactSubmission $mail) {
            return $mail->senderEmail === 'mara@example.com'
                && $mail->topic === 'general'
                && $mail->hasTo(config('mail.league_contact'));
        });
    }

    public function test_a_sponsorship_enquiry_uses_its_own_subject_line()
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Joseph Okonkwo',
            'email' => 'joseph@example.com',
            'topic' => 'sponsorship',
            'message' => 'We would like to sponsor the season finale.',
        ]);

        Mail::assertSent(ContactSubmission::class, function (ContactSubmission $mail) {
            return $mail->envelope()->subject === 'Sponsorship enquiry from Joseph Okonkwo';
        });
    }

    public function test_the_form_requires_a_name_email_topic_and_message()
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'topic', 'message']);
        Mail::assertNothingSent();
    }

    public function test_an_unknown_topic_is_rejected()
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), [
            'name' => 'Mara Vasquez',
            'email' => 'mara@example.com',
            'topic' => 'not-a-real-topic',
            'message' => 'Hello.',
        ]);

        $response->assertSessionHasErrors('topic');
        Mail::assertNothingSent();
    }

    public function test_a_filled_honeypot_is_silently_discarded()
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'topic' => 'general',
            'message' => 'Buy things.',
            'company' => 'Bot Industries',
        ]);

        // The bot sees the same confirmation a person sees.
        $response->assertSessionHas('status');
        Mail::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContactFormTest`
Expected: all 5 FAIL with `Route [contact.store] not defined.`

- [ ] **Step 3: Add the recipient config**

In `config/mail.php`, add a top-level key inside the returned array (next to `'from' => [...]`):

```php
    /*
    |--------------------------------------------------------------------------
    | League Contact Address
    |--------------------------------------------------------------------------
    |
    | Where contact and sponsorship form submissions are delivered.
    |
    */

    'league_contact' => env('LEAGUE_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
```

Add to both `.env` and `.env.example`, below the `MAIL_` block:

```
LEAGUE_CONTACT_EMAIL="hello@example.com"
```

- [ ] **Step 4: Create the Mailable**

Create `app/Mail/ContactSubmission.php`:

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactSubmission extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Subject lines by topic. Keys must match the validation rule in
     * ContactController::store().
     */
    private const SUBJECTS = [
        'general' => 'Contact form message from :name',
        'registration' => 'League registration question from :name',
        'partnership' => 'Partnership enquiry from :name',
        'support' => 'Technical support request from :name',
        'sponsorship' => 'Sponsorship enquiry from :name',
    ];

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $topic,
        public string $body,
    ) {
    }

    public function envelope(): Envelope
    {
        $template = self::SUBJECTS[$this->topic] ?? self::SUBJECTS['general'];

        return new Envelope(
            subject: str_replace(':name', $this->senderName, $template),
            replyTo: [new Address($this->senderEmail, $this->senderName)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-submission');
    }
}
```

- [ ] **Step 5: Create the mail view**

Create `resources/views/mail/contact-submission.blade.php`:

```blade
<x-mail::message>
# New {{ $topic }} message

**From:** {{ $senderName }} ({{ $senderEmail }})

{{ $body }}

Reply directly to this email to reach the sender.
</x-mail::message>
```

Run `php artisan vendor:publish --tag=laravel-mail` first if `x-mail::message` is not resolvable.

- [ ] **Step 6: Create the controller**

Create `app/Http/Controllers/ContactController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Mail\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a field no person sees and no person fills in. Answer
        // exactly as we would a real submission so bots learn nothing.
        if ($request->filled('company')) {
            return back()->with('status', 'Thanks — your message is on its way.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'topic' => 'required|in:general,registration,partnership,support,sponsorship',
            'message' => 'required|string|max:5000',
        ]);

        Mail::to(config('mail.league_contact'))->send(new ContactSubmission(
            senderName: $validated['name'],
            senderEmail: $validated['email'],
            topic: $validated['topic'],
            body: $validated['message'],
        ));

        return back()->with('status', 'Thanks — your message is on its way.');
    }
}
```

- [ ] **Step 7: Add the route**

In `routes/web.php`, directly below the existing `contact` GET route, add:

```php
Route::post('/contact', [\App\Http\Controllers\ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=ContactFormTest`
Expected: 5 passed.

- [ ] **Step 9: Wire up the contact form**

In `resources/views/contact.blade.php`, change line 69 from:

```blade
<form action="#" method="POST" class="space-y-5">
```

to:

```blade
<form action="{{ route('contact.store') }}" method="POST" class="space-y-5">
    @csrf
    <input type="text" name="company" id="company" tabindex="-1" autocomplete="off" hidden>
```

The honeypot uses the HTML `hidden` attribute, not CSS — this keeps it working in Phase 1 when Tailwind is removed.

Give the `<select>` options real values and rename it to `topic`:

```blade
<select name="topic" id="topic" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500 transition-colors">
    <option value="general">General Inquiry</option>
    <option value="registration">League Registration</option>
    <option value="partnership">Commercial Partnership</option>
    <option value="support">Technical Support</option>
</select>
```

Update the `<label for="subject">` above it to `for="topic"`.

Directly above the submit button, add the confirmation and error output:

```blade
    @if (session('status'))
        <p class="text-sm font-semibold text-green-600 dark:text-green-400">{{ session('status') }}</p>
    @endif

    @error('message')
        <p class="text-sm font-semibold text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
```

- [ ] **Step 10: Wire up the sponsorship form**

In `resources/views/about/index.blade.php`, change line 89 from:

```blade
<form action="#" method="POST" class="space-y-5">
```

to:

```blade
<form action="{{ route('contact.store') }}" method="POST" class="space-y-5">
    @csrf
    <input type="hidden" name="topic" value="sponsorship">
    <input type="text" name="company" id="sponsor_company" tabindex="-1" autocomplete="off" hidden>
```

Add the same `session('status')` and `@error('message')` blocks above its submit button.

- [ ] **Step 11: Verify both forms end-to-end**

Run: `php artisan serve` in one terminal, then submit each form in a browser.
Expected: the page returns with "Thanks — your message is on its way." Check `storage/logs/laravel.log` — the rendered email is written there because `MAIL_MAILER=log`. Confirm the subject line differs between the two forms.

Run: `grep -rn 'action="#"' resources/views/`
Expected: no output.

- [ ] **Step 12: Checkpoint — hand off for commit**

Stage: `app/Http/Controllers/ContactController.php app/Mail/ContactSubmission.php resources/views/mail/ config/mail.php .env.example routes/web.php resources/views/contact.blade.php resources/views/about/index.blade.php tests/Feature/ContactFormTest.php`
Suggested message: `feat: deliver contact and sponsorship form submissions by email`

Note: `.env` is gitignored. Remind the user to add `LEAGUE_CONTACT_EMAIL` to any deployed environment.

---

## Task 6: Remove the Vue / PrimeVue stack

Spec §5.5. `resources/js/components/Dashboard.vue` is a 23-line "Click Me" counter demo. The real UI is Blade + Alpine. Six dependencies exist to serve it.

**Files:**
- Delete: `resources/js/components/Dashboard.vue`, `resources/js/vue-shims.d.ts`
- Modify: `resources/js/app.ts`, `vite.config.js`, `package.json`

**Interfaces:**
- Consumes: nothing
- Produces: nothing. Phase 1 assumes `app.ts` contains only Alpine bootstrapping.

- [ ] **Step 1: Confirm nothing else mounts Vue**

Run: `grep -rn "dashboard-app\|createApp\|primevue\|PrimeVue\|\.vue" resources/ --include="*.blade.php" --include="*.ts" --include="*.js"`
Expected: matches only in `resources/js/app.ts` and the two files being deleted. If any Blade view contains `id="dashboard-app"`, remove that element too and note it.

- [ ] **Step 2: Delete the files**

```bash
rm resources/js/components/Dashboard.vue resources/js/vue-shims.d.ts
rmdir resources/js/components 2>/dev/null || true
```

- [ ] **Step 3: Reduce `app.ts` to Alpine only**

Replace the entire contents of `resources/js/app.ts` with:

```ts
import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
```

- [ ] **Step 4: Strip Vue from the Vite config**

Replace the entire contents of `vite.config.js` with:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
    ],
});
```

- [ ] **Step 5: Drop the dependencies**

```bash
npm uninstall vue primevue @primevue/themes primeicons @vitejs/plugin-vue vue-tsc
```

Verify: `grep -nE '"(vue|primevue|@primevue/themes|primeicons|@vitejs/plugin-vue|vue-tsc)"' package.json`
Expected: no output.

- [ ] **Step 6: Verify the build**

Run: `npm run build`
Expected: succeeds with no errors and no Vue chunk in the output listing.

Run: `php artisan test`
Expected: all green (no PHP behaviour changed).

- [ ] **Step 7: Checkpoint — hand off for commit**

Stage: `package.json package-lock.json vite.config.js resources/js/`
Suggested message: `chore: remove the unused Vue and PrimeVue stack`

Note: `package-lock.json` already had unrelated uncommitted changes before this task. Point that out so the user decides what to include.

---

## Task 7: Naming, readme, and dead views

Spec §5.6.

**Files:**
- Modify: `.env`, `.env.example`, `README.md`
- Delete: `resources/views/welcome.blade.php`, `resources/views/about/mission.blade.php`, `resources/views/about/sponsors.blade.php`

**Interfaces:**
- Consumes: nothing
- Produces: nothing

- [ ] **Step 1: Confirm the views really are dead**

Run:
```bash
grep -rn "view('welcome'\|view(\"welcome\"\|about\.mission\|about\.sponsors\|@include('about" resources/ routes/ app/ tests/
```
Expected: no output. `routes/web.php` redirects `/about/mission` and `/about/sponsors` to `/about`; it never renders those views.

- [ ] **Step 2: Delete them**

```bash
rm resources/views/welcome.blade.php resources/views/about/mission.blade.php resources/views/about/sponsors.blade.php
```

This also removes 7 of the 12 inline `style=""` occurrences in the codebase.

- [ ] **Step 3: Set the application name**

In `.env` and `.env.example`, change:

```
APP_NAME=Laravel
```

to:

```
APP_NAME="First to Act Poker"
```

Then run `php artisan config:clear`.

- [ ] **Step 4: Write the readme**

Replace `README.md` entirely:

```markdown
# First to Act Poker

League management for a recreational poker league: seasons, venues, tournaments,
registrations, results and standings.

## Stack

Laravel 12 (PHP 8.2) · SQLite · Blade + Alpine.js · Vite

## Setup

```bash
composer setup
```

That installs dependencies, copies `.env`, generates a key, migrates, and builds
front-end assets.

Seed a realistic dataset — 100 players, 5 venues, 5 seasons, tournaments with
registrants and results:

```bash
php artisan db:seed
```

The seeded administrator is `admin@example.com` / `password`.

## Running

```bash
composer dev
```

Runs the PHP server, queue worker, log tailer and Vite together.

## Tests

```bash
composer test
```

## How the league works

- A **season** spans a date range. Exactly one season is current at a time; making
  one current automatically clears the others.
- A **tournament** belongs to a season and a venue. `scheduled_at` is the
  registration cutoff; `start_time` is when play begins.
- **Registrants** sign up for a tournament. Self-registration closes at
  `scheduled_at`. An administrator can still add a player after that from the
  registrants screen, where an entry recorded after `scheduled_at` is flagged as
  a late entry.
- **Results** award points from the shared **points structure** table, so place
  and points are never typed in by hand.
- **Venue points** are a separate loyalty ledger, independent of tournament results.

Everything under `/poker` and `/users` requires an administrator account.

## Documentation

- `docs/superpowers/specs/` — design specifications
- `docs/superpowers/plans/` — implementation plans
```

- [ ] **Step 5: Verify**

Run: `php artisan test`
Expected: all green.

Run: `php artisan serve` and load `/`, `/about`, `/contact`.
Expected: all 200; the browser tab shows "First to Act Poker".

- [ ] **Step 6: Checkpoint — hand off for commit**

Stage: `.env.example README.md resources/views/`
Suggested message: `chore: name the app, write a real readme, delete unrouted views`

---

## Task 8: Route smoke test

Spec §8.1. Phase 1 rewrites 68 Blade views. This test is the net that catches a view broken mid-conversion. It must exist before Phase 1 starts.

It deliberately **fails on an unknown route parameter** rather than skipping it, so the fixture map cannot silently go stale as routes are added.

**Files:**
- Test: `tests/Feature/RouteSmokeTest.php`

**Interfaces:**
- Consumes: every route name registered by Tasks 2, 3 and 5
- Produces: nothing

- [ ] **Step 1: Write the test**

Create `tests/Feature/RouteSmokeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Requests every registered GET route and asserts none returns a server
 * error. This is the guard for large-scale view rewrites.
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, string> route parameter name => concrete value */
    private array $bindings = [];

    /**
     * URIs that cannot be smoke-tested by a plain GET.
     */
    private const SKIPPED = [
        'up',                       // health check, no view
        'storage/{path}',           // static file serving
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);

        $venue = Venue::create([
            'name' => 'The Grand Card Room',
            'address' => '100 Casino Blvd',
            'description' => 'A premier poker venue.',
        ]);

        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $tournament = PokerTournament::create([
            'name' => 'Weekly Freezeout',
            'scheduled_at' => now()->addDays(7),
            'start_time' => now()->addDays(7)->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $structure = PointsStructure::create(['place' => 1, 'points' => 100]);

        $registrant = PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->admin->id,
            'player_name' => 'Admin User',
            'registered_at' => now(),
        ]);

        $result = PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->admin->id,
            'player_name' => 'Admin User',
            'place' => 1,
            'points' => 100,
        ]);

        $venuePoints = VenuePoints::create([
            'venue_id' => $venue->id,
            'user_id' => $this->admin->id,
            'user_name' => 'Admin User',
            'event_date' => now()->toDateString(),
            'amount' => 50,
        ]);

        $this->bindings = [
            'user' => $this->admin->id,
            'venue' => $venue->id,
            'season' => $season->id,
            'tournament' => $tournament->id,
            'points_structure' => $structure->id,
            'registrant' => $registrant->id,
            'result' => $result->id,
            'venue_point' => $venuePoints->id,
            'token' => 'smoke-test-token',
            'id' => $this->admin->id,
            'hash' => sha1($this->admin->email),
        ];
    }

    public function test_every_get_route_responds_to_an_admin_without_a_server_error()
    {
        $this->assertNoServerErrors(fn (string $uri) => $this->actingAs($this->admin)->get($uri));
    }

    public function test_every_get_route_responds_to_a_guest_without_a_server_error()
    {
        $this->assertNoServerErrors(fn (string $uri) => $this->get($uri));
    }

    private function assertNoServerErrors(callable $request): void
    {
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (in_array($uri, self::SKIPPED, true) || str_starts_with($uri, '_')) {
                continue;
            }

            $unbound = [];

            $resolved = preg_replace_callback(
                '/\{(\w+)\??\}/',
                function (array $matches) use (&$unbound) {
                    if (! array_key_exists($matches[1], $this->bindings)) {
                        $unbound[] = $matches[1];

                        return $matches[0];
                    }

                    return $this->bindings[$matches[1]];
                },
                $uri
            );

            if ($unbound !== []) {
                $failures[] = sprintf(
                    '%s — no fixture for {%s}. Add it to $bindings in RouteSmokeTest.',
                    $uri,
                    implode('}, {', $unbound)
                );

                continue;
            }

            $status = $request('/'.ltrim($resolved, '/'))->getStatusCode();

            if ($status >= 500) {
                $failures[] = sprintf('%s — HTTP %d', $uri, $status);
            }
        }

        $this->assertSame(
            [],
            $failures,
            "Routes failed the smoke test:\n  ".implode("\n  ", $failures)
        );
    }
}
```

- [ ] **Step 2: Run it**

Run: `php artisan test --filter=RouteSmokeTest`
Expected: 2 passed.

If it fails with "no fixture for {x}", a route parameter is unmapped — add it to `$bindings`. That failure is the test working as designed, not a defect in the test.

If it fails with an HTTP 500 on a real route, that is a genuine bug found. Fix it before continuing.

- [ ] **Step 3: Confirm it actually catches a broken view**

Temporarily break a view to prove the net works:

```bash
printf '\n@php throw new \\RuntimeException("smoke"); @endphp\n' >> resources/views/events.blade.php
php artisan test --filter=RouteSmokeTest
```
Expected: FAILS reporting `events — HTTP 500`.

Now undo it:
```bash
sed -i '$ d' resources/views/events.blade.php
sed -i '$ d' resources/views/events.blade.php
php artisan test --filter=RouteSmokeTest
tail -3 resources/views/events.blade.php
```
Expected: 2 passed, and the tail shows the view's original closing markup with no `RuntimeException` line.

- [ ] **Step 4: Run the whole suite**

Run: `php artisan test`
Expected: all green — the 13 pre-existing files plus the 5 added in this phase.

- [ ] **Step 5: Checkpoint — hand off for commit**

Stage: `tests/Feature/RouteSmokeTest.php`
Suggested message: `test: add a route smoke test covering every GET route`

---

## Phase 0 exit criteria

All must hold before Phase 1 begins:

- [ ] `php artisan test` — all green.
- [ ] `grep -rn "is_active" app/ routes/ resources/ database/` — no output.
- [ ] `grep -rn 'action="#"' resources/views/` — no output.
- [ ] `grep -rn "poker.tournaments.show\|poker.seasons.show" resources/ app/` — no output.
- [ ] `grep -c "abort_unless" app/Http/Controllers/UserController.php` — `0`.
- [ ] `grep -rn 'style="' resources/views/ | wc -l` — `5` (down from 12; the remainder are handled in Phase 1).
- [ ] `npm run build` — succeeds, no Vue in the bundle.
- [ ] A non-admin account can view `/dashboard`, `/events`, a tournament, a season, and can self-register and unregister.
- [ ] A non-admin account gets 403 on every `/poker` route and on `/users`.
