<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * Taking back the most recent elimination.
 *
 * Places are handed out from the bottom up, so the player who went out LAST
 * holds the LOWEST place on record -- not the newest row. A late registration
 * shifts every recorded place and reprices it, so `created_at` and `place` can
 * tell different stories about order, and only one of them is the authority.
 *
 * Deleting the lowest place leaves every other finish exactly where it is and
 * returns `registrants - results`, so the next elimination hands out the same
 * place at the same price. That is the whole feature: no repricing, no shift,
 * and no second points calculation beside eliminate()'s.
 */
class UndoEliminationTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    /** @return array<int, User> */
    private function field(PokerTournament $tournament, int $size): array
    {
        $players = [];

        for ($i = 1; $i <= $size; $i++) {
            $player = User::factory()->create(['first_name' => 'Player', 'last_name' => (string) $i]);
            $this->enter($tournament, $player);
            $players[] = $player;
        }

        return $players;
    }

    /** A structure paying 10 places, steeper at the top. */
    private function payTen(): void
    {
        foreach ([1 => 100, 2 => 85, 3 => 75, 4 => 65, 5 => 55,
                  6 => 47, 7 => 40, 8 => 34, 9 => 29, 10 => 24] as $place => $points) {
            PointsStructure::create(['place' => $place, 'points' => $points]);
        }
    }

    private function eliminate(PokerTournament $tournament, User $player, ?User $actor = null)
    {
        return $this->actingAs($actor ?? $this->admin())
            ->post(route('poker.tournaments.eliminate', $tournament), ['user_id' => $player->id]);
    }

    private function undo(PokerTournament $tournament, User $player, ?User $actor = null)
    {
        return $this->actingAs($actor ?? $this->admin())
            ->delete(route('poker.tournaments.uneliminate', $tournament), ['user_id' => $player->id]);
    }

    private function resultFor(User $player): ?PokerTournamentResult
    {
        return PokerTournamentResult::where('user_id', $player->id)->first();
    }

    public function test_undoing_removes_the_most_recent_finish(): void
    {
        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->eliminate($tournament, $players[0]);
        $this->eliminate($tournament, $players[1]);

        $this->undo($tournament, $players[1]);

        $this->assertNull($this->resultFor($players[1]), 'The most recent finish is gone.');
        $this->assertSame(5, $this->resultFor($players[0])?->place, 'The one before it is untouched.');
    }

    public function test_the_freed_place_is_handed_out_again_at_the_same_price(): void
    {
        $this->payTen();

        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->eliminate($tournament, $players[0]);
        $this->eliminate($tournament, $players[1]);

        $fourth = $this->resultFor($players[1]);
        $this->assertSame(4, $fourth?->place);

        $this->undo($tournament, $players[1]);
        $this->eliminate($tournament, $players[2]);

        $retaken = $this->resultFor($players[2]);
        $this->assertSame(4, $retaken?->place, 'The freed place is the next one on offer.');
        $this->assertSame($fourth->points, $retaken?->points, 'And it is worth what it was worth.');
    }

    public function test_undo_walks_back_one_at_a_time(): void
    {
        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        foreach ([0, 1, 2] as $i) {
            $this->eliminate($tournament, $players[$i]);
        }

        $this->undo($tournament, $players[2]);
        $this->undo($tournament, $players[1]);

        $this->assertSame(1, PokerTournamentResult::where('tournament_id', $tournament->id)->count());
        $this->assertSame(5, $this->resultFor($players[0])?->place);
    }

    public function test_only_the_most_recent_elimination_can_be_undone(): void
    {
        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->eliminate($tournament, $players[0]);
        $this->eliminate($tournament, $players[1]);

        // players[0] went out FIRST, so they are not the one to take back.
        $this->undo($tournament, $players[0])->assertSessionHas('error');

        $this->assertNotNull($this->resultFor($players[0]), 'Their finish survives.');
        $this->assertNotNull($this->resultFor($players[1]), 'And so does the one that is actually last.');
    }

    public function test_undoing_is_refused_when_nobody_is_out(): void
    {
        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->undo($tournament, $players[0])->assertSessionHas('error');

        $this->assertSame(0, PokerTournamentResult::where('tournament_id', $tournament->id)->count());
    }

    public function test_undoing_is_refused_once_results_are_published(): void
    {
        $this->payTen();

        $tournament = $this->tournament();
        $players = $this->field($tournament, 3);

        foreach ($players as $player) {
            $this->eliminate($tournament, $player);
        }

        $tournament->forceFill(['published_at' => now()])->save();

        $this->undo($tournament, $players[2])->assertSessionHas('error');

        $this->assertNotNull($this->resultFor($players[2]), 'A published finish stands.');
    }

    public function test_a_finished_but_unpublished_field_can_still_be_undone(): void
    {
        // Everybody out and nothing published is exactly when a wrong order
        // gets noticed -- somebody says "no, Dave went out before me".
        $this->payTen();

        $tournament = $this->tournament();
        $players = $this->field($tournament, 3);

        foreach ($players as $player) {
            $this->eliminate($tournament, $player);
        }

        $this->assertTrue($tournament->isComplete());

        $this->undo($tournament, $players[2]);

        $this->assertNull($this->resultFor($players[2]), 'The winner is back in the field.');
        $this->assertFalse($tournament->isComplete(), 'And the tournament is unfinished again.');
    }

    public function test_a_late_registration_does_not_confuse_which_finish_is_last(): void
    {
        // Registering shifts every recorded place down and reprices it, so the
        // newest ROW and the lowest PLACE stop agreeing with each other.
        $this->payTen();

        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->eliminate($tournament, $players[0]);
        $this->eliminate($tournament, $players[1]);

        $this->enter($tournament, User::factory()->create(['first_name' => 'Late', 'last_name' => 'Arrival']));

        $this->undo($tournament, $players[1]);

        $this->assertNull($this->resultFor($players[1]), 'The shifted finish is still the last one.');
        $this->assertSame(6, $this->resultFor($players[0])?->place, 'The other one keeps its shifted place.');
    }

    /**
     * The standings row for one player, as the page is handed it.
     *
     * @return array<string, mixed>
     */
    private function row(PokerTournament $tournament, User $player, ?User $viewer = null): array
    {
        $rows = [];

        $this->actingAs($viewer ?? $this->admin())
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                $rows = $page->toArray()['props']['standings'];
            });

        foreach ($rows as $row) {
            if (($row['player_id'] ?? null) === $player->id) {
                return $row;
            }
        }

        $this->fail('No standings row for that player.');
    }

    public function test_only_the_last_eliminated_row_offers_undo(): void
    {
        $this->payTen();

        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->eliminate($tournament, $players[0]);
        $this->eliminate($tournament, $players[1]);

        $this->assertTrue($this->row($tournament, $players[1])['can_undo']);
        $this->assertFalse($this->row($tournament, $players[0])['can_undo'], 'Only the most recent one.');
    }

    public function test_a_row_still_in_the_field_cannot_be_undone(): void
    {
        $this->payTen();

        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->eliminate($tournament, $players[0]);

        $this->assertFalse($this->row($tournament, $players[1])['can_undo'], 'They are still playing.');
    }

    public function test_undo_is_not_offered_once_results_are_published(): void
    {
        $this->payTen();

        $tournament = $this->tournament();
        $players = $this->field($tournament, 3);

        foreach ($players as $player) {
            $this->eliminate($tournament, $player);
        }

        $tournament->forceFill(['published_at' => now()])->save();

        $this->assertFalse($this->row($tournament, $players[2])['can_undo']);
    }

    public function test_undo_is_not_offered_to_a_player(): void
    {
        $this->payTen();

        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->eliminate($tournament, $players[0]);

        $viewer = User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);

        $this->assertFalse($this->row($tournament, $players[0], $viewer)['can_undo']);
    }

    public function test_a_result_with_no_registration_behind_it_is_not_undoable(): void
    {
        // user_id is nullable on results -- a deleted player leaves the
        // snapshotted name behind. The route addresses a player by user_id, so
        // such a row cannot be undone and must not offer a button that would
        // be refused.
        $tournament = $this->tournament();
        $this->field($tournament, 3);

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => null,
            'player_name' => 'Departed Player',
            'place' => 3,
            'points' => 75,
        ]);

        $rows = [];

        $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                $rows = $page->toArray()['props']['standings'];
            });

        $orphan = collect($rows)->firstWhere('name', 'Departed Player');

        $this->assertNotNull($orphan, 'The row is still shown.');
        $this->assertFalse($orphan['can_undo'], 'But it cannot be undone.');
    }

    public function test_the_confirmation_names_the_place_and_points_being_given_up(): void
    {
        $this->payTen();

        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->eliminate($tournament, $players[0]);

        $confirm = $this->row($tournament, $players[0])['undo_confirm'];

        $this->assertStringContainsString('5th', $confirm, 'The place being given up.');
        $this->assertStringContainsString('55', $confirm, 'And the points going with it.');
    }

    public function test_a_player_cannot_undo_an_elimination(): void
    {
        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->eliminate($tournament, $players[0]);

        $this->undo($tournament, $players[0], User::factory()->create(['is_admin' => false]))
            ->assertForbidden();

        $this->assertNotNull($this->resultFor($players[0]));
    }
}
