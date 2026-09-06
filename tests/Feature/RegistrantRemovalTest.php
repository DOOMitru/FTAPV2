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

    private function tournament(string $startsIn = '+3 days'): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 40',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(2),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Wednesday Night Poker',
            'start_time' => now()->modify($startsIn),
            'venue_id' => Venue::create(['name' => 'Diamond Club', 'address' => '1 Card Street'])->id,
            'season_id' => $season->id,
        ]);
    }

    public function test_an_admin_is_offered_the_control_on_the_tournament_page(): void
    {
        // The page an administrator is on when they notice a wrong entry. Until
        // now the only way to remove one was the registrants index, which lists
        // every entry in every tournament in the league.
        $tournament = $this->tournament();
        $player = User::factory()->create(['first_name' => 'Wanda', 'last_name' => 'Reeve']);
        $this->register($tournament, $player);

        $this->actingAs($this->admin())->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSee('title="Remove from tournament"', false)
            ->assertSee('Remove Wanda Reeve from '.$tournament->name.'?', false);
    }

    public function test_the_control_survives_play_starting(): void
    {
        // The entry most likely to be wrong is one an admin added late, on the
        // night. Nothing about the hour removes the control -- only a recorded
        // finish does, which is the test below.
        $tournament = $this->tournament(startsIn: '-1 hour');
        $this->register($tournament, User::factory()->create());

        $this->actingAs($this->admin())->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSee('title="Remove from tournament"', false);
    }

    public function test_the_control_leaves_every_row_once_one_player_finishes(): void
    {
        // Not just the row of the player who finished. A place is a position in
        // a field, so the field is settled as a whole -- removing ANY of the
        // three now makes that recorded finish describe a field of two.
        $tournament = $this->tournament(startsIn: '-1 hour');
        $players = User::factory()->count(3)->create();

        foreach ($players as $player) {
            $this->register($tournament, $player);
        }

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSeeInOrder(
                ['title="Remove from tournament"', 'title="Remove from tournament"', 'title="Remove from tournament"'],
                false
            );

        $this->recordAFinish($tournament, $players[0], 3);

        $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('title="Remove from tournament"', false);
    }

    public function test_the_page_says_why_the_control_is_gone(): void
    {
        $tournament = $this->tournament(startsIn: '-1 hour');
        $player = User::factory()->create();
        $this->register($tournament, $player);
        $admin = $this->admin();

        // Nothing to explain while the control is there.
        $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('entries locked');

        $this->recordAFinish($tournament, $player, 1);

        $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSee('entries locked');
    }

    public function test_the_explanation_is_not_shown_to_a_player(): void
    {
        // They were never offered the control, so there is nothing missing to
        // account for -- it would only read as the site telling them off.
        $tournament = $this->tournament(startsIn: '-1 hour');
        $player = User::factory()->create(['approval_status' => 'approved']);
        $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player, 1);

        $this->actingAs($player)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('entries locked');
    }

    public function test_a_player_is_not_offered_the_control(): void
    {
        $tournament = $this->tournament();
        $player = User::factory()->create(['approval_status' => 'approved']);
        $this->register($tournament, $player);

        $this->actingAs($player)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('title="Remove from tournament"', false)
            ->assertDontSee('poker/registrants', false);
    }

    public function test_a_player_cannot_remove_anyone_even_by_posting(): void
    {
        // The control is hidden from them; the route must refuse them too.
        $tournament = $this->tournament();
        $victim = $this->register($tournament, User::factory()->create());
        $player = User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);

        $this->actingAs($player)
            ->delete(route('poker.registrants.destroy', $victim))
            ->assertForbidden();

        $this->assertSame(1, $tournament->registrants()->count());
    }

    public function test_the_card_stops_offering_a_withdrawal_once_results_exist(): void
    {
        // The hole this change closes. Registration is still OPEN, so the card's
        // old condition was satisfied and it drew Unregister -- while the
        // controller refused the click, because a result had been recorded
        // through the admin results form.
        $tournament = $this->tournament(startsIn: '+3 days');
        $player = User::factory()->create(['approval_status' => 'approved']);
        $this->register($tournament, $player);

        $this->actingAs($player)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSee('>Unregister<', false);

        $this->recordAFinish($tournament, $player, 1);

        $this->actingAs($player)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('>Unregister<', false)
            // Still registered, and still told so. The withdrawal is gone, not
            // the fact that they are in the tournament.
            ->assertSee('Registered');
    }

    public function test_the_home_and_events_cards_agree_with_the_tournament_page(): void
    {
        // One component draws all three, but each page builds its own query --
        // and hasRecordedResults() reads a withCount that only two of them add.
        // A page that forgot it would fall back to a live query and still be
        // correct, so this is about the answer, not the query.
        $tournament = $this->tournament(startsIn: '+3 days');
        $player = User::factory()->create(['approval_status' => 'approved']);
        $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player, 1);

        foreach (['home', 'events'] as $page) {
            $this->actingAs($player)->get(route($page))->assertOk()
                ->assertDontSee('>Unregister<', false, "The {$page} card still offered a withdrawal.");
        }
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
            ->from(route('tournaments.show', $tournament))
            ->delete(route('poker.registrants.destroy', $registrant))
            // Back where the admin was. This used to redirect to the
            // registrants index unconditionally, which was right while that
            // index was the only caller and became a page nobody asked for once
            // the tournament page grew the same control.
            ->assertRedirect(route('tournaments.show', $tournament))
            ->assertSessionHas('status');

        $this->assertSame(0, $tournament->registrants()->count());
    }

    public function test_removing_from_the_registrants_index_still_returns_there(): void
    {
        // The other caller, unchanged: back() from the index IS the index.
        $tournament = $this->tournament();
        $registrant = $this->register($tournament, User::factory()->create());

        $this->actingAs($this->admin())
            ->from(route('poker.registrants.index'))
            ->delete(route('poker.registrants.destroy', $registrant))
            ->assertRedirect(route('poker.registrants.index'));
    }

    public function test_the_confirmation_names_the_player_and_the_tournament(): void
    {
        // Named, because the tournament page draws a column of these and the
        // registrants index lists entries from every tournament in the league.
        // "Registrant removed successfully" told an admin nothing about which.
        $tournament = $this->tournament();
        $player = User::factory()->create(['first_name' => 'Wanda', 'last_name' => 'Reeve']);
        $registrant = $this->register($tournament, $player);

        $this->actingAs($this->admin())
            ->from(route('tournaments.show', $tournament))
            ->delete(route('poker.registrants.destroy', $registrant));

        $this->assertSame(
            'Wanda Reeve has been removed from '.$tournament->name.'.',
            session('status')
        );
    }

    public function test_an_admin_cannot_remove_a_registrant_once_a_finish_is_recorded(): void
    {
        $tournament = $this->tournament(startsIn: '-1 hour');
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
        $tournament = $this->tournament(startsIn: '-1 hour');
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
        $tournament = $this->tournament(startsIn: '-1 hour');
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
        // Believed unreachable when this was written -- withdrawing needs
        // registration open and eliminating needs it closed -- but that was two
        // other guards happening to agree rather than this rule being enforced,
        // and they do not actually agree: PokerTournamentResultController
        // records a result through the admin results form with no requirement
        // that registration be closed. So this state is reachable, exactly as
        // set up below, and the guard is load-bearing rather than belt.
        $tournament = $this->tournament(startsIn: '+3 days');
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
        $scored = $this->tournament(startsIn: '-1 hour');
        $player = User::factory()->create();
        $this->register($scored, $player);
        $this->recordAFinish($scored, $player, 1);

        $other = PokerTournament::create([
            'name' => 'Sunday Deepstack',
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
        $tournament = $this->tournament(startsIn: '-1 hour');
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
