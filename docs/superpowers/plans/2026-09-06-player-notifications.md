# Player Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Players receive an in-app notification, with placement-appropriate fanfare, when an administrator publishes the results of a tournament they scored points in — and can mark those notifications read or unread and delete them.

**Architecture:** Laravel's own database-notification channel, because `User` already has `Notifiable` and it supplies read state for free. Publishing is an explicit administrator action that also locks the tournament against further change, which is what makes a sent notification permanently true. Two interface surfaces: a redesigned desktop user dropdown, and a new mobile bell button.

**Tech Stack:** Laravel 12, PHP 8.5, Blade + Alpine.js, hand-built CSS (no Tailwind), PHPUnit 11, SQLite locally and MySQL in CI/production.

**Spec:** `docs/superpowers/specs/2026-09-06-notifications-design.md`

## Global Constraints

- **Never run git commands.** The owner commits manually. Every "Commit" step below is a hand-off: state the files and the message, then stop and wait.
- **No inline CSS.** `InlineStyleGuardTest` fails the suite on a `style` attribute or a `<style>` block. The single permitted exception is a `style` attribute whose every declaration is a custom property (`style="--meter-fill: 86%"`). `resources/views/vendor/mail` is the only exempt directory.
- **No inline JavaScript.** Alpine directives in attributes only, as `x-dropdown` and `x-topbar` already do.
- **No browser-based tests.** Anything visual is verified by screenshot, not by an assertion on computed style.
- Gradients, `--shadow-raised`, `--shadow-float` and `--radius-lg` are fenced to `resources/css/5-public/` by `PublicRegisterTest`. The dashboard register uses borders, not shadows.
- **Both database drivers must pass.** CI runs the suite on SQLite and MySQL. JSON-path queries and integer casts are the two things that have historically passed on one and failed on the other.
- Run the full suite with `php artisan test`. Run one file with `php artisan test --filter=ClassName`.
- After changing any file under `resources/css/`, run `npm run build`.

---

### Task 1: The notifications table and the placement notification

Laravel's stock `notifications` migration uses `$table->morphs('notifiable')`, which is an unsigned bigint. Users here are ULIDs. A `morphs` migrates cleanly and then fails on the first insert, which is why the test sends a real notification rather than inspecting the schema.

**Files:**
- Create: `database/migrations/2026_09_06_100000_create_notifications_table.php`
- Create: `app/Notifications/TournamentPlacement.php`
- Test: `tests/Feature/TournamentPlacementNotificationTest.php`

**Interfaces:**
- Consumes: `App\Models\PokerTournamentResult` (has `place`, `points`, `user_id`, `player_name`, and a `tournament` relation), `App\Models\User`.
- Produces: `App\Notifications\TournamentPlacement`, constructed as `new TournamentPlacement(PokerTournamentResult $result)`. Its stored `data` array has exactly the keys `tournament_id`, `tournament_name`, `played_on`, `place`, `points`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TournamentPlacementNotificationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\TournamentPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Storing a placement.
 *
 * The schema is the trap here. Laravel's stock notifications migration declares
 * morphs('notifiable'), an unsigned bigint, and this application's users are
 * ULIDs. That migrates cleanly and fails on the first insert, so these tests
 * send a real notification rather than inspecting columns.
 */
class TournamentPlacementNotificationTest extends TestCase
{
    use RefreshDatabase;

    // NOT result(): PHPUnit\TestCase::result() is final and cannot be overridden.
    private function scoredResult(int $place, int $points): PokerTournamentResult
    {
        $season = PokerSeason::create([
            'name' => 'Season 40', 'start_date' => '2026-08-01',
            'end_date' => '2026-10-31', 'is_current' => true,
        ]);

        $tournament = PokerTournament::create([
            'name' => 'Autumn Showdown',
            'start_time' => '2026-09-09 19:00',
            'venue_id' => Venue::create(['name' => 'The Copper Kettle', 'address' => '1 Card St'])->id,
            'season_id' => $season->id,
        ]);

        $user = User::factory()->create(['first_name' => 'Wanda', 'last_name' => 'Reeve']);

        return PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'player_name' => 'Wanda Reeve',
            'place' => $place,
            'points' => $points,
        ]);
    }

    public function test_a_placement_is_stored_against_a_ulid_user(): void
    {
        $result = $this->scoredResult(place: 1, points: 100);

        $result->user->notify(new TournamentPlacement($result));

        $this->assertSame(1, $result->user->notifications()->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $result->user_id,
            'notifiable_type' => User::class,
            'type' => TournamentPlacement::class,
        ]);
    }

    public function test_it_carries_everything_the_card_needs(): void
    {
        // A copy, not a join. The tournament can be renamed or deleted later,
        // and the message should keep saying what was true when it was sent.
        $result = $this->scoredResult(place: 2, points: 75);

        $result->user->notify(new TournamentPlacement($result));

        $this->assertSame([
            'tournament_id' => $result->tournament_id,
            'tournament_name' => 'Autumn Showdown',
            'played_on' => '2026-09-09',
            'place' => 2,
            'points' => 75,
        ], $result->user->notifications()->first()->data);
    }

    public function test_a_stored_placement_starts_unread(): void
    {
        $result = $this->scoredResult(place: 3, points: 50);

        $result->user->notify(new TournamentPlacement($result));

        $this->assertSame(1, $result->user->unreadNotifications()->count());
    }

    public function test_it_is_stored_rather_than_mailed(): void
    {
        // via() is database only. A mass mail on every league night is a
        // different decision with a different cost.
        $result = $this->scoredResult(place: 1, points: 100);

        $this->assertSame(['database'], (new TournamentPlacement($result))->via($result->user));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=TournamentPlacementNotificationTest`
Expected: FAIL — `Class "App\Notifications\TournamentPlacement" not found`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_09_06_100000_create_notifications_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's database notification channel.
 *
 * Hand-written rather than taken from `php artisan notifications:table`,
 * because that stub declares morphs('notifiable') -- an unsigned bigint -- and
 * every user in this application has a ULID primary key. The stub would migrate
 * without complaint and then fail on the first notification anybody sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->ulidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

- [ ] **Step 4: Create the notification**

Create `app/Notifications/TournamentPlacement.php`:

```php
<?php

namespace App\Notifications;

use App\Models\PokerTournamentResult;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Told to a player when the results of a tournament they scored in are
 * published.
 *
 * Only players with points > 0 receive one: the cut is the points structure, so
 * a structure paying the top ten of a field of twenty notifies ten people and
 * eleventh onwards score nothing and hear nothing.
 *
 * The data is a COPY of the tournament's name and date rather than a reference
 * to it. A tournament can be renamed, re-dated or deleted afterwards, and a
 * message in somebody's inbox should go on saying what was true when it was
 * sent rather than quietly changing underneath them.
 */
class TournamentPlacement extends Notification
{
    use Queueable;

    public function __construct(private PokerTournamentResult $result) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $tournament = $this->result->tournament;

        return [
            'tournament_id' => $tournament->id,
            'tournament_name' => $tournament->name,
            'played_on' => $tournament->start_time->toDateString(),
            'place' => $this->result->place,
            'points' => $this->result->points,
        ];
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=TournamentPlacementNotificationTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Prove the ULID column is load-bearing**

Temporarily change `ulidMorphs` to `morphs` in the migration and run the test.

**The send tests will still pass, and that is the finding.** SQLite is
dynamically typed: it stores a 26-character ULID in a bigint column without
complaint. So every assertion above goes green against a schema that fails on
MySQL in CI — the exact SQLite-versus-MySQL split this project has been bitten by
before. Add an assertion on the column itself, which discriminates on both
drivers:

```php
    public function test_the_notifiable_column_holds_a_ulid_rather_than_an_integer(): void
    {
        // varchar with ulidMorphs, integer with morphs -- both measured.
        $this->assertSame('varchar', Schema::getColumnType('notifications', 'notifiable_id'));
    }
```

Re-run with `morphs` and confirm exactly that one test fails, then restore
`ulidMorphs`. Requires `use Illuminate\Support\Facades\Schema;`.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: all green, 515 + 4 = 519 tests.

- [ ] **Step 8: Commit (hand-off)**

Do not run git. Tell the owner:

Files: `database/migrations/2026_09_06_100000_create_notifications_table.php`, `app/Notifications/TournamentPlacement.php`, `tests/Feature/TournamentPlacementNotificationTest.php`

```
feat(notifications): store placement notifications

Laravel's database channel, which User already has Notifiable for. The
migration is hand-written because the framework stub declares
morphs('notifiable') -- an unsigned bigint against ULID users -- which
migrates cleanly and fails on the first insert.

The notification copies the tournament's name and date rather than
referencing them: a message in someone's inbox should keep saying what
was true when it was sent.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HpG1pmKxAnRgEpKmNSnNzG
```

---

### Task 2: `published_at`, completeness, and the shared refusal

**Files:**
- Create: `database/migrations/2026_09_06_110000_add_published_at_to_tournaments_table.php`
- Modify: `app/Models/PokerTournament.php`
- Test: `tests/Feature/TournamentPublishingTest.php`

**Interfaces:**
- Produces: `PokerTournament::isPublished(): bool`, `PokerTournament::isComplete(): bool`, `PokerTournament::publishedRefusal(): ?string`. Tasks 3 and 4 depend on all three.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TournamentPublishingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When a tournament is finished, and what saying so means.
 *
 * "Every result is in" is not a stable state in this application: registering a
 * late player shifts every recorded finish down, and results can be edited
 * through the admin CRUD. So completeness is a question an administrator asks
 * before publishing, and publishing is what freezes the answer.
 */
class TournamentPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function tournament(): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 40', 'start_date' => '2026-08-01',
            'end_date' => '2026-10-31', 'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Autumn Showdown',
            'start_time' => now()->subHour(),
            'venue_id' => Venue::create(['name' => 'Hall', 'address' => '1 St'])->id,
            'season_id' => $season->id,
        ]);
    }

    protected function enter(PokerTournament $tournament, User $player): void
    {
        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'registered_at' => now(),
        ]);
    }

    protected function score(PokerTournament $tournament, User $player, int $place, int $points): PokerTournamentResult
    {
        return PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'place' => $place,
            'points' => $points,
        ]);
    }

    public function test_an_empty_tournament_is_not_complete(): void
    {
        // Nobody entered, so there is nothing to be finished. Without this
        // guard "every registrant has a result" is trivially true of zero
        // registrants and an empty tournament could be published.
        $this->assertFalse($this->tournament()->isComplete());
    }

    public function test_a_tournament_with_an_unscored_registrant_is_not_complete(): void
    {
        $tournament = $this->tournament();
        $players = User::factory()->count(2)->create();

        foreach ($players as $player) {
            $this->enter($tournament, $player);
        }

        $this->score($tournament, $players[0], 2, 50);

        $this->assertFalse($tournament->fresh()->isComplete());
    }

    public function test_a_tournament_where_everyone_has_finished_is_complete(): void
    {
        $tournament = $this->tournament();
        $players = User::factory()->count(2)->create();

        foreach ($players as $player) {
            $this->enter($tournament, $player);
        }

        $this->score($tournament, $players[0], 2, 50);
        $this->score($tournament, $players[1], 1, 100);

        $this->assertTrue($tournament->fresh()->isComplete());
    }

    public function test_a_result_for_someone_who_never_entered_does_not_complete_it(): void
    {
        // Counting results against registrants would pass here. The admin
        // results form validates that a user EXISTS, not that they registered,
        // so a stray result can outnumber the field without covering it.
        $tournament = $this->tournament();
        $entered = User::factory()->create();
        $stranger = User::factory()->create();

        $this->enter($tournament, $entered);
        $this->score($tournament, $stranger, 1, 100);

        $this->assertFalse($tournament->fresh()->isComplete());
    }

    public function test_a_tournament_starts_unpublished(): void
    {
        $this->assertFalse($this->tournament()->isPublished());
        $this->assertNull($this->tournament()->published_at);
    }

    public function test_publishing_is_recorded_as_a_time(): void
    {
        $tournament = $this->tournament();

        $tournament->forceFill(['published_at' => now()])->save();

        $this->assertTrue($tournament->fresh()->isPublished());
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $tournament->fresh()->published_at);
    }

    public function test_an_unpublished_tournament_refuses_nothing(): void
    {
        $this->assertNull($this->tournament()->publishedRefusal());
    }

    public function test_the_refusal_names_the_tournament_and_says_what_to_do(): void
    {
        // Six call sites show this message. It has to say which tournament --
        // an administrator may have several open -- and what the way out is.
        $tournament = $this->tournament();
        $tournament->forceFill(['published_at' => now()])->save();

        $refusal = $tournament->publishedRefusal();

        $this->assertStringContainsString('Autumn Showdown', $refusal);
        $this->assertStringContainsString('published', $refusal);
        $this->assertStringContainsString('Unpublish', $refusal);
    }

    public function test_published_at_is_not_mass_assignable(): void
    {
        // Publishing sends messages to players. It is not something a tournament
        // edit form should be able to do by posting a field.
        $tournament = $this->tournament();

        $tournament->update(['published_at' => now()]);

        $this->assertFalse($tournament->fresh()->isPublished());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=TournamentPublishingTest`
Expected: FAIL — `Call to undefined method App\Models\PokerTournament::isComplete()`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_09_06_110000_add_published_at_to_tournaments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an administrator declared a tournament's results final.
 *
 * Publishing does two things at once: it tells the players who scored, and it
 * locks the tournament. The second is what makes the first safe -- a place is a
 * position in a field, and without the lock a late registration would shift
 * every finish after the messages had gone out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
```

- [ ] **Step 4: Add the three methods to the model**

In `app/Models/PokerTournament.php`, add `'published_at' => 'datetime'` to `$casts` and leave `$fillable` alone — publishing is done by an explicit action, never by a form post. Then add:

```php
    /** Results have been declared final, and the tournament is locked. */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Every player who entered has a finish, and somebody entered.
     *
     * Comparing counts would be wrong twice over. An empty tournament has zero
     * of each and would read as finished, and the admin results form validates
     * that a user EXISTS rather than that they registered -- so a result for
     * somebody who never played can make the numbers match while a registrant
     * is still unscored.
     */
    public function isComplete(): bool
    {
        $entered = $this->registrants()->whereNotNull('user_id')->pluck('user_id');

        if ($entered->isEmpty()) {
            return false;
        }

        return $this->results()->whereIn('user_id', $entered)->distinct()->count('user_id') === $entered->unique()->count();
    }

    /**
     * Why this tournament cannot be changed, or null if it can.
     *
     * One method for six call sites -- register, unregister, eliminate, remove
     * registrant, and result create/update/delete -- because six copies of a
     * rule are six chances for it to drift, which is the same reasoning that
     * put hasRecordedResults() here.
     */
    public function publishedRefusal(): ?string
    {
        if (! $this->isPublished()) {
            return null;
        }

        return __('Results for :tournament have been published. Unpublish them first to make changes.', [
            'tournament' => $this->name,
        ]);
    }
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=TournamentPublishingTest`
Expected: PASS, 9 tests.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: green. If `TournamentScheduleTest` or `EliminatePlayerTest` fail, the cast or the migration is wrong — nothing in this task should change existing behaviour.

- [ ] **Step 7: Commit (hand-off)**

Files: the migration, `app/Models/PokerTournament.php`, `tests/Feature/TournamentPublishingTest.php`

```
feat(tournaments): record when results were published

published_at carries two meanings at once: the results are final, and the
tournament is locked. The second is what makes the first safe, since a
late registration otherwise shifts every finish after the players have
already been told.

isComplete() compares registrants to the results covering them rather
than counting both. An empty tournament has zero of each and would read
as finished, and a result for somebody who never entered can make the
numbers match while a registrant is still unscored.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HpG1pmKxAnRgEpKmNSnNzG
```

---

### Task 3: Publishing and unpublishing

**Files:**
- Modify: `app/Http/Controllers/Poker/PokerTournamentController.php`
- Modify: `routes/web.php` (inside the `admin` + `poker` group, beside `tournaments.eliminate` at roughly line 197)
- Modify: `resources/views/poker/tournaments/show.blade.php`
- Test: `tests/Feature/PublishResultsTest.php`

**Interfaces:**
- Consumes: `TournamentPlacement` (Task 1), `isPublished()` / `isComplete()` (Task 2).
- Produces: routes `poker.tournaments.publish` (POST) and `poker.tournaments.unpublish` (DELETE).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PublishResultsTest.php`. It extends `TournamentPublishingTest` purely to reuse the `tournament()`, `enter()` and `score()` helpers:

```php
<?php

namespace Tests\Feature;

use App\Models\PokerTournament;
use App\Models\User;
use App\Notifications\TournamentPlacement;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Publishing results tells the players who scored, and closes the tournament.
 */
class PublishResultsTest extends TournamentPublishingTest
{
    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    /**
     * A finished tournament: three players in, three finishes, the last of them
     * scoring nothing because the structure does not pay that place.
     *
     * @return array{0: PokerTournament, 1: \Illuminate\Support\Collection<int, User>}
     */
    private function finished(): array
    {
        $tournament = $this->tournament();
        $players = User::factory()->count(3)->create();

        foreach ($players as $player) {
            $this->enter($tournament, $player);
        }

        $this->score($tournament, $players[0], 1, 100);
        $this->score($tournament, $players[1], 2, 60);
        $this->score($tournament, $players[2], 3, 0);

        return [$tournament->fresh(), $players];
    }

    public function test_publishing_notifies_every_player_who_scored(): void
    {
        [$tournament, $players] = $this->finished();

        $this->actingAs($this->admin())
            ->post(route('poker.tournaments.publish', $tournament))
            ->assertSessionHas('status');

        $this->assertSame(1, $players[0]->notifications()->count());
        $this->assertSame(1, $players[1]->notifications()->count());
    }

    public function test_a_finisher_who_scored_nothing_is_not_notified(): void
    {
        // The cut is the points structure. Third of three scored 0 because the
        // structure does not pay that place, so there is nothing to celebrate.
        [, $players] = $this->finished();

        $this->actingAs($this->admin())
            ->post(route('poker.tournaments.publish', PokerTournament::first()));

        $this->assertSame(0, $players[2]->notifications()->count());
    }

    public function test_a_non_podium_scorer_is_notified_too(): void
    {
        // Fourth place with points gets a message. Only the fanfare differs.
        $tournament = $this->tournament();
        $players = User::factory()->count(4)->create();

        foreach ($players as $player) {
            $this->enter($tournament, $player);
        }

        foreach ([[0, 1, 100], [1, 2, 60], [2, 3, 40], [3, 4, 20]] as [$i, $place, $points]) {
            $this->score($tournament, $players[$i], $place, $points);
        }

        $this->actingAs($this->admin())->post(route('poker.tournaments.publish', $tournament->fresh()));

        $this->assertSame(1, $players[3]->notifications()->count());
        $this->assertSame(4, $players[3]->notifications()->first()->data['place']);
    }

    public function test_publishing_marks_the_tournament(): void
    {
        [$tournament] = $this->finished();

        $this->actingAs($this->admin())->post(route('poker.tournaments.publish', $tournament));

        $this->assertTrue($tournament->fresh()->isPublished());
    }

    public function test_an_incomplete_tournament_cannot_be_published(): void
    {
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->enter($tournament, $player);

        $this->actingAs($this->admin())
            ->post(route('poker.tournaments.publish', $tournament))
            ->assertSessionHas('error');

        $this->assertFalse($tournament->fresh()->isPublished());
        $this->assertSame(0, DatabaseNotification::count());
    }

    public function test_publishing_twice_sends_one_set(): void
    {
        [$tournament, $players] = $this->finished();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('poker.tournaments.publish', $tournament));
        $this->actingAs($admin)->post(route('poker.tournaments.publish', $tournament->fresh()))
            ->assertSessionHas('error');

        $this->assertSame(1, $players[0]->notifications()->count());
    }

    public function test_a_tournament_nobody_scored_in_still_publishes(): void
    {
        // No points structure means every result is 0. Locking is the other
        // half of what publishing does, so refusing here would leave the
        // tournament permanently unfinishable.
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 0);

        $this->actingAs($this->admin())
            ->post(route('poker.tournaments.publish', $tournament->fresh()))
            ->assertSessionHas('status');

        $this->assertTrue($tournament->fresh()->isPublished());
        $this->assertSame(0, DatabaseNotification::count());
    }

    public function test_unpublishing_retracts_the_notifications_it_sent(): void
    {
        // Unpublishing means the results were not final. Leaving a message that
        // says "you finished 1st" when the league no longer agrees is leaving a
        // false statement in somebody's inbox.
        [$tournament, $players] = $this->finished();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('poker.tournaments.publish', $tournament));
        $this->assertSame(1, $players[0]->notifications()->count());

        $this->actingAs($admin)
            ->delete(route('poker.tournaments.unpublish', $tournament->fresh()))
            ->assertSessionHas('status');

        $this->assertFalse($tournament->fresh()->isPublished());
        $this->assertSame(0, $players[0]->fresh()->notifications()->count());
    }

    public function test_unpublishing_leaves_another_tournaments_notifications_alone(): void
    {
        [$first, $firstPlayers] = $this->finished();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('poker.tournaments.publish', $first));

        // A second finished tournament, published and then left alone.
        $second = PokerTournament::create([
            'name' => 'Winter Open',
            'start_time' => now()->subDay(),
            'venue_id' => $first->venue_id,
            'season_id' => $first->season_id,
        ]);
        $other = User::factory()->create();
        $this->enter($second, $other);
        $this->score($second, $other, 1, 100);

        $this->actingAs($admin)->post(route('poker.tournaments.publish', $second->fresh()));
        $this->actingAs($admin)->delete(route('poker.tournaments.unpublish', $first->fresh()));

        $this->assertSame(0, $firstPlayers[0]->fresh()->notifications()->count());
        $this->assertSame(1, $other->fresh()->notifications()->count(), 'Another tournament lost its notifications.');
    }

    public function test_republishing_sends_one_clean_set(): void
    {
        [$tournament, $players] = $this->finished();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('poker.tournaments.publish', $tournament));
        $this->actingAs($admin)->delete(route('poker.tournaments.unpublish', $tournament->fresh()));
        $this->actingAs($admin)->post(route('poker.tournaments.publish', $tournament->fresh()));

        $this->assertSame(1, $players[0]->fresh()->notifications()->count());
    }

    public function test_only_placement_notifications_are_retracted(): void
    {
        // Unpublishing must not clear a player's unrelated notifications.
        [$tournament, $players] = $this->finished();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('poker.tournaments.publish', $tournament));

        DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\SomethingElse',
            'notifiable_type' => User::class,
            'notifiable_id' => $players[0]->id,
            'data' => ['message' => 'unrelated'],
            'read_at' => null,
        ]);

        $this->actingAs($admin)->delete(route('poker.tournaments.unpublish', $tournament->fresh()));

        $this->assertSame(1, $players[0]->fresh()->notifications()->count());
        $this->assertSame('App\\Notifications\\SomethingElse', $players[0]->fresh()->notifications()->first()->type);
    }

    public function test_a_player_cannot_publish(): void
    {
        [$tournament] = $this->finished();

        $this->actingAs(User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']))
            ->post(route('poker.tournaments.publish', $tournament))
            ->assertForbidden();

        $this->assertFalse($tournament->fresh()->isPublished());
    }

    public function test_the_button_appears_only_when_it_can_act(): void
    {
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->enter($tournament, $player);
        $admin = $this->admin();

        // Incomplete: no button.
        $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('>Publish results<', false);

        $this->score($tournament, $player, 1, 100);

        $this->actingAs($admin)->get(route('tournaments.show', $tournament->fresh()))->assertOk()
            ->assertSee('>Publish results<', false);

        $this->actingAs($admin)->post(route('poker.tournaments.publish', $tournament->fresh()));

        $this->actingAs($admin)->get(route('tournaments.show', $tournament->fresh()))->assertOk()
            ->assertDontSee('>Publish results<', false)
            ->assertSee('>Unpublish<', false);
    }

    public function test_a_player_is_not_offered_either_control(): void
    {
        [$tournament] = $this->finished();

        $this->actingAs(User::factory()->create(['approval_status' => 'approved']))
            ->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('>Publish results<', false)
            ->assertDontSee('>Unpublish<', false);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PublishResultsTest`
Expected: FAIL — `Route [poker.tournaments.publish] not defined`.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, inside the `Route::middleware('admin')->prefix('poker')->name('poker.')->group(...)`, directly after the `tournaments.eliminate` route:

```php
        // Declaring results final. Not part of the tournaments resource: it
        // sends messages to players and locks the record, which is not what
        // "update a tournament" means.
        Route::post('tournaments/{tournament}/publish', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'publish'])
            ->name('tournaments.publish');
        Route::delete('tournaments/{tournament}/publish', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'unpublish'])
            ->name('tournaments.unpublish');
```

- [ ] **Step 4: Add the controller actions**

In `app/Http/Controllers/Poker/PokerTournamentController.php`, add these imports at the top if missing — `use App\Notifications\TournamentPlacement;`, `use Illuminate\Notifications\DatabaseNotification;`, `use Illuminate\Support\Facades\DB;` — then add both methods:

```php
    /**
     * Declare the results final: tell the players who scored, and lock it.
     *
     * The two happen together and in one transaction. Sending without locking
     * would leave every message vulnerable to a late registration shifting the
     * places underneath it; locking without sending would close a tournament
     * nobody was told about.
     */
    public function publish(PokerTournament $tournament): RedirectResponse
    {
        if ($tournament->isPublished()) {
            return back()->with('error', __('Results for :tournament have already been published.', [
                'tournament' => $tournament->name,
            ]));
        }

        if (! $tournament->isComplete()) {
            return back()->with('error', __(
                'Every registered player needs a finish before results can be published.'
            ));
        }

        // points > 0 is the whole recipient rule. A place the structure does not
        // pay scores nothing, and there is nothing to celebrate about nothing.
        $scored = $tournament->results()
            ->where('points', '>', 0)
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        DB::transaction(function () use ($tournament, $scored) {
            foreach ($scored as $result) {
                $result->user?->notify(new TournamentPlacement($result));
            }

            $tournament->forceFill(['published_at' => now()])->save();
        });

        return back()->with('status', trans_choice(
            '{0}Results published. Nobody scored points, so no one was notified.'
            .'|{1}Results published. 1 player was notified.'
            .'|[2,*]Results published. :count players were notified.',
            $scored->count(),
            ['count' => $scored->count()]
        ));
    }

    /**
     * Reopen a tournament, and retract what publishing claimed.
     *
     * The notifications go with it. Unpublishing means the results were not
     * final, so a message still saying "you finished 1st" is a statement the
     * league no longer stands behind -- and deleting them means republishing
     * sends one clean set rather than a duplicate for everybody whose placing
     * did not change.
     */
    public function unpublish(PokerTournament $tournament): RedirectResponse
    {
        if (! $tournament->isPublished()) {
            return back()->with('error', __('Results for :tournament have not been published.', [
                'tournament' => $tournament->name,
            ]));
        }

        DB::transaction(function () use ($tournament) {
            // Scoped by type as well as by tournament: a player's unrelated
            // notifications are not this action's to delete.
            DatabaseNotification::where('type', TournamentPlacement::class)
                ->where('data->tournament_id', $tournament->id)
                ->delete();

            $tournament->forceFill(['published_at' => null])->save();
        });

        return back()->with('status', __('Results for :tournament are open again, and the notifications have been withdrawn.', [
            'tournament' => $tournament->name,
        ]));
    }
```

- [ ] **Step 5: Add the controls to the tournament page**

In `resources/views/poker/tournaments/show.blade.php`, inside the `shell__header` action area beside the existing Back and Edit controls, add:

```blade
                @if (auth()->user()->is_admin)
                    {{-- Publishing is the end of the tournament: it tells the
                         players who scored and locks the record. Offered only
                         when it can act, so the button is never a click that
                         fails. --}}
                    @if (! $tournament->isPublished() && $tournament->isComplete())
                        <form action="{{ route('poker.tournaments.publish', $tournament) }}" method="POST"
                              data-confirm-tone="primary"
                              data-confirm="{{ __('Publish results for :tournament? Every player who scored points will be notified, and the tournament will be locked.', [
                                  'tournament' => $tournament->name,
                              ]) }}">
                            @csrf
                            <x-btn variant="primary" type="submit">{{ __('Publish results') }}</x-btn>
                        </form>
                    @endif

                    @if ($tournament->isPublished())
                        <form action="{{ route('poker.tournaments.unpublish', $tournament) }}" method="POST"
                              data-confirm="{{ __('Unpublish :tournament? The notifications sent to players will be withdrawn.', [
                                  'tournament' => $tournament->name,
                              ]) }}">
                            @csrf
                            @method('DELETE')

                            <x-btn variant="ghost" type="submit">{{ __('Unpublish') }}</x-btn>
                        </form>
                    @endif
                @endif
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=PublishResultsTest`
Expected: PASS, 14 tests.

- [ ] **Step 7: Verify the JSON query on MySQL, not just SQLite**

`where('data->tournament_id', ...)` compiles differently per driver, and this project has already shipped a query that passed on SQLite and failed on MySQL. Run the file against MySQL before moving on. If no local MySQL is available, say so in the hand-off so the owner watches the CI MySQL leg.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: green.

- [ ] **Step 9: Commit (hand-off)**

Files: `routes/web.php`, `app/Http/Controllers/Poker/PokerTournamentController.php`, `resources/views/poker/tournaments/show.blade.php`, `tests/Feature/PublishResultsTest.php`

```
feat(tournaments): publish results and notify the players who scored

Publishing sends and locks in one transaction. Sending without locking
leaves every message open to a late registration shifting the places
underneath it; locking without sending closes a tournament nobody heard
about.

Recipients are results with points > 0, so the cut is the points
structure rather than the podium. A tournament nobody scored in still
publishes -- locking is the other half of the job, and refusing would
leave it permanently unfinishable.

Unpublishing deletes the placement notifications for that tournament.
Reopening the results means the league no longer stands behind them, and
a message still claiming first place is a statement it has withdrawn.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HpG1pmKxAnRgEpKmNSnNzG
```

---

### Task 4: The lock

**Files:**
- Modify: `app/Http/Controllers/Poker/PokerTournamentController.php` (`register`, `unregister`, `eliminate`)
- Modify: `app/Http/Controllers/Poker/PokerTournamentRegistrantController.php` (`destroy`)
- Modify: `app/Http/Controllers/Poker/PokerTournamentResultController.php` (`store`, `update`, `destroy`)
- Test: `tests/Feature/PublishedTournamentLockTest.php`

**Interfaces:**
- Consumes: `PokerTournament::publishedRefusal()` (Task 2).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PublishedTournamentLockTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;

/**
 * A published tournament does not change.
 *
 * Six paths could otherwise move a field whose players have already been told
 * where they finished. Each is tested in a PAIR -- allowed before publishing,
 * refused after -- because a guard that refused unconditionally would pass half
 * of every one of these on its own.
 */
class PublishedTournamentLockTest extends TournamentPublishingTest
{
    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    /** A finished, publishable tournament and the three players in it. */
    private function finished(): array
    {
        $tournament = $this->tournament();
        $players = User::factory()->count(3)->create(['approval_status' => 'approved']);

        foreach ($players as $player) {
            $this->enter($tournament, $player);
        }

        foreach ([[0, 1, 100], [1, 2, 60], [2, 3, 40]] as [$i, $place, $points]) {
            $this->score($tournament, $players[$i], $place, $points);
        }

        return [$tournament->fresh(), $players];
    }

    private function publish(PokerTournament $tournament): void
    {
        $this->actingAs($this->admin())->post(route('poker.tournaments.publish', $tournament));
    }

    public function test_registering_is_refused_after_publishing(): void
    {
        [$tournament] = $this->finished();
        $newcomer = User::factory()->create(['approval_status' => 'approved']);

        // Allowed before.
        $this->actingAs($newcomer)->post(route('tournaments.register', $tournament))
            ->assertSessionHas('status');
        $tournament->registrants()->where('user_id', $newcomer->id)->delete();

        $this->publish($tournament);

        $this->actingAs($newcomer)->post(route('tournaments.register', $tournament->fresh()))
            ->assertSessionHas('error');

        $this->assertSame(0, $tournament->registrants()->where('user_id', $newcomer->id)->count());
    }

    public function test_unregistering_is_refused_after_publishing_and_says_why(): void
    {
        // Already refused by hasRecordedResults(), since a published tournament
        // necessarily has a result for everyone. What publishing adds is the
        // REASON an administrator reads, so this asserts the message.
        [$tournament, $players] = $this->finished();
        $this->publish($tournament);

        $this->actingAs($players[0])->delete(route('tournaments.unregister', $tournament->fresh()));

        $this->assertStringContainsString('published', session('error'));
    }

    public function test_eliminating_is_refused_after_publishing(): void
    {
        PointsStructure::create(['place' => 1, 'points' => 100]);
        $tournament = $this->tournament();
        $players = User::factory()->count(2)->create();

        foreach ($players as $player) {
            $this->enter($tournament, $player);
        }

        $admin = $this->admin();

        // Allowed before: one elimination, which also completes the field.
        $this->actingAs($admin)->post(route('poker.tournaments.eliminate', $tournament), [
            'user_id' => $players[0]->id,
        ])->assertSessionHas('status');

        $this->actingAs($admin)->post(route('poker.tournaments.eliminate', $tournament), [
            'user_id' => $players[1]->id,
        ]);

        $this->publish($tournament->fresh());

        $this->actingAs($admin)->post(route('poker.tournaments.eliminate', $tournament->fresh()), [
            'user_id' => $players[0]->id,
        ])->assertSessionHas('error');

        $this->assertStringContainsString('published', session('error'));
    }

    public function test_removing_a_registrant_is_refused_after_publishing_and_says_why(): void
    {
        // The second of the two the older rule already refused.
        [$tournament, $players] = $this->finished();
        $this->publish($tournament);

        $victim = PokerTournamentRegistrant::where('tournament_id', $tournament->id)
            ->where('user_id', $players[0]->id)->firstOrFail();

        $this->actingAs($this->admin())->delete(route('poker.registrants.destroy', $victim));

        $this->assertStringContainsString('published', session('error'));
        $this->assertSame(3, $tournament->registrants()->count());
    }

    public function test_creating_a_result_is_refused_after_publishing(): void
    {
        [$tournament, $players] = $this->finished();
        $structure = PointsStructure::create(['place' => 9, 'points' => 5]);
        $stranger = User::factory()->create();

        $this->publish($tournament);

        $this->actingAs($this->admin())->post(route('poker.results.store'), [
            'tournament_id' => $tournament->id,
            'points_structure_id' => $structure->id,
            'user_id' => $stranger->id,
            'player_name' => 'Late Addition',
        ])->assertSessionHas('error');

        $this->assertSame(3, $tournament->results()->count());
        $this->assertNotNull($players);
    }

    public function test_updating_a_result_is_refused_after_publishing(): void
    {
        [$tournament, $players] = $this->finished();
        $structure = PointsStructure::create(['place' => 1, 'points' => 100]);
        $result = $tournament->results()->where('user_id', $players[0]->id)->firstOrFail();

        $this->publish($tournament);

        $this->actingAs($this->admin())->put(route('poker.results.update', $result), [
            'tournament_id' => $tournament->id,
            'points_structure_id' => $structure->id,
            'user_id' => $players[0]->id,
            'player_name' => 'Renamed',
        ])->assertSessionHas('error');

        $this->assertSame('100', (string) $result->fresh()->points);
    }

    public function test_deleting_a_result_is_refused_after_publishing(): void
    {
        [$tournament, $players] = $this->finished();
        $result = $tournament->results()->where('user_id', $players[0]->id)->firstOrFail();

        $this->publish($tournament);

        $this->actingAs($this->admin())->delete(route('poker.results.destroy', $result))
            ->assertSessionHas('error');

        $this->assertSame(3, $tournament->results()->count());
    }

    public function test_unpublishing_opens_every_path_again(): void
    {
        // The half that proves the lock is a gate rather than a wall.
        [$tournament, $players] = $this->finished();
        $admin = $this->admin();
        $this->publish($tournament);

        $this->actingAs($admin)->delete(route('poker.tournaments.unpublish', $tournament->fresh()));

        $result = $tournament->results()->where('user_id', $players[0]->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('poker.results.destroy', $result))
            ->assertSessionHas('status');

        $this->assertSame(2, $tournament->results()->count());
    }

    public function test_another_tournament_is_unaffected(): void
    {
        [$published] = $this->finished();
        $this->publish($published);

        $open = PokerTournament::create([
            'name' => 'Winter Open',
            'start_time' => now()->subHour(),
            'venue_id' => $published->venue_id,
            'season_id' => $published->season_id,
        ]);
        $player = User::factory()->create(['approval_status' => 'approved']);

        $this->actingAs($player)->post(route('tournaments.register', $open))
            ->assertSessionHas('status');

        $this->assertSame(1, $open->registrants()->count());
        $this->assertNotNull(PokerTournamentResult::count());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PublishedTournamentLockTest`
Expected: FAIL — several tests report `assertSessionHas('error')` failing, because the actions still succeed.

- [ ] **Step 3: Guard the three tournament actions**

In `app/Http/Controllers/Poker/PokerTournamentController.php`, add this as the first statement of `register`, `unregister` and `eliminate` — after the existing `$validated = $request->validate(...)` line in `eliminate`, and before any other logic in the other two:

```php
        // A published tournament is finished. Its players have been told where
        // they came, and a place is a position in a field -- so nothing may
        // change the field's size or its finishes.
        if ($refusal = $tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }
```

- [ ] **Step 4: Guard registrant removal**

In `app/Http/Controllers/Poker/PokerTournamentRegistrantController.php`, add this as the first statement of `destroy`, **before** the existing `hasRecordedResults()` check so the more specific reason wins:

```php
        // Checked before the results rule below, which would also refuse this
        // but for a reason that is true of any scored tournament. "Results have
        // been published" is what an administrator here needs to hear.
        if ($refusal = $registrant->tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }
```

- [ ] **Step 5: Guard the three result actions**

In `app/Http/Controllers/Poker/PokerTournamentResultController.php`:

In `store`, after the existing `$validated = $request->validate([...])` block:

```php
        $tournament = PokerTournament::findOrFail($validated['tournament_id']);

        if ($refusal = $tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }
```

In `update`, as the first statement:

```php
        if ($refusal = $result->tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }
```

In `destroy`, as the first statement:

```php
        if ($refusal = $result->tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }
```

Add `use App\Models\PokerTournament;` to the imports if it is not already there.

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=PublishedTournamentLockTest`
Expected: PASS, 9 tests.

- [ ] **Step 7: Mutation-check the guard**

Delete the guard from `PokerTournamentResultController@destroy`, run the file, and confirm exactly one test fails rather than none. Restore it. Repeat for one other call site. A guard nothing proves is a guard nothing keeps.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: green. `EliminatePlayerTest` and `RegistrantRemovalTest` exercise these same controllers and must be unaffected — nothing they do publishes.

- [ ] **Step 9: Commit (hand-off)**

Files: the three controllers, `tests/Feature/PublishedTournamentLockTest.php`

```
feat(tournaments): lock a tournament once its results are published

Six paths could otherwise move a field whose players have already been
told where they finished. All six ask one method on the model, because
six copies of a rule are six chances for it to drift.

Two of them -- unregister and registrant removal -- were already refused
by hasRecordedResults(), since a published tournament necessarily has a
result for everyone. What this adds there is the reason an administrator
reads, so those two tests assert the message rather than the refusal.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HpG1pmKxAnRgEpKmNSnNzG
```

---

### Task 5: Reading, unreading and deleting

**Files:**
- Create: `app/Http/Controllers/NotificationController.php`
- Modify: `routes/web.php` (inside `Route::middleware('auth')->group(...)`, before the `/tournaments/{tournament}` routes)
- Test: `tests/Feature/NotificationManagementTest.php`

**Interfaces:**
- Produces: routes `notifications.update` (PATCH), `notifications.destroy` (DELETE), `notifications.clear-read` (DELETE). Tasks 7 and 8 post to all three.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/NotificationManagementTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A player's own notifications are theirs to manage.
 *
 * The obvious hole in a feature like this is a route model binding that will
 * happily fetch somebody else's row, so every action here is scoped through the
 * authenticated user's own relation rather than checked afterwards.
 */
class NotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TournamentPlacement',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'tournament_id' => 'abc', 'tournament_name' => 'Autumn Showdown',
                'played_on' => '2026-09-09', 'place' => 1, 'points' => 100,
            ],
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_a_player_can_mark_one_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->notify($user);

        $this->actingAs($user)->patch(route('notifications.update', $notification))
            ->assertSessionHas('status');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_the_same_control_marks_it_unread_again(): void
    {
        // One route, one button whose label flips. Two routes for two halves of
        // one toggle is two things to keep in step.
        $user = User::factory()->create();
        $notification = $this->notify($user, read: true);

        $this->actingAs($user)->patch(route('notifications.update', $notification));

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_a_read_notification_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $notification = $this->notify($user, read: true);

        $this->actingAs($user)->delete(route('notifications.destroy', $notification))
            ->assertSessionHas('status');

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_an_unread_notification_cannot_be_deleted(): void
    {
        // Deleting something you have not looked at is how a player loses a
        // result they never saw.
        $user = User::factory()->create();
        $notification = $this->notify($user);

        $this->actingAs($user)->delete(route('notifications.destroy', $notification))
            ->assertSessionHas('error');

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_clearing_read_leaves_the_unread_ones(): void
    {
        $user = User::factory()->create();
        $this->notify($user, read: true);
        $this->notify($user, read: true);
        $this->notify($user);

        $this->actingAs($user)->delete(route('notifications.clear-read'))
            ->assertSessionHas('status');

        $this->assertSame(1, $user->fresh()->notifications()->count());
        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_clearing_read_touches_nobody_else(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->notify($user, read: true);
        $this->notify($other, read: true);

        $this->actingAs($user)->delete(route('notifications.clear-read'));

        $this->assertSame(1, $other->fresh()->notifications()->count());
    }

    public function test_a_player_cannot_reach_another_players_notification(): void
    {
        // 404 rather than 403: a forbidden response confirms the row exists.
        $user = User::factory()->create();
        $victim = $this->notify(User::factory()->create(), read: true);

        $this->actingAs($user)->patch(route('notifications.update', $victim))->assertNotFound();
        $this->actingAs($user)->delete(route('notifications.destroy', $victim))->assertNotFound();

        $this->assertSame(1, DatabaseNotification::count());
        $this->assertNull($victim->fresh()->read_at ? null : 'unchanged');
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $notification = $this->notify(User::factory()->create());

        $this->patch(route('notifications.update', $notification))->assertRedirect(route('login'));
        $this->delete(route('notifications.clear-read'))->assertRedirect(route('login'));
    }

    public function test_the_literal_clear_route_is_not_swallowed_by_the_parameter(): void
    {
        // /notifications/read must be declared before /notifications/{id} or it
        // is matched as an id and 404s. Ids are UUIDs so a real collision cannot
        // happen, which is exactly why the bug would be silent.
        $user = User::factory()->create();
        $this->notify($user, read: true);

        $this->actingAs($user)->delete('/notifications/read')->assertSessionHas('status');

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=NotificationManagementTest`
Expected: FAIL — `Route [notifications.update] not defined`.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/NotificationController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A player's own notifications.
 *
 * Every action reaches its row through the authenticated user's own relation
 * rather than through a route model binding. Binding first and checking
 * ownership afterwards is the same code with a hole in it, and it answers 403
 * where 404 is correct -- a forbidden response confirms the row exists.
 */
class NotificationController extends Controller
{
    /** Read becomes unread and unread becomes read: one control, one route. */
    public function update(Request $request, string $notification): RedirectResponse
    {
        $row = $this->own($request, $notification);

        $row->read_at ? $row->markAsUnread() : $row->markAsRead();

        return back()->with('status', $row->fresh()->read_at
            ? __('Marked as read.')
            : __('Marked as unread.'));
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $row = $this->own($request, $notification);

        if (! $row->read_at) {
            return back()->with('error', __('Mark a notification as read before deleting it.'));
        }

        $row->delete();

        return back()->with('status', __('Notification deleted.'));
    }

    /** Everything already read, in one go. */
    public function clearRead(Request $request): RedirectResponse
    {
        $cleared = $request->user()->readNotifications()->delete();

        return back()->with('status', trans_choice(
            '{0}Nothing to clear.|{1}1 notification cleared.|[2,*]:count notifications cleared.',
            $cleared,
            ['count' => $cleared]
        ));
    }

    /** Scoped to the signed-in user, so somebody else's id is simply not found. */
    private function own(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, inside `Route::middleware('auth')->group(function () {`, immediately after the profile routes:

```php
    // The literal path is declared FIRST. Behind /notifications/{notification}
    // it would be matched as an id and 404 -- and because ids are UUIDs, no
    // real notification could ever shadow it, so the bug would be invisible.
    Route::delete('/notifications/read', [\App\Http\Controllers\NotificationController::class, 'clearRead'])
        ->name('notifications.clear-read');
    Route::patch('/notifications/{notification}', [\App\Http\Controllers\NotificationController::class, 'update'])
        ->name('notifications.update');
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\NotificationController::class, 'destroy'])
        ->name('notifications.destroy');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=NotificationManagementTest`
Expected: PASS, 9 tests.

- [ ] **Step 6: Prove the route order matters**

Move the `notifications.clear-read` route below the two parameterised ones, run the file, and confirm `test_the_literal_clear_route_is_not_swallowed_by_the_parameter` fails. Put it back.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: green. `RouteSmokeTest` may need the three new routes acknowledged — check how it enumerates routes before assuming a failure is a bug.

- [ ] **Step 8: Commit (hand-off)**

Files: `app/Http/Controllers/NotificationController.php`, `routes/web.php`, `tests/Feature/NotificationManagementTest.php`

```
feat(notifications): let players read, unread and delete their own

Every action reaches its row through the signed-in user's own relation
rather than a route model binding checked afterwards -- which is the same
code with a hole in it, and answers 403 where 404 is correct.

Deleting is refused until a notification has been read, because deleting
something you have not looked at is how a player loses a result they
never saw.

/notifications/read is declared before /notifications/{notification}. Ids
are UUIDs, so nothing could ever shadow the literal path by accident and
the ordering bug would have been silent.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HpG1pmKxAnRgEpKmNSnNzG
```

---

### Task 6: The notification card and its fanfare

**Files:**
- Create: `resources/views/components/notification-card.blade.php`
- Create: `resources/css/3-components/_notification.css`
- Modify: `resources/css/app.css` (add the import beside the other `3-components` entries)
- Test: `tests/Feature/NotificationCardTest.php`

**Interfaces:**
- Consumes: routes from Task 5.
- Produces: `<x-notification-card :notification="$notification" />`, taking an `Illuminate\Notifications\DatabaseNotification`. Tasks 7 and 8 render it.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/NotificationCardTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * How a placement looks.
 *
 * The tier is the whole point: a first place should carry the same gold the
 * player just saw on the podium, and a fourth place should not claim a medal it
 * did not win.
 */
class NotificationCardTest extends TestCase
{
    use RefreshDatabase;

    private function card(int $place, int $points, bool $read = false): string
    {
        $user = User::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TournamentPlacement',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'tournament_id' => 'abc',
                'tournament_name' => 'Autumn Showdown',
                'played_on' => '2026-09-09',
                'place' => $place,
                'points' => $points,
            ],
            'read_at' => $read ? now() : null,
        ]);

        return Blade::render('<x-notification-card :notification="$n" />', ['n' => $notification]);
    }

    public function test_first_place_wears_gold(): void
    {
        $this->assertStringContainsString('notification--gold', $this->card(1, 100));
    }

    public function test_second_and_third_wear_silver_and_bronze(): void
    {
        $this->assertStringContainsString('notification--silver', $this->card(2, 60));
        $this->assertStringContainsString('notification--bronze', $this->card(3, 40));
    }

    public function test_a_scoring_finish_off_the_podium_is_not_a_medal(): void
    {
        $html = $this->card(4, 20);

        $this->assertStringContainsString('notification--scored', $html);
        $this->assertStringNotContainsString('notification--gold', $html);
        $this->assertStringNotContainsString('notification--bronze', $html);
    }

    public function test_it_says_the_place_the_tournament_and_the_points(): void
    {
        $html = $this->card(1, 100);

        $this->assertStringContainsString('1st', $html);
        $this->assertStringContainsString('Autumn Showdown', $html);
        $this->assertStringContainsString('100', $html);
    }

    public function test_an_unread_card_is_marked_as_unread(): void
    {
        $this->assertStringContainsString('notification--unread', $this->card(1, 100));
        $this->assertStringNotContainsString('notification--unread', $this->card(1, 100, read: true));
    }

    public function test_an_unread_card_offers_read_but_not_delete(): void
    {
        $html = $this->card(1, 100);

        $this->assertStringContainsString('Mark as read', $html);
        $this->assertStringNotContainsString('Delete notification', $html);
    }

    public function test_a_read_card_offers_unread_and_delete(): void
    {
        $html = $this->card(1, 100, read: true);

        $this->assertStringContainsString('Mark as unread', $html);
        $this->assertStringContainsString('Delete notification', $html);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=NotificationCardTest`
Expected: FAIL — unable to locate component `notification-card`.

- [ ] **Step 3: Create the component**

Create `resources/views/components/notification-card.blade.php`:

```blade
@props(['notification'])

@php
    $data = $notification->data;
    $place = (int) ($data['place'] ?? 0);
    $read = $notification->read_at !== null;

    // The tier. 1-3 reuse the podium's medal tokens so the message matches the
    // podium the player just saw; a scoring finish below that gets the accent
    // instead, because it is worth telling someone about without claiming a
    // medal it did not win.
    $tier = match ($place) {
        1 => 'notification--gold',
        2 => 'notification--silver',
        3 => 'notification--bronze',
        default => 'notification--scored',
    };
@endphp

<article class="notification {{ $tier }}{{ $read ? '' : ' notification--unread' }}">
    <span class="notification__place">{{ \Illuminate\Support\Number::ordinal($place) }}</span>

    <div class="notification__body">
        <p class="notification__title">{{ $data['tournament_name'] ?? __('A tournament') }}</p>

        <p class="notification__meta">
            {{ number_format((int) ($data['points'] ?? 0)) }} {{ __('pts') }}
            &middot;
            {{ \Illuminate\Support\Carbon::parse($data['played_on'])->format('M j, Y') }}
        </p>
    </div>

    <div class="notification__actions">
        {{-- One route for both directions; the label says which way this click
             goes. --}}
        <form action="{{ route('notifications.update', $notification->id) }}" method="POST">
            @csrf
            @method('PATCH')

            <x-action icon="{{ $read ? 'view' : 'approve' }}"
                      :label="$read ? __('Mark as unread') : __('Mark as read')" />
        </form>

        {{-- Only once read. Deleting something you have not looked at is how a
             player loses a result they never saw, and the controller refuses it
             too. --}}
        @if ($read)
            <form action="{{ route('notifications.destroy', $notification->id) }}" method="POST"
                  data-confirm="{{ __('Delete this notification about :tournament?', [
                      'tournament' => $data['tournament_name'] ?? __('a tournament'),
                  ]) }}">
                @csrf
                @method('DELETE')

                <x-action icon="delete" :label="__('Delete notification')" danger />
            </form>
        @endif
    </div>
</article>
```

- [ ] **Step 4: Create the stylesheet**

Create `resources/css/3-components/_notification.css`:

```css
/* One placement in a notification list. */
.notification {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    border-block-end: var(--border-width) solid var(--c-border);
}

.notification:last-child {
    border-block-end: 0;
}

/* The unread mark is the leading edge, not a colour on the text: a list has to
   stay readable, and reading it is what makes these read. */
.notification--unread {
    border-inline-start: 3px solid var(--c-primary);
    background-color: var(--c-surface-raised);
}

.notification__place {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--radius-pill);
    background-color: var(--c-surface-raised);
    font-family: var(--font-mono);
    font-size: var(--step--1);
    font-weight: 700;
    color: var(--c-text-muted);
}

/* The medals, reusing the podium's tokens and its fixed ink -- those contrast
   ratios were measured against these three grounds in both themes. */
.notification--gold .notification__place {
    background-color: var(--c-gold);
    color: var(--c-medal-ink);
}

.notification--silver .notification__place {
    background-color: var(--c-silver);
    color: var(--c-medal-ink);
}

.notification--bronze .notification__place {
    background-color: var(--c-bronze);
    color: var(--c-medal-ink);
}

/* Scored, but not a medal. */
.notification--scored .notification__place {
    background-color: var(--c-primary-fill);
    color: var(--c-primary-ink);
}

.notification__body {
    min-width: 0;
    flex: 1;
}

.notification__title {
    margin: 0;
    font-weight: 600;
    line-height: var(--leading-tight);
}

.notification__meta {
    margin: 0;
    font-size: var(--step--2);
    color: var(--c-text-muted);
}

.notification__actions {
    display: flex;
    align-items: center;
    gap: var(--space-1);
    flex-shrink: 0;
}

/* The list itself: capped so a long history scrolls inside the panel rather
   than growing it past the bottom of the window. */
.notification-list {
    max-height: 20rem;
    overflow-y: auto;
}
```

- [ ] **Step 5: Register the stylesheet**

In `resources/css/app.css`, add beside the other `3-components` imports, in alphabetical position:

```css
@import "./3-components/_notification.css";
```

- [ ] **Step 6: Build and run the test**

Run: `npm run build && php artisan test --filter=NotificationCardTest`
Expected: PASS, 7 tests.

- [ ] **Step 7: Confirm the tokens exist**

Grep `resources/css/1-base/_tokens.css` for `--c-gold`, `--c-silver`, `--c-bronze`, `--c-medal-ink`, `--c-primary-fill` and `--c-primary-ink`. All six are already used by the podium and the buttons, but a typo here fails silently as an unstyled circle rather than as an error.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: green, including `InlineStyleGuardTest` and `PublicRegisterTest`.

- [ ] **Step 9: Commit (hand-off)**

Files: `resources/views/components/notification-card.blade.php`, `resources/css/3-components/_notification.css`, `resources/css/app.css`, `tests/Feature/NotificationCardTest.php`

```
feat(notifications): the placement card and its fanfare

First, second and third reuse the podium's medal tokens, so the message
carries the same gold the player just saw. A scoring finish below the
podium takes the accent instead -- worth telling someone about, without
claiming a medal it did not win.

Delete appears only once a notification has been read, matching the
controller rather than restating it.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HpG1pmKxAnRgEpKmNSnNzG
```

---

### Task 7: The desktop dropdown and the unread badge

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (a view composer, beside `composeAuthenticationEmails()`)
- Modify: `resources/views/layouts/navigation.blade.php` (lines ~63-82, the `topbar__actions-desktop` block)
- Modify: `resources/css/3-components/_dropdown.css`
- Modify: `resources/css/3-components/_nav.css`
- Test: `tests/Feature/NotificationBellTest.php`

**Interfaces:**
- Consumes: `<x-notification-card>` (Task 6), routes (Task 5).
- Produces: `$unreadNotificationCount` (int) and `$recentNotifications` (Collection) bound to `layouts.navigation`. Task 8 uses both.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/NotificationBellTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Where a player finds their notifications.
 *
 * Two surfaces, because the topbar is two structures rather than one restyled:
 * a dropdown on desktop, and on mobile a flat row that has never had one.
 */
class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, string $tournament = 'Autumn Showdown', bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TournamentPlacement',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'tournament_id' => (string) Str::uuid(), 'tournament_name' => $tournament,
                'played_on' => '2026-09-09', 'place' => 1, 'points' => 100,
            ],
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_the_badge_counts_unread_only(): void
    {
        $user = User::factory()->create(['approval_status' => 'approved']);
        $this->notify($user);
        $this->notify($user);
        $this->notify($user, read: true);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('unreadNotificationCount', 2);
    }

    public function test_there_is_no_badge_at_zero(): void
    {
        // A badge showing 0 is a badge saying nothing, loudly.
        $user = User::factory()->create(['approval_status' => 'approved']);
        $this->notify($user, read: true);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('unreadNotificationCount', 0)
            ->assertDontSee('nav-link__badge', false);
    }

    public function test_the_dropdown_lists_notifications(): void
    {
        $user = User::factory()->create(['approval_status' => 'approved']);
        $this->notify($user, 'Autumn Showdown');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Autumn Showdown')
            ->assertSee('notification--gold', false);
    }

    public function test_the_dropdown_still_holds_profile_and_log_out(): void
    {
        // The redesign must not lose what the menu was already for.
        $user = User::factory()->create(['approval_status' => 'approved']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee(route('profile.edit'), false)
            ->assertSee(route('logout'), false);
    }

    public function test_an_empty_list_says_so(): void
    {
        $user = User::factory()->create(['approval_status' => 'approved']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('No notifications yet.');
    }

    public function test_clear_read_is_offered_only_when_something_is_read(): void
    {
        $user = User::factory()->create(['approval_status' => 'approved']);
        $this->notify($user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Clear read', false);

        $this->notify($user, read: true);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Clear read', false);
    }

    public function test_the_list_is_capped_and_newest_first(): void
    {
        $user = User::factory()->create(['approval_status' => 'approved']);

        for ($i = 1; $i <= 12; $i++) {
            $this->notify($user, "Tournament {$i}")->forceFill(['created_at' => now()->addMinutes($i)])->save();
        }

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('recentNotifications', fn ($rows) => $rows->count() === 10
                && $rows->first()->data['tournament_name'] === 'Tournament 12');
    }

    public function test_a_guest_page_has_no_notification_surface(): void
    {
        // layouts/public renders the same topbar component.
        $this->get(route('home'))->assertOk()->assertDontSee('nav-link__badge', false);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=NotificationBellTest`
Expected: FAIL — `Failed asserting that the view has key 'unreadNotificationCount'`.

- [ ] **Step 3: Bind the data with a view composer**

In `app/Providers/AppServiceProvider.php`, add `use Illuminate\Support\Facades\View;` and call a new private method from `boot()`:

```php
        $this->composeNotifications();
```

Then add:

```php
    /**
     * The topbar's notification data, on every page that draws it.
     *
     * A composer rather than a per-controller concern: this is layout data,
     * and thirty-odd controllers should not each remember to supply it. Guests
     * get nothing, so the public shell pays for none of it.
     */
    private function composeNotifications(): void
    {
        View::composer('layouts.navigation', function ($view) {
            $user = auth()->user();

            $view->with([
                'unreadNotificationCount' => $user?->unreadNotifications()->count() ?? 0,
                // Capped: the panel scrolls, but a player with three seasons of
                // history should not have all of it serialised into every page.
                'recentNotifications' => $user
                    ? $user->notifications()->latest()->limit(10)->get()
                    : collect(),
            ]);
        });
    }
```

- [ ] **Step 4: Rebuild the desktop dropdown**

In `resources/views/layouts/navigation.blade.php`, replace the `x-dropdown` inside `topbar__actions-desktop` with:

```blade
            <x-dropdown align="right">
                <x-slot name="trigger">
                    <button type="button" class="nav-link nav-link--user">
                        <x-monogram :user="auth()->user()" size="sm" decorative />
                        <span>{{ auth()->user()->display_name }}</span>

                        {{-- Absent at zero. A badge reading 0 is a badge saying
                             nothing, loudly. --}}
                        @if ($unreadNotificationCount > 0)
                            <span class="nav-link__badge">
                                {{ $unreadNotificationCount }}
                                <span class="u-visually-hidden">{{ __('unread notifications') }}</span>
                            </span>
                        @endif
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="dropdown__section">
                        <p class="dropdown__heading">{{ __('Notifications') }}</p>

                        <div class="notification-list">
                            @forelse ($recentNotifications as $notification)
                                <x-notification-card :notification="$notification" />
                            @empty
                                <p class="dropdown__empty">{{ __('No notifications yet.') }}</p>
                            @endforelse
                        </div>

                        @if ($recentNotifications->whereNotNull('read_at')->isNotEmpty())
                            <form action="{{ route('notifications.clear-read') }}" method="POST"
                                  data-confirm="{{ __('Delete every notification you have already read?') }}">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="dropdown__item">{{ __('Clear read notifications') }}</button>
                            </form>
                        @endif
                    </div>

                    <div class="dropdown__section">
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Your profile') }}</x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown__item">{{ __('Log out') }}</button>
                        </form>
                    </div>
                </x-slot>
            </x-dropdown>
```

- [ ] **Step 5: Style the new pieces**

Append to `resources/css/3-components/_dropdown.css`:

```css
/* The menu holds two things now -- a notification list and the account links --
   so it needs a rule between them and a wider panel to hold a card. */
.dropdown__section + .dropdown__section {
    border-block-start: var(--border-width) solid var(--c-border);
}

.dropdown__heading {
    margin: 0;
    padding: var(--space-3) var(--space-4);
    font-size: var(--step--2);
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--c-text-muted);
}

.dropdown__empty {
    margin: 0;
    padding: var(--space-4);
    font-size: var(--step--1);
    color: var(--c-text-muted);
}

/* Wide enough for a place, a tournament name and two row actions. The account
   links alone never needed this much, so it is scoped to this dropdown rather
   than widening every menu in the app. */
.dropdown--notifications .dropdown__menu {
    width: min(22rem, calc(100vw - var(--space-6)));
}
```

Append to `resources/css/3-components/_nav.css`:

```css
/* The unread count, on the trigger's top-end corner. position:relative goes on
   the trigger so the badge is placed against the button rather than against
   whatever ancestor happens to be positioned. */
.nav-link--user {
    position: relative;
}

.nav-link__badge {
    position: absolute;
    inset-block-start: -0.25rem;
    inset-inline-end: -0.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.125rem;
    height: 1.125rem;
    padding-inline: 0.25rem;
    border-radius: var(--radius-pill);
    background-color: var(--c-primary-fill);
    font-family: var(--font-mono);
    font-size: var(--step--2);
    font-weight: 700;
    line-height: 1;
    color: var(--c-primary-ink);
}
```

Then change the dropdown's opening tag in Step 4 to carry that hook:

```blade
            <x-dropdown align="right" class="dropdown--notifications">
```

`x-dropdown` merges the class onto its root, so `.dropdown--notifications .dropdown__menu` reaches the panel. Two classes beat the single-class `.dropdown__menu`, so the width wins regardless of which stylesheet loads first — this project has had three defects from two single-class selectors colliding.

- [ ] **Step 6: Build and run the test**

Run: `npm run build && php artisan test --filter=NotificationBellTest`
Expected: the seven desktop tests PASS. `test_a_guest_page_has_no_notification_surface` should already pass — the public shell has no user dropdown.

- [ ] **Step 7: Screenshot it**

This project has no browser tests, so the only way to know it looks right is to look. Dump the dashboard as an authenticated admin with three notifications (one gold, one bronze, one read), rewrite the asset URLs to `file://` paths, and screenshot at 1280 wide with the dropdown forced open — Alpine does not run over `file://`, so temporarily replace `x-show="open"` with nothing in a scratch copy of the HTML rather than editing the component.

Check: the badge sits on the trigger's corner and is not clipped; the medal circles are the right three colours; the panel is not wider than the viewport; the two sections are separated.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: green.

- [ ] **Step 9: Commit (hand-off)**

Files: `app/Providers/AppServiceProvider.php`, `resources/views/layouts/navigation.blade.php`, `resources/css/3-components/_dropdown.css`, `resources/css/3-components/_nav.css`, `tests/Feature/NotificationBellTest.php`

```
feat(notifications): put notifications in the desktop user menu

The menu becomes two sections -- a capped, scrolling list of placements
above the account links it already held. A view composer supplies the
count and the list, because this is layout data and thirty-odd
controllers should not each remember to provide it.

The badge is absent at zero rather than showing one: a badge reading 0 is
a badge saying nothing, loudly.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HpG1pmKxAnRgEpKmNSnNzG
```

---

### Task 8: The mobile bell

The mobile topbar has no dropdown today — `topbar__actions-mobile` is a flat row of Log out, theme toggle and a profile link. This is new construction. The bell goes in `topbar__inner`, left of the burger, which means `x-topbar` needs a new slot; the public shell renders the same component and must be unaffected.

**Files:**
- Modify: `resources/views/components/topbar.blade.php` (add a `leading` slot before the burger at line ~19)
- Modify: `resources/views/layouts/navigation.blade.php` (fill the new slot)
- Modify: `resources/css/2-layout/_topbar.css`
- Test: `tests/Feature/NotificationBellTest.php` (add to the file from Task 7)

**Interfaces:**
- Consumes: `$unreadNotificationCount`, `$recentNotifications` (Task 7), `<x-notification-card>` (Task 6).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/NotificationBellTest.php`:

```php
    public function test_the_mobile_bell_is_rendered_for_a_signed_in_player(): void
    {
        $user = User::factory()->create(['approval_status' => 'approved']);
        $this->notify($user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('topbar__bell', false)
            ->assertSee('Notifications', false);
    }

    public function test_the_bell_carries_its_own_badge(): void
    {
        $user = User::factory()->create(['approval_status' => 'approved']);
        $this->notify($user);
        $this->notify($user);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $bell = substr($html, (int) strpos($html, 'topbar__bell'));
        $bell = substr($bell, 0, (int) strpos($bell, '</button>'));

        $this->assertStringContainsString('topbar__bell-badge', $bell);
        $this->assertStringContainsString('2', $bell);
    }

    public function test_the_bell_sits_before_the_burger(): void
    {
        // "Left of the menu button" is the requirement, and in a
        // direction-agnostic layout that is document order.
        $user = User::factory()->create(['approval_status' => 'approved']);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'topbar__burger'),
            strpos($html, 'topbar__bell'),
            'The bell must be rendered before the burger.'
        );
    }

    public function test_the_public_shell_has_no_bell(): void
    {
        // x-topbar is shared with the public site, which has no signed-in user
        // and must be untouched by the new slot.
        $this->get(route('home'))->assertOk()->assertDontSee('topbar__bell', false);
    }

    public function test_a_guest_visiting_a_public_page_sees_no_notifications(): void
    {
        $this->get(route('events'))->assertOk()
            ->assertDontSee('topbar__bell', false)
            ->assertDontSee('notification--gold', false);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=NotificationBellTest`
Expected: FAIL — the five new tests report `topbar__bell` not found.

- [ ] **Step 3: Add the slot to the topbar**

In `resources/views/components/topbar.blade.php`, change the props line to:

```blade
@props(['links' => null, 'actions' => null, 'leading' => null])
```

and insert immediately before the `<button type="button" class="topbar__burger" ...>`:

```blade
        {{-- Anything that belongs beside the burger rather than inside the
             collapsing panel. The authenticated shell puts the notification
             bell here; the public shell passes nothing and is unchanged. --}}
        @if ($leading)
            {{ $leading }}
        @endif
```

- [ ] **Step 4: Fill the slot**

In `resources/views/layouts/navigation.blade.php`, add a `leading` slot to the `<x-topbar>` call, before the `links` slot:

```blade
    <x-slot name="leading">
        {{-- Mobile only: on desktop the notifications live in the user menu,
             and two surfaces showing the same list at once is one too many.
             .topbar__bell is hidden above the 48rem breakpoint. --}}
        <x-dropdown align="right" class="topbar__bell-menu">
            <x-slot name="trigger">
                <button type="button" class="topbar__bell">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>

                    <span class="u-visually-hidden">{{ __('Notifications') }}</span>

                    @if ($unreadNotificationCount > 0)
                        <span class="topbar__bell-badge">
                            {{ $unreadNotificationCount }}
                            <span class="u-visually-hidden">{{ __('unread notifications') }}</span>
                        </span>
                    @endif
                </button>
            </x-slot>

            <x-slot name="content">
                <p class="dropdown__heading">{{ __('Notifications') }}</p>

                <div class="notification-list">
                    @forelse ($recentNotifications as $notification)
                        <x-notification-card :notification="$notification" />
                    @empty
                        <p class="dropdown__empty">{{ __('No notifications yet.') }}</p>
                    @endforelse
                </div>

                @if ($recentNotifications->whereNotNull('read_at')->isNotEmpty())
                    <form action="{{ route('notifications.clear-read') }}" method="POST"
                          data-confirm="{{ __('Delete every notification you have already read?') }}">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="dropdown__item">{{ __('Clear read notifications') }}</button>
                    </form>
                @endif
            </x-slot>
        </x-dropdown>
    </x-slot>
```

- [ ] **Step 5: Style the bell**

Append to `resources/css/2-layout/_topbar.css`:

```css
/* The notification bell, beside the burger. Mobile only: on desktop the same
   list lives in the user menu, and two surfaces showing it at once is one too
   many. Same breakpoint the rest of the topbar splits on. */
.topbar__bell-menu {
    margin-inline-start: auto;
}

.topbar__bell {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border: var(--border-width) solid var(--c-border);
    border-radius: var(--radius);
    background-color: transparent;
    color: var(--c-text);
    cursor: pointer;
}

.topbar__bell svg {
    width: 1.125rem;
    height: 1.125rem;
}

.topbar__bell:focus-visible {
    outline: 2px solid var(--c-primary);
    outline-offset: 2px;
}

.topbar__bell-badge {
    position: absolute;
    inset-block-start: -0.375rem;
    inset-inline-end: -0.375rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.125rem;
    height: 1.125rem;
    padding-inline: 0.25rem;
    border-radius: var(--radius-pill);
    background-color: var(--c-primary-fill);
    font-family: var(--font-mono);
    font-size: var(--step--2);
    font-weight: 700;
    line-height: 1;
    color: var(--c-primary-ink);
}

@media (min-width: 48rem) {
    .topbar__bell-menu {
        display: none;
    }
}
```

- [ ] **Step 6: Build and run the test**

Run: `npm run build && php artisan test --filter=NotificationBellTest`
Expected: PASS, 13 tests.

- [ ] **Step 7: Screenshot both widths**

Dump the dashboard as a signed-in player with notifications, and screenshot at **390px** (phone) and **1280px** (desktop). Check at 390: the bell sits left of the burger, the badge is not clipped by the topbar's edge, and the panel fits the viewport. Check at 1280: the bell is gone entirely and only the user menu carries notifications.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: green, including `InlineStyleGuardTest`, `MarkupBalanceTest` and `RouteSmokeTest`.

- [ ] **Step 9: Update the resume document**

In `docs/RESUME-HERE.md`, update the suite count and add a section under the dated headings recording: that publishing is an explicit act because "all results are in" is not a stable state; that publishing locks six paths through one method on the model; that unpublishing retracts its notifications; that recipients are `points > 0` rather than the podium; and that `x-topbar` gained a `leading` slot shared with the public shell.

- [ ] **Step 10: Commit (hand-off)**

Files: `resources/views/components/topbar.blade.php`, `resources/views/layouts/navigation.blade.php`, `resources/css/2-layout/_topbar.css`, `tests/Feature/NotificationBellTest.php`, `docs/RESUME-HERE.md`

```
feat(notifications): a notification bell for mobile

The mobile topbar had no dropdown at all -- a flat row of Log out, theme
toggle and a profile link -- so this is new construction rather than a
restyle. The bell sits left of the burger, which means x-topbar needed a
leading slot; the public shell passes nothing and is unchanged.

Mobile only, at the breakpoint the topbar already splits on. On desktop
the same list is in the user menu, and two surfaces showing it at once is
one too many.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01HpG1pmKxAnRgEpKmNSnNzG
```

---

## Self-review notes

**Spec coverage.** §1 storage → Task 1. §2 publishing, unpublishing, the no-points case → Task 3 (schema and predicates in Task 2). §3 the lock, all six paths → Task 4. §4 the notification, recipients, tiered fanfare → Tasks 1 and 6. §5 read/unread/delete/clear, route order, ownership → Task 5. §6 desktop → Task 7, mobile → Task 8. §7 data loading → Task 7's composer. §9 testing → every task's test file.

**Type consistency.** `isComplete()`, `isPublished()` and `publishedRefusal()` are defined in Task 2 and used with those exact names in Tasks 3 and 4. `TournamentPlacement` takes a `PokerTournamentResult` in Task 1 and is constructed that way in Task 3. `$unreadNotificationCount` and `$recentNotifications` are produced in Task 7 and consumed in Task 8 under the same names. `<x-notification-card :notification="…">` is defined in Task 6 and called identically in Tasks 7 and 8.

**Known risk carried forward.** `where('data->tournament_id', …)` in Task 3 compiles per driver. Task 3 Step 7 exists specifically to check it on MySQL, because this project has already shipped a query that passed on SQLite and failed the MySQL CI leg.
