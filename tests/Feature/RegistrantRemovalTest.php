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
 * Who may be taken out of a field, and when.
 *
 * A place is a position in a field, not a label on a player: tenth of ten. That
 * is still the whole rule, but it was being applied to the wrong thing. Any
 * finish anywhere in a tournament used to lock EVERY entry in it, so the first
 * elimination froze the nine people still playing -- one of whom might have
 * been entered by mistake, and now could not be taken out.
 *
 * What actually cannot be removed is somebody with a finish of their own.
 * Delete their position and every other place describes a tournament that never
 * happened. A player still in has no position yet, so they can go, and the
 * field shrinks cleanly: the shrink hook moves the recorded finishes UP a place
 * and reprices them, the mirror of what a late entry does.
 *
 * Publishing closes it for good, for everyone: at that point the players have
 * been told where they came.
 *
 * A player withdrawing THEMSELVES is unchanged and stricter -- any finish in
 * the tournament stops it. That is deliberate: an administrator taking somebody
 * out is looking at the room, and a player tapping a button on the way home is
 * not.
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

        $html = $this->withoutEmphasis(
            $this->actingAs($this->admin())->get(route('tournaments.show', $tournament))
                ->assertOk()->getContent()
        );

        $this->assertStringContainsString('title="Remove from tournament"', $html);
        $this->assertStringContainsString('Remove Wanda Reeve from '.$tournament->name.'?', $html);
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

    public function test_the_control_leaves_only_the_row_of_the_player_who_finished(): void
    {
        // The inversion. One elimination used to take the control off every
        // row; it now takes it off exactly one.
        $tournament = $this->tournament(startsIn: '-1 hour');

        $out = User::factory()->create(['first_name' => 'Ousted', 'last_name' => 'Player']);
        $in = User::factory()->create(['first_name' => 'Still', 'last_name' => 'Playing']);

        foreach ([$out, $in] as $player) {
            $this->register($tournament, $player);
        }

        $this->recordAFinish($tournament, $out, 2);

        $html = $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament))->assertOk()->getContent();

        // Asserted on the destroy URLs, which name a registrant each, rather
        // than on the label -- x-action renders it more than once per control,
        // so counting the words counts the wrong thing.
        $outRow = $tournament->registrants()->where('user_id', $out->id)->firstOrFail();
        $inRow = $tournament->registrants()->where('user_id', $in->id)->firstOrFail();

        $this->assertStringContainsString(route('poker.registrants.destroy', $inRow), $html);
        $this->assertStringNotContainsString(route('poker.registrants.destroy', $outRow), $html);
    }

    public function test_publishing_takes_the_control_off_every_row(): void
    {
        $tournament = $this->tournament(startsIn: '-1 hour');
        $this->register($tournament, User::factory()->create());
        $tournament->forceFill(['published_at' => now()])->save();

        $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()
            ->assertDontSee('Remove from tournament');
    }

    public function test_the_page_says_the_field_is_locked_once_published(): void
    {
        // A missing control reads as a bug rather than as a state, which is why
        // the note exists at all. It used to fire on the first result; the
        // field is not locked then any more, so it fires on publishing.
        $tournament = $this->tournament(startsIn: '-1 hour');
        $player = User::factory()->create();
        $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player, 1);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('field locked');

        $tournament->forceFill(['published_at' => now()])->save();

        $this->actingAs($admin)->get(route('tournaments.show', $tournament->fresh()))->assertOk()
            ->assertSee('Results published · field locked', false);
    }

    public function test_the_explanation_is_not_shown_to_a_player(): void
    {
        // They were never offered the control, so there is nothing missing to
        // account for -- it would only read as the site telling them off.
        $tournament = $this->tournament(startsIn: '-1 hour');
        $player = User::factory()->create(['approval_status' => 'approved']);
        $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player, 1);

        $tournament->forceFill(['published_at' => now()])->save();

        $this->actingAs($player)->get(route('tournaments.show', $tournament->fresh()))->assertOk()
            ->assertDontSee('field locked');
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
            ->assertSessionHas('status', fn ($message) => filled($message));

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
            $this->withoutEmphasis(session('status'))
        );
    }

    public function test_an_admin_cannot_remove_a_player_who_has_finished(): void
    {
        $tournament = $this->tournament(startsIn: '-1 hour');
        $player = User::factory()->create();
        $registrant = $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player, 1);

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $registrant))
            ->assertSessionHas('error');

        $this->assertSame(1, $tournament->registrants()->count());
    }

    public function test_an_admin_can_remove_a_player_still_in_a_scored_tournament(): void
    {
        // The change. One elimination used to lock everybody; it now locks the
        // player it belongs to and nobody else.
        $tournament = $this->tournament(startsIn: '-1 hour');

        $out = User::factory()->create();
        $in = User::factory()->create();

        foreach ([$out, $in] as $player) {
            $this->register($tournament, $player);
        }

        $this->recordAFinish($tournament, $out, 2);

        $stillIn = $tournament->registrants()->where('user_id', $in->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $stillIn))
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertSame(1, $tournament->registrants()->count());
    }

    public function test_nobody_can_be_removed_once_results_are_published(): void
    {
        $tournament = $this->tournament(startsIn: '-1 hour');
        $registrant = $this->register($tournament, User::factory()->create());
        $tournament->forceFill(['published_at' => now()])->save();

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $registrant))
            ->assertSessionHas('error');

        $this->assertSame(1, $tournament->registrants()->count());
    }

    public function test_the_refusal_names_the_player_and_says_what_to_do(): void
    {
        $tournament = $this->tournament(startsIn: '-1 hour');
        $player = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Okonkwo']);
        $registrant = $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player, 1);

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $registrant))
            ->assertSessionHas('error', fn (string $error) => str_contains($error, 'Nadia Okonkwo')
                && str_contains($error, 'eliminated')
                && str_contains($error, 'position in the field'));
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
        // registration open and eliminating needed it closed -- but that was
        // two other guards happening to agree rather than this rule being
        // enforced, and they did not agree: the admin results form recorded a
        // result with no requirement that registration be closed.
        //
        // That form is gone, and the reachability argument survives it:
        // Eliminate dropped its own timing gate when the shift hook took over
        // moving finishes, so a player can be eliminated while registration is
        // still open -- exactly the state set up below. The guard is
        // load-bearing rather than belt.
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
            ->assertSessionHas('status', fn ($message) => filled($message));

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
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertSame(3, $tournament->registrants()->count());
        $this->assertSame(3, PokerTournamentResult::where('user_id', $players[0]->id)->value('place'));
    }
}
