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
 * A settled field cannot change size.
 *
 * A place is a position in a field, not a label on a player: tenth of ten. Take
 * a player out of the field after finishes are recorded and every one of those
 * finishes describes a tournament that never happened -- tenth of ten, in a
 * field of nine.
 *
 * Registering someone late is the opposite case and IS handled: the shift hook
 * moves recorded places down to match. There is no way back, because removing a
 * player is ambiguous where adding one is not -- did they never play, or did
 * they play and their result should go too? So the answer is that they stay.
 */
class RegistrantRemovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    private function tournament(string $closesIn = '+3 days'): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 40',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(2),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Wednesday Night Poker',
            'scheduled_at' => now()->modify($closesIn),
            'start_time' => now()->modify($closesIn),
            'venue_id' => Venue::create(['name' => 'Diamond Club', 'address' => '1 Card Street'])->id,
            'season_id' => $season->id,
        ]);
    }

    private function register(PokerTournament $tournament, User $player): PokerTournamentRegistrant
    {
        return PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'registered_at' => now(),
        ]);
    }

    private function recordAFinish(PokerTournament $tournament, User $player, int $place): void
    {
        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'place' => $place,
            'points' => 0,
        ]);
    }

    public function test_an_admin_can_remove_a_registrant_before_anyone_finishes(): void
    {
        // The ordinary case, and the one a blanket refusal would break: a
        // mistaken entry before play is exactly what this control is for.
        $tournament = $this->tournament();
        $registrant = $this->register($tournament, User::factory()->create());

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $registrant))
            ->assertRedirect(route('poker.registrants.index'));

        $this->assertSame(0, $tournament->registrants()->count());
    }

    public function test_an_admin_cannot_remove_a_registrant_once_a_finish_is_recorded(): void
    {
        $tournament = $this->tournament(closesIn: '-1 hour');
        $players = User::factory()->count(3)->create();

        foreach ($players as $player) {
            $this->register($tournament, $player);
        }

        $this->recordAFinish($tournament, $players[0], 3);

        $victim = $tournament->registrants()->where('user_id', $players[1]->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $victim))
            ->assertSessionHas('error');

        $this->assertSame(3, $tournament->registrants()->count(), 'The field changed size.');
    }

    public function test_the_refusal_names_the_player_and_says_what_to_do(): void
    {
        $tournament = $this->tournament(closesIn: '-1 hour');
        $player = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $registrant = $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player, 1);

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $registrant));

        $this->assertStringContainsString('Ada Lovelace', session('error'));
        $this->assertStringContainsString('Delete the results first', session('error'));
    }

    public function test_the_control_is_not_drawn_once_it_cannot_act(): void
    {
        // Offering a button the controller refuses is offering a click that
        // cannot work.
        $tournament = $this->tournament(closesIn: '-1 hour');
        $player = User::factory()->create();
        $registrant = $this->register($tournament, $player);

        $admin = $this->admin();

        // On the control, not on its URL: the edit link is that same URL with
        // "/edit" on the end, so an absent delete form still "contains" it.
        $this->actingAs($admin)->get(route('poker.registrants.index'))->assertOk()
            ->assertSee('title="Delete"', false);

        $this->recordAFinish($tournament, $player, 1);

        $this->actingAs($admin)->get(route('poker.registrants.index'))->assertOk()
            ->assertSee('title="Edit"', false)
            ->assertDontSee('title="Delete"', false);
    }

    public function test_a_player_cannot_withdraw_once_results_exist_either(): void
    {
        // Unreachable today -- withdrawing needs registration open and
        // eliminating needs it closed -- but that is two other guards happening
        // to agree, not this rule being enforced. The moment either moves, this
        // is the hole.
        $tournament = $this->tournament(closesIn: '+3 days');
        $player = User::factory()->create(['approval_status' => 'approved']);
        $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player, 1);

        $this->actingAs($player)
            ->delete(route('tournaments.unregister', $tournament))
            ->assertSessionHas('error');

        $this->assertSame(1, $tournament->registrants()->count());
    }

    public function test_a_finish_in_another_tournament_does_not_lock_this_one(): void
    {
        // The guard reads the registrant's OWN tournament. Reading results
        // globally would freeze every registrant in the league the moment one
        // tournament was scored.
        $scored = $this->tournament(closesIn: '-1 hour');
        $player = User::factory()->create();
        $this->register($scored, $player);
        $this->recordAFinish($scored, $player, 1);

        $other = PokerTournament::create([
            'name' => 'Sunday Deepstack',
            'scheduled_at' => now()->addDays(5),
            'start_time' => now()->addDays(5),
            'venue_id' => $scored->venue_id,
            'season_id' => $scored->season_id,
        ]);

        $registrant = $this->register($other, User::factory()->create());

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $registrant))
            ->assertSessionHas('status');

        $this->assertSame(0, $other->registrants()->count());
    }

    public function test_a_late_entry_is_still_allowed_after_results(): void
    {
        // The opposite direction stays open, and must: it is how a player who
        // turned up late gets scored, and the shift hook keeps the recorded
        // places honest.
        $tournament = $this->tournament(closesIn: '-1 hour');
        $players = User::factory()->count(2)->create();

        foreach ($players as $player) {
            $this->register($tournament, $player);
        }

        $this->recordAFinish($tournament, $players[0], 2);

        $latecomer = User::factory()->create(['approval_status' => 'approved']);

        $this->actingAs($this->admin())
            ->post(route('tournaments.register', $tournament), ['user_id' => $latecomer->id])
            ->assertSessionHas('status');

        $this->assertSame(3, $tournament->registrants()->count());
        $this->assertSame(3, PokerTournamentResult::where('user_id', $players[0]->id)->value('place'));
    }
}
