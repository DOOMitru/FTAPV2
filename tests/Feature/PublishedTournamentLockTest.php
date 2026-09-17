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

        // As an ADMINISTRATOR, and with a user_id. These tournaments have been
        // played, so a player registering themselves is refused by the
        // start-time rule before publishing is ever consulted -- and publishing
        // is what this test is about. An administrator's window runs to
        // publication, which is exactly the line being drawn here.
        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        // Allowed while open. Entering somebody into a scored tournament is
        // deliberately still permitted -- the shift hook moves the finishes.
        $this->actingAs($admin)->post(route('tournaments.register', $open), ['user_id' => $newcomer->id])
            ->assertSessionHas('status', fn ($message) => filled($message));

        [$closed] = $this->published('Closed Night');

        $this->actingAs($admin)->post(route('tournaments.register', $closed), ['user_id' => $newcomer->id])
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
        ])->assertSessionHas('status', fn ($message) => filled($message));

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

    public function test_unpublishing_opens_every_path_again(): void
    {
        // The half that proves the lock is a gate rather than a wall.
        [$tournament, $players] = $this->published();
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('poker.tournaments.unpublish', $tournament));

        // Proved through removing a registrant, which the lock also refuses.
        // It used to be proved by deleting a result; that route is gone with
        // the results screens, and eliminating -- the path that now records a
        // result -- cannot be replayed on a field where everyone has finished.
        $registrant = $tournament->registrants()->where('user_id', $players[0]->id)->firstOrFail();
        $tournament->results()->where('user_id', $players[0]->id)->delete();

        $this->actingAs($admin)->delete(route('poker.registrants.destroy', $registrant))
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertSame(2, $tournament->registrants()->count());
    }

    public function test_publishing_one_tournament_does_not_lock_another(): void
    {
        [$published] = $this->published('Closed Night');
        [$open] = $this->finished('Open Night');

        $newcomer = User::factory()->create(['approval_status' => 'approved']);
        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        // An administrator, for the same reason as above: these tournaments
        // have started, and this test is about publishing, not about the
        // player's window.
        $this->actingAs($admin)->post(route('tournaments.register', $open), ['user_id' => $newcomer->id])
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertSame(4, $open->registrants()->count());
        $this->assertTrue($published->fresh()->isPublished());
    }
}
