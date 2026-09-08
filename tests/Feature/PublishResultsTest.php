<?php

namespace Tests\Feature;

use App\Models\PokerTournament;
use App\Models\User;
use App\Notifications\TournamentPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * Publishing results tells the players who scored, and closes the tournament.
 *
 * The two happen together on purpose. Sending without locking leaves every
 * message open to a late registration shifting the places underneath it;
 * locking without sending closes a tournament nobody was told about.
 */
class PublishResultsTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    /**
     * A finished tournament: three entered, three finished, the last of them
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
        [$tournament, $players] = $this->finished();

        $this->actingAs($this->admin())->post(route('poker.tournaments.publish', $tournament));

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
        $this->enter($tournament, User::factory()->create());

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

        $second = $this->tournament('Winter Open');
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
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SomethingElse',
            'notifiable_type' => User::class,
            'notifiable_id' => $players[0]->id,
            'data' => ['message' => 'unrelated'],
            'read_at' => null,
        ]);

        $this->actingAs($admin)->delete(route('poker.tournaments.unpublish', $tournament->fresh()));

        $this->assertSame(1, $players[0]->fresh()->notifications()->count());
        $this->assertSame(
            'App\\Notifications\\SomethingElse',
            $players[0]->fresh()->notifications()->first()->type
        );
    }

    public function test_the_notification_carries_the_placement_it_was_sent_for(): void
    {
        [$tournament, $players] = $this->finished();

        $this->actingAs($this->admin())->post(route('poker.tournaments.publish', $tournament));

        $data = $players[1]->notifications()->first()->data;

        $this->assertSame(2, $data['place']);
        $this->assertSame(60, $data['points']);
        $this->assertSame('Autumn Showdown', $data['tournament_name']);
        $this->assertSame(TournamentPlacement::class, $players[1]->notifications()->first()->type);
    }

    public function test_a_player_cannot_publish(): void
    {
        [$tournament] = $this->finished();

        $this->actingAs(User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']))
            ->post(route('poker.tournaments.publish', $tournament))
            ->assertForbidden();

        $this->assertFalse($tournament->fresh()->isPublished());
    }

    public function test_a_player_cannot_unpublish(): void
    {
        [$tournament] = $this->finished();
        $this->actingAs($this->admin())->post(route('poker.tournaments.publish', $tournament));

        $this->actingAs(User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']))
            ->delete(route('poker.tournaments.unpublish', $tournament->fresh()))
            ->assertForbidden();

        $this->assertTrue($tournament->fresh()->isPublished());
    }

    public function test_the_button_appears_only_when_it_can_act(): void
    {
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->enter($tournament, $player);
        $admin = $this->admin();

        // Incomplete: no button, because it would be a click that fails.
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

    public function test_edit_steps_down_while_publish_is_offered(): void
    {
        // Two primary buttons side by side is two calls to action and therefore
        // none. Only a screenshot showed this; every test passed with both red.
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->enter($tournament, $player);
        $admin = $this->admin();

        // Not yet publishable: Edit is the page's action, and stays primary.
        $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSee('href="'.route('poker.tournaments.edit', $tournament).'" class="btn btn--primary"', false);

        $this->score($tournament, $player, 1, 100);

        $this->actingAs($admin)->get(route('tournaments.show', $tournament->fresh()))->assertOk()
            ->assertSee('href="'.route('poker.tournaments.edit', $tournament).'" class="btn btn--ghost"', false);
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
