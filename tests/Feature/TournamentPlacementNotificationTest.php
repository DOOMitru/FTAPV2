<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\TournamentPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    public function test_the_notifiable_column_holds_a_ulid_rather_than_an_integer(): void
    {
        // The send tests above do NOT catch this, and that is the point.
        // SQLite is dynamically typed: it stores a 26-character ULID in a
        // bigint column without complaint, so every one of them passes locally
        // against a schema that would fail on MySQL in CI. Asserting the column
        // itself is what makes the fault visible on the driver we develop on.
        //
        // The TYPE NAME differs by driver, though, which this test learned the
        // hard way -- it asserted 'varchar' and broke the MySQL leg of the very
        // pipeline it exists to protect. ulidMorphs creates a CHAR(26); SQLite
        // reports that as varchar and MySQL as char. What matters is that it is
        // a string type at all: morphs would give integer on SQLite and bigint
        // on MySQL, and either fails this list.
        $this->assertContains(
            Schema::getColumnType('notifications', 'notifiable_id'),
            ['char', 'varchar'],
            'notifiable_id must hold a ULID. An integer type means the migration used morphs().'
        );
    }

    public function test_it_is_stored_rather_than_mailed(): void
    {
        // via() is database only. A mass mail on every league night is a
        // different decision with a different cost.
        $result = $this->scoredResult(place: 1, points: 100);

        $this->assertSame(['database'], (new TournamentPlacement($result))->via($result->user));
    }
}
