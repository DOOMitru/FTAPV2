<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * A published tournament does not change.
 *
 * Six paths could otherwise move a field whose players have already been told
 * where they finished. Each is tested in a PAIR -- allowed on an open
 * tournament, refused on a published one -- because a guard that refused
 * unconditionally would pass half of every one of these on its own.
 *
 * THREE of the six were already being refused before this rule existed, and
 * that is worth knowing before somebody deletes them as dead code. A published
 * tournament necessarily has a result for every registrant, so unregister and
 * registrant-removal were already turned away by hasRecordedResults(), and
 * eliminate was already turned away by "every registered player already has a
 * result". What publishing adds to those three is the REASON an administrator
 * reads -- so those three assert the message rather than the refusal.
 */
class PublishedTournamentLockTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    /**
     * A finished tournament and the three players in it, ready to publish.
     *
     * @return array{0: PokerTournament, 1: \Illuminate\Support\Collection<int, User>}
     */
    private function finished(string $name = 'Autumn Showdown'): array
    {
        $tournament = $this->tournament($name);
        $players = User::factory()->count(3)->create(['approval_status' => 'approved']);

        foreach ($players as $player) {
            $this->enter($tournament, $player);
        }

        foreach ([[0, 1, 100], [1, 2, 60], [2, 3, 40]] as [$i, $place, $points]) {
            $this->score($tournament, $players[$i], $place, $points);
        }

        return [$tournament->fresh(), $players];
    }

    /** A finished tournament with its results published. */
    private function published(string $name = 'Autumn Showdown'): array
    {
        [$tournament, $players] = $this->finished($name);

        $this->actingAs($this->admin())->post(route('poker.tournaments.publish', $tournament));

        return [$tournament->fresh(), $players];
    }

    public function test_registering_is_refused_after_publishing(): void
    {
        [$open] = $this->finished('Open Night');
        $newcomer = User::factory()->create(['approval_status' => 'approved']);

        // Allowed while open. Registering into a scored tournament is
        // deliberately still permitted -- the shift hook moves the finishes.
        $this->actingAs($newcomer)->post(route('tournaments.register', $open))
            ->assertSessionHas('status');

        [$closed] = $this->published('Closed Night');

        $this->actingAs($newcomer)->post(route('tournaments.register', $closed))
            ->assertSessionHas('error');

        $this->assertStringContainsString('published', session('error'));
        $this->assertSame(3, $closed->registrants()->count());
    }

    public function test_unregistering_is_refused_after_publishing_and_says_why(): void
    {
        // Already refused by hasRecordedResults(). What publishing adds is the
        // reason, so the message is what this asserts.
        [$tournament, $players] = $this->published();

        $this->actingAs($players[0])->delete(route('tournaments.unregister', $tournament));

        $this->assertStringContainsString('published', session('error'));
        $this->assertSame(3, $tournament->registrants()->count());
    }

    public function test_eliminating_is_refused_after_publishing_and_says_why(): void
    {
        // The third already-refused path: a published tournament has a result
        // for everyone, so eliminate would fail on "every registered player
        // already has a result" regardless. The message is the difference.
        PointsStructure::create(['place' => 1, 'points' => 100]);

        // Allowed while open: two entered, nobody out yet.
        $open = $this->tournament('Open Night');
        $players = User::factory()->count(2)->create();
        foreach ($players as $player) {
            $this->enter($open, $player);
        }

        $admin = $this->admin();

        $this->actingAs($admin)->post(route('poker.tournaments.eliminate', $open), [
            'user_id' => $players[0]->id,
        ])->assertSessionHas('status');

        [$closed, $closedPlayers] = $this->published('Closed Night');

        $this->actingAs($admin)->post(route('poker.tournaments.eliminate', $closed), [
            'user_id' => $closedPlayers[0]->id,
        ])->assertSessionHas('error');

        $this->assertStringContainsString('published', session('error'));
    }

    public function test_removing_a_registrant_is_refused_after_publishing_and_says_why(): void
    {
        // The second already-refused path.
        [$tournament, $players] = $this->published();

        $victim = PokerTournamentRegistrant::where('tournament_id', $tournament->id)
            ->where('user_id', $players[0]->id)->firstOrFail();

        $this->actingAs($this->admin())->delete(route('poker.registrants.destroy', $victim));

        $this->assertStringContainsString('published', session('error'));
        $this->assertSame(3, $tournament->registrants()->count());
    }

    public function test_creating_a_result_is_refused_after_publishing(): void
    {
        $structure = PointsStructure::create(['place' => 9, 'points' => 5]);
        $admin = $this->admin();

        // Allowed while open.
        [$open] = $this->finished('Open Night');
        $this->actingAs($admin)->post(route('poker.results.store'), [
            'tournament_id' => $open->id,
            'points_structure_id' => $structure->id,
            'user_id' => User::factory()->create()->id,
            'player_name' => 'Late Addition',
        ])->assertSessionHasNoErrors();
        $this->assertSame(4, $open->results()->count());

        [$closed] = $this->published('Closed Night');

        $this->actingAs($admin)->post(route('poker.results.store'), [
            'tournament_id' => $closed->id,
            'points_structure_id' => $structure->id,
            'user_id' => User::factory()->create()->id,
            'player_name' => 'Later Still',
        ])->assertSessionHas('error');

        $this->assertSame(3, $closed->results()->count());
    }

    public function test_updating_a_result_is_refused_after_publishing(): void
    {
        $structure = PointsStructure::create(['place' => 1, 'points' => 100]);
        $admin = $this->admin();

        [$open, $openPlayers] = $this->finished('Open Night');
        $openResult = $open->results()->where('user_id', $openPlayers[0]->id)->firstOrFail();

        $this->actingAs($admin)->put(route('poker.results.update', $openResult), [
            'tournament_id' => $open->id,
            'points_structure_id' => $structure->id,
            'user_id' => $openPlayers[0]->id,
            'player_name' => 'Renamed While Open',
        ])->assertSessionHas('status');

        $this->assertSame('Renamed While Open', $openResult->fresh()->player_name);

        [$closed, $closedPlayers] = $this->published('Closed Night');
        $closedResult = $closed->results()->where('user_id', $closedPlayers[0]->id)->firstOrFail();

        $this->actingAs($admin)->put(route('poker.results.update', $closedResult), [
            'tournament_id' => $closed->id,
            'points_structure_id' => $structure->id,
            'user_id' => $closedPlayers[0]->id,
            'player_name' => 'Renamed While Closed',
        ])->assertSessionHas('error');

        $this->assertSame($closedPlayers[0]->first_name.' '.$closedPlayers[0]->last_name,
            $closedResult->fresh()->player_name);
    }

    public function test_deleting_a_result_is_refused_after_publishing(): void
    {
        $admin = $this->admin();

        [$open, $openPlayers] = $this->finished('Open Night');
        $openResult = $open->results()->where('user_id', $openPlayers[0]->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('poker.results.destroy', $openResult))
            ->assertSessionHas('status');
        $this->assertSame(2, $open->results()->count());

        [$closed, $closedPlayers] = $this->published('Closed Night');
        $closedResult = $closed->results()->where('user_id', $closedPlayers[0]->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('poker.results.destroy', $closedResult))
            ->assertSessionHas('error');

        $this->assertSame(3, $closed->results()->count());
    }

    public function test_unpublishing_opens_every_path_again(): void
    {
        // The half that proves the lock is a gate rather than a wall.
        [$tournament, $players] = $this->published();
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('poker.tournaments.unpublish', $tournament));

        $result = $tournament->results()->where('user_id', $players[0]->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('poker.results.destroy', $result))
            ->assertSessionHas('status');

        $this->assertSame(2, $tournament->results()->count());
    }

    public function test_publishing_one_tournament_does_not_lock_another(): void
    {
        [$published] = $this->published('Closed Night');
        [$open] = $this->finished('Open Night');

        $newcomer = User::factory()->create(['approval_status' => 'approved']);

        $this->actingAs($newcomer)->post(route('tournaments.register', $open))
            ->assertSessionHas('status');

        $this->assertSame(4, $open->registrants()->count());
        $this->assertTrue($published->fresh()->isPublished());
    }
}
