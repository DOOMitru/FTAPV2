<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * Repairing finishes whose points do not match their place.
 *
 * The damage this undoes: a late registration used to shift every recorded
 * finish down a place without moving its points, so a finish held the money for
 * a place it no longer occupied. The hook does both now; these rows predate it.
 *
 * The command is a repair tool pointed at real data, so the tests that matter
 * most are the ones about what it must NOT touch.
 */
class RepriceResultsTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private function structure(): void
    {
        foreach ([1 => 100, 2 => 85, 3 => 75] as $place => $points) {
            PointsStructure::create(['place' => $place, 'points' => $points]);
        }
    }

    private function player(string $name = 'Wanda Reeve'): User
    {
        [$first, $last] = explode(' ', $name);

        return User::factory()->create(['first_name' => $first, 'last_name' => $last]);
    }

    /** A finish with deliberately stale points, as the bug left them. */
    private function stale(PokerTournament $t, int $place, int $points): PokerTournamentResult
    {
        return $this->score($t, $this->player(), $place, $points);
    }

    public function test_a_stale_finish_is_repriced(): void
    {
        $this->structure();
        $result = $this->stale($this->tournament(), 2, 100);

        $this->artisan('results:reprice --force')->assertSuccessful();

        $this->assertSame(85, $result->fresh()->points, '2nd pays 85, not the 100 it was holding.');
    }

    public function test_a_finish_past_the_end_of_the_structure_is_zeroed(): void
    {
        $this->structure();
        $result = $this->stale($this->tournament(), 4, 75);

        $this->artisan('results:reprice --force')->assertSuccessful();

        $this->assertSame(0, $result->fresh()->points, 'A structure paying three pays nothing for 4th.');
    }

    public function test_a_correct_finish_is_left_alone(): void
    {
        $this->structure();
        $result = $this->score($this->tournament(), $this->player(), 1, 100);

        $this->artisan('results:reprice --force')->assertSuccessful();

        $this->assertSame(100, $result->fresh()->points);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->structure();
        $result = $this->stale($this->tournament(), 2, 100);

        $this->artisan('results:reprice --dry-run --force')->assertSuccessful();

        $this->assertSame(100, $result->fresh()->points, 'A dry run changed the database.');
    }

    public function test_published_tournaments_are_left_alone_by_default(): void
    {
        // Their players have been told what they scored. Correcting one moves a
        // season table against a number somebody was sent, so it takes a flag.
        $this->structure();
        $tournament = $this->tournament();
        $result = $this->stale($tournament, 2, 100);
        $tournament->forceFill(['published_at' => now()])->save();

        $this->artisan('results:reprice --force')->assertSuccessful();

        $this->assertSame(100, $result->fresh()->points, 'A published finish was repriced without --published.');
    }

    public function test_published_tournaments_are_repriced_when_asked_for(): void
    {
        $this->structure();
        $tournament = $this->tournament();
        $result = $this->stale($tournament, 2, 100);
        $tournament->forceFill(['published_at' => now()])->save();

        $this->artisan('results:reprice --published --force')->assertSuccessful();

        $this->assertSame(85, $result->fresh()->points);
    }

    public function test_one_tournament_can_be_repaired_alone(): void
    {
        $this->structure();
        $mine = $this->tournament('Mine');
        $theirs = $this->tournament('Theirs');

        $repaired = $this->stale($mine, 2, 100);
        $untouched = $this->stale($theirs, 2, 100);

        $this->artisan('results:reprice --tournament='.$mine->id.' --force')->assertSuccessful();

        $this->assertSame(85, $repaired->fresh()->points);
        $this->assertSame(100, $untouched->fresh()->points, '--tournament repriced a tournament it was not given.');
    }

    public function test_it_refuses_to_run_without_a_points_structure(): void
    {
        // Every place would pay nothing, so this would silently zero the whole
        // league's history -- the one input that turns a repair into a wipe.
        $result = $this->stale($this->tournament(), 2, 100);

        $this->artisan('results:reprice --force')->assertFailed();

        $this->assertSame(100, $result->fresh()->points, 'An empty structure wiped the points.');
    }

    public function test_nothing_to_do_says_so_and_succeeds(): void
    {
        $this->structure();
        $this->score($this->tournament(), $this->player(), 1, 100);

        $this->artisan('results:reprice --force')
            ->expectsOutputToContain('every finish in scope holds what its place pays')
            ->assertSuccessful();
    }
}
