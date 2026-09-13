<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * Who may change the size of a field, and until when.
 *
 * Three windows, and they are deliberately different lengths:
 *
 *   - a PLAYER enters until the tournament starts. Once the cards are in the
 *     air the field is whatever is sitting at the tables, and somebody adding
 *     themselves from a phone changes how many places there are to hand out for
 *     a game already under way.
 *   - an ADMINISTRATOR enters somebody until the results are published. They
 *     are in the room; a late arrival at the table is a real thing, and the
 *     shift hook makes it arithmetically safe.
 *   - an ADMINISTRATOR removes somebody until the results are published, so
 *     long as that player has no finish of their own.
 *
 * The arithmetic is the part worth testing hardest. A place is a position in a
 * field, so changing the size of the field changes every place in it, and the
 * points follow the place.
 */
class RegistrationWindowTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private function payTen(): void
    {
        foreach ([1 => 100, 2 => 85, 3 => 75, 4 => 65, 5 => 55,
                  6 => 47, 7 => 40, 8 => 34, 9 => 29, 10 => 24] as $place => $points) {
            PointsStructure::create(['place' => $place, 'points' => $points]);
        }
    }

    private function player(string $first = 'Wanda', string $last = 'Reeve'): User
    {
        return User::factory()->create([
            'first_name' => $first, 'last_name' => $last, 'approval_status' => 'approved',
        ]);
    }

    /** @return array<int, array{0: int, 1: int}> place and points, in elimination order. */
    private function scores(\App\Models\PokerTournament $tournament): array
    {
        return PokerTournamentResult::where('tournament_id', $tournament->id)
            ->orderBy('created_at')->orderBy('id')->get(['place', 'points'])
            ->map(fn ($r) => [$r->place, $r->points])->all();
    }

    // ---- the player's window ------------------------------------------------

    public function test_a_player_can_enter_before_the_start(): void
    {
        $tournament = $this->tournament();
        $tournament->forceFill(['start_time' => now()->addHour()])->save();

        $this->actingAs($this->player())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('status');

        $this->assertSame(1, $tournament->registrants()->count());
    }

    public function test_a_player_cannot_enter_once_it_has_started(): void
    {
        $tournament = $this->tournament();
        $tournament->forceFill(['start_time' => now()->subMinute()])->save();

        $this->actingAs($this->player())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('error');

        $this->assertSame(0, $tournament->registrants()->count());
    }

    public function test_the_refusal_tells_the_player_what_to_do(): void
    {
        // "No" with no next step turns support into guesswork, and the next
        // step here is a real one: an administrator can still enter them.
        $tournament = $this->tournament('Wednesday Night');
        $tournament->forceFill(['start_time' => now()->subMinute()])->save();

        $this->actingAs($this->player())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'Wednesday Night')
                && str_contains($e, 'already started')
                && str_contains($e, 'administrator'));
    }

    public function test_the_card_stops_offering_a_player_the_button(): void
    {
        // The controller's rule, restated in the view. A button the controller
        // refuses is a click that cannot work.
        $tournament = $this->tournament();
        $player = $this->player();

        $tournament->forceFill(['start_time' => now()->addHour()])->save();
        $this->actingAs($player)->get(route('tournaments.show', $tournament))
            ->assertOk()->assertSee('>Register<', false);

        $tournament->forceFill(['start_time' => now()->subMinute()])->save();
        $this->actingAs($player)->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()->assertDontSee('>Register<', false);
    }

    // ---- the administrator's window ----------------------------------------

    public function test_an_admin_can_enter_somebody_after_the_start(): void
    {
        $tournament = $this->tournament();
        $tournament->forceFill(['start_time' => now()->subHour()])->save();
        $player = $this->player();

        $this->actingAs($this->admin())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament), ['user_id' => $player->id])
            ->assertSessionHas('status');

        $this->assertSame(1, $tournament->registrants()->count());
    }

    public function test_an_admin_cannot_enter_anybody_once_published(): void
    {
        $tournament = $this->tournament();
        $player = $this->player();
        $tournament->forceFill(['published_at' => now()])->save();

        $this->actingAs($this->admin())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament), ['user_id' => $player->id])
            ->assertSessionHas('error');

        $this->assertSame(0, $tournament->registrants()->count());
    }

    public function test_an_admin_entering_themselves_is_not_stopped_by_the_start(): void
    {
        // Self-registration posts no user_id, so the gate keys off the role
        // rather than off the shape of the request -- an administrator who sits
        // down to play is still an administrator.
        $tournament = $this->tournament();
        $tournament->forceFill(['start_time' => now()->subHour()])->save();

        $this->actingAs($this->admin())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('status');

        $this->assertSame(1, $tournament->registrants()->count());
    }

    // ---- the arithmetic -----------------------------------------------------

    public function test_removing_a_player_moves_every_finish_up_a_place(): void
    {
        // The mirror of a late entry. Three out of eleven hold 11th, 10th and
        // 9th; take out one of the eight still playing and they hold 10th, 9th
        // and 8th, because the field is ten now.
        $this->payTen();
        $tournament = $this->tournament();

        $players = [];
        foreach (range(1, 11) as $i) {
            $players[$i] = $this->player('Player', (string) $i);
            $this->enter($tournament, $players[$i]);
        }

        foreach ([[1, 11, 0], [2, 10, 24], [3, 9, 29]] as [$i, $place, $points]) {
            $this->score($tournament, $players[$i], $place, $points);
        }

        $this->assertSame([[11, 0], [10, 24], [9, 29]], $this->scores($tournament));

        // Player 11 is still in, so removing them is allowed.
        $stillIn = $tournament->registrants()->where('user_id', $players[11]->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $stillIn))
            ->assertSessionHas('status');

        $this->assertSame([[10, 24], [9, 29], [8, 34]], $this->scores($tournament));
    }

    public function test_the_points_follow_the_place_when_the_field_shrinks(): void
    {
        // The half that a shift alone gets wrong, and the same failure the
        // late-entry path had: places moved and points did not.
        $this->payTen();
        $tournament = $this->tournament();

        $a = $this->player('Ann', 'One');
        $b = $this->player('Bob', 'Two');
        $c = $this->player('Cal', 'Three');

        foreach ([$a, $b, $c] as $p) {
            $this->enter($tournament, $p);
        }

        $this->score($tournament, $a, 3, 75);

        $stillIn = $tournament->registrants()->where('user_id', $c->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $stillIn))
            ->assertSessionHas('status');

        $result = PokerTournamentResult::where('user_id', $a->id)->firstOrFail();

        $this->assertSame(2, $result->place, 'Third of three becomes second of two.');
        $this->assertSame(85, $result->points, '2nd pays 85, not the 75 it was holding.');
    }

    public function test_removing_and_re_adding_a_player_leaves_the_places_where_they_were(): void
    {
        // Round trip. The two hooks are inverses, and a pair that does not
        // cancel is a slow corruption rather than a visible bug.
        $this->payTen();
        $tournament = $this->tournament();

        $players = [];
        foreach (range(1, 5) as $i) {
            $players[$i] = $this->player('Player', (string) $i);
            $this->enter($tournament, $players[$i]);
        }

        $this->score($tournament, $players[1], 5, 55);
        $before = $this->scores($tournament);

        $stillIn = $tournament->registrants()->where('user_id', $players[5]->id)->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('poker.registrants.destroy', $stillIn))
            ->assertSessionHas('status');

        $this->actingAs($admin)
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament), ['user_id' => $players[5]->id])
            ->assertSessionHas('status');

        $this->assertSame($before, $this->scores($tournament));
    }

    public function test_removing_a_player_from_an_unscored_field_touches_nothing(): void
    {
        // decrement() on an empty set is a no-op, but "no results yet" is the
        // common case and a hook that only behaves on the interesting path is
        // half a hook.
        $tournament = $this->tournament();
        $player = $this->player();
        $this->enter($tournament, $player);

        $registrant = $tournament->registrants()->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $registrant))
            ->assertSessionHas('status');

        $this->assertSame(0, $tournament->registrants()->count());
        $this->assertSame([], $this->scores($tournament));
    }

    public function test_a_removal_leaves_another_tournament_alone(): void
    {
        // Both statements in the hook are scoped by tournament_id. An unscoped
        // one rewrites the league.
        $this->payTen();
        $mine = $this->tournament('Mine');
        $theirs = $this->tournament('Theirs');

        $a = $this->player('Ann', 'One');
        $b = $this->player('Bob', 'Two');
        $outsider = $this->player('Zoe', 'Outside');

        foreach ([$a, $b] as $p) {
            $this->enter($mine, $p);
        }

        $this->enter($theirs, $outsider);
        $this->score($mine, $a, 2, 85);
        $this->score($theirs, $outsider, 1, 100);

        $stillIn = $mine->registrants()->where('user_id', $b->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('poker.registrants.destroy', $stillIn))
            ->assertSessionHas('status');

        $untouched = PokerTournamentResult::where('user_id', $outsider->id)->firstOrFail();
        $this->assertSame(1, $untouched->place);
        $this->assertSame(100, $untouched->points);
    }

    public function test_a_registrant_with_a_finish_does_not_shift_the_field_if_deleted(): void
    {
        // The hook guards itself rather than trusting the controller. Deleting
        // a scored registrant is refused upstream; if it ever happened,
        // shifting would be one wrong thing among several.
        $this->payTen();
        $tournament = $this->tournament();

        $a = $this->player('Ann', 'One');
        $b = $this->player('Bob', 'Two');

        foreach ([$a, $b] as $p) {
            $this->enter($tournament, $p);
        }

        $this->score($tournament, $a, 2, 85);
        $this->score($tournament, $b, 1, 100);

        PokerTournamentRegistrant::where('tournament_id', $tournament->id)
            ->where('user_id', $a->id)->firstOrFail()->delete();

        $this->assertSame([[2, 85], [1, 100]], $this->scores($tournament));
    }
}
