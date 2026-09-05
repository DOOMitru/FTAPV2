<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Knocking a registered player out of a tournament.
 *
 * The place is not chosen by the administrator: it falls out of how many
 * players are still in. Poker pays from the bottom up -- the first player out
 * of a field of ten finishes tenth, and the last one standing takes first -- so
 * the place on offer is always the number of registrants without a result yet.
 *
 * That arithmetic is the whole feature, and it is off by one in either
 * direction if anyone reaches for the obvious "count the results and add one".
 */
class EliminatePlayerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** A tournament whose registration has closed, so play can be scored. */
    private function tournament(string $closesIn = '-1 hour'): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 40',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Knockout Cup',
            'scheduled_at' => now()->modify($closesIn),
            'start_time' => now()->modify($closesIn),
            'venue_id' => Venue::create(['name' => 'Knockout Hall', 'address' => '9 Rail Street'])->id,
            'season_id' => $season->id,
        ]);
    }

    /** @return array<int, User> */
    private function field(PokerTournament $tournament, int $size): array
    {
        $players = [];

        for ($i = 1; $i <= $size; $i++) {
            $user = User::factory()->create(['first_name' => 'Player', 'last_name' => (string) $i]);

            PokerTournamentRegistrant::create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'player_name' => 'Player '.$i,
                'registered_at' => now(),
            ]);

            $players[] = $user;
        }

        return $players;
    }

    private function eliminate(PokerTournament $tournament, User $player, ?User $actor = null)
    {
        return $this->actingAs($actor ?? $this->admin())
            ->post(route('poker.tournaments.eliminate', $tournament), ['user_id' => $player->id]);
    }

    public function test_the_first_player_out_of_a_field_finishes_last(): void
    {
        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        $this->eliminate($tournament, $players[0]);

        $result = PokerTournamentResult::where('user_id', $players[0]->id)->firstOrFail();
        $this->assertSame(5, $result->place, 'First out of five finishes fifth.');
    }

    public function test_places_count_down_as_players_go_out(): void
    {
        // The arithmetic, end to end. Five registrants eliminated in order take
        // 5th, 4th, 3rd, 2nd and finally 1st -- the last one standing wins.
        $tournament = $this->tournament();
        $players = $this->field($tournament, 5);

        foreach ($players as $player) {
            $this->eliminate($tournament, $player);
        }

        $places = PokerTournamentResult::where('tournament_id', $tournament->id)
            ->orderBy('created_at')->orderBy('id')->pluck('place')->all();

        $this->assertSame([5, 4, 3, 2, 1], $places);
    }

    public function test_the_result_carries_the_points_for_that_place(): void
    {
        PointsStructure::create(['place' => 3, 'points' => 75]);

        $tournament = $this->tournament();
        $players = $this->field($tournament, 3);

        $this->eliminate($tournament, $players[0]);

        $this->assertSame(75, PokerTournamentResult::where('user_id', $players[0]->id)->value('points'));
    }

    public function test_a_place_the_structure_does_not_pay_scores_nothing(): void
    {
        // A structure paying the top two of a field of four is not a fault:
        // third and fourth simply score nothing.
        PointsStructure::create(['place' => 1, 'points' => 100]);
        PointsStructure::create(['place' => 2, 'points' => 50]);

        $tournament = $this->tournament();
        $players = $this->field($tournament, 4);

        $this->eliminate($tournament, $players[0]);

        $this->assertSame(0, PokerTournamentResult::where('user_id', $players[0]->id)->value('points'));
    }

    public function test_nobody_can_be_eliminated_while_registration_is_open(): void
    {
        // A late entry would change how many places there are to hand out, and
        // every place already awarded would be wrong.
        $tournament = $this->tournament(closesIn: '+2 days');
        $players = $this->field($tournament, 3);

        $this->eliminate($tournament, $players[0])->assertSessionHas('error');

        $this->assertSame(0, PokerTournamentResult::where('tournament_id', $tournament->id)->count());
    }

    public function test_a_player_cannot_be_eliminated_twice(): void
    {
        $tournament = $this->tournament();
        $players = $this->field($tournament, 3);

        $this->eliminate($tournament, $players[0]);
        $this->eliminate($tournament, $players[0])->assertSessionHas('error');

        $this->assertSame(1, PokerTournamentResult::where('tournament_id', $tournament->id)->count());
    }

    public function test_someone_who_never_registered_cannot_be_eliminated(): void
    {
        $tournament = $this->tournament();
        $this->field($tournament, 3);
        $stranger = User::factory()->create();

        $this->eliminate($tournament, $stranger)->assertSessionHas('error');

        $this->assertSame(0, PokerTournamentResult::where('tournament_id', $tournament->id)->count());
    }

    public function test_a_player_cannot_eliminate_anyone(): void
    {
        $tournament = $this->tournament();
        $players = $this->field($tournament, 3);

        $this->eliminate($tournament, $players[0], actor: User::factory()->create(['is_admin' => false]))
            ->assertForbidden();

        $this->assertSame(0, PokerTournamentResult::where('tournament_id', $tournament->id)->count());
    }

    public function test_the_button_appears_only_for_an_admin_once_registration_closes(): void
    {
        PointsStructure::create(['place' => 3, 'points' => 75]);

        $tournament = $this->tournament();
        $this->field($tournament, 3);

        $admin = $this->admin();

        // The confirmation names the place and the points, because an
        // administrator does not choose either.
        $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSee('Eliminate')
            // Three registrants, none out yet: the place on offer is 3rd, not
            // 4th. Written as 4th first, and this assertion caught it.
            ->assertSee('They finish in 3rd place and are awarded 75 points', false);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('poker.tournaments.eliminate')
            ->assertDontSee('>Eliminate<', false);
    }

    public function test_the_button_is_absent_while_registration_is_open(): void
    {
        $tournament = $this->tournament(closesIn: '+2 days');
        $this->field($tournament, 3);

        $this->actingAs($this->admin())->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('>Eliminate<', false);
    }

    public function test_an_eliminated_row_shows_its_finish_instead_of_the_button(): void
    {
        PointsStructure::create(['place' => 3, 'points' => 75]);

        $tournament = $this->tournament();
        $players = $this->field($tournament, 3);
        $this->eliminate($tournament, $players[0]);

        $this->actingAs($this->admin())->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSee('3rd')
            ->assertSee('75 pts');
    }

    public function test_a_late_registration_pushes_every_recorded_finish_down(): void
    {
        // The case exactly as described: ten registered, one out in tenth, an
        // eleventh joins -- and that finish is now eleventh, because a place
        // describes a position in a field and the field changed.
        $tournament = $this->tournament();
        $players = $this->field($tournament, 10);

        $this->eliminate($tournament, $players[0]);
        $this->assertSame(10, PokerTournamentResult::where('user_id', $players[0]->id)->value('place'));

        $this->field($tournament, 1);

        $this->assertSame(11, PokerTournamentResult::where('user_id', $players[0]->id)->value('place'));
    }

    public function test_every_recorded_finish_moves_together(): void
    {
        // Three already out of ten -- tenth, ninth, eighth. One more joins and
        // all three shift, keeping their order and the gaps between them.
        $tournament = $this->tournament();
        $players = $this->field($tournament, 10);

        foreach (array_slice($players, 0, 3) as $player) {
            $this->eliminate($tournament, $player);
        }

        $this->assertSame([10, 9, 8], $this->placesInEliminationOrder($tournament));

        $this->field($tournament, 1);

        $this->assertSame([11, 10, 9], $this->placesInEliminationOrder($tournament));
    }

    public function test_several_late_registrations_shift_by_that_many(): void
    {
        // "any number of players": three more join, so a tenth-place finish
        // becomes thirteenth.
        $tournament = $this->tournament();
        $players = $this->field($tournament, 10);

        $this->eliminate($tournament, $players[0]);
        $this->field($tournament, 3);

        $this->assertSame(13, PokerTournamentResult::where('user_id', $players[0]->id)->value('place'));
    }

    public function test_the_countdown_carries_on_correctly_after_a_late_entry(): void
    {
        // The half that a shift alone could get wrong. Ten out of ten becomes
        // eleven of eleven, so the NEXT player out must take tenth -- not
        // eleventh again, and not ninth.
        $tournament = $this->tournament();
        $players = $this->field($tournament, 10);

        $this->eliminate($tournament, $players[0]);
        [$latecomer] = $this->field($tournament, 1);

        $this->eliminate($tournament, $players[1]);

        $this->assertSame(11, PokerTournamentResult::where('user_id', $players[0]->id)->value('place'));
        $this->assertSame(10, PokerTournamentResult::where('user_id', $players[1]->id)->value('place'));
        $this->assertNotNull($latecomer->id);
    }

    public function test_registering_into_a_tournament_with_no_results_shifts_nothing(): void
    {
        // The ordinary case, and the one a blanket increment could damage.
        $tournament = $this->tournament();
        $this->field($tournament, 4);

        $this->assertSame(0, PokerTournamentResult::where('tournament_id', $tournament->id)->count());
    }

    public function test_a_registration_does_not_disturb_another_tournament(): void
    {
        $scored = $this->tournament();
        $players = $this->field($scored, 3);
        $this->eliminate($scored, $players[0]);

        $other = PokerTournament::create([
            'name' => 'Unrelated Cup',
            'scheduled_at' => now()->subHour(),
            'start_time' => now()->subHour(),
            'venue_id' => $scored->venue_id,
            'season_id' => $scored->season_id,
        ]);

        $this->field($other, 5);

        $this->assertSame(3, PokerTournamentResult::where('tournament_id', $scored->id)->value('place'));
    }

    /** @return array<int, int> Places, oldest elimination first. */
    private function placesInEliminationOrder(PokerTournament $tournament): array
    {
        return PokerTournamentResult::where('tournament_id', $tournament->id)
            ->orderBy('created_at')->orderBy('id')->pluck('place')->all();
    }

    public function test_the_shift_happens_through_the_registration_route(): void
    {
        // The rule lives on the model because there are two ways to register.
        // This is the one an administrator actually uses -- register() skips
        // the closed-registration check for admins, which is precisely how a
        // late entry arrives after play has started.
        $tournament = $this->tournament();
        $players = $this->field($tournament, 3);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('poker.tournaments.eliminate', $tournament), [
            'user_id' => $players[0]->id,
        ]);

        $this->assertSame(3, PokerTournamentResult::where('user_id', $players[0]->id)->value('place'));

        $latecomer = User::factory()->create(['first_name' => 'Late', 'last_name' => 'Arrival']);

        $this->actingAs($admin)
            ->post(route('tournaments.register', $tournament), ['user_id' => $latecomer->id])
            ->assertSessionHas('status');

        $this->assertSame(
            4,
            PokerTournamentResult::where('user_id', $players[0]->id)->value('place'),
            'Registering through the route must shift recorded finishes too.'
        );
    }
}
