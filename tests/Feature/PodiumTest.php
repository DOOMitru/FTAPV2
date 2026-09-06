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
 * Which podium places are settled, and when.
 *
 * Places are handed out from the bottom of the field up, so the lowest place
 * numbers on record are not the podium until the field has shrunk to meet
 * them. Taking the best three at any moment -- which is what both pages used
 * to do -- puts players on a podium while first and second are still being
 * played for.
 *
 * Third is settled once two players are left. First and second appear together
 * and only once everybody has a result.
 */
class PodiumTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 50',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Podium Cup',
            'start_time' => now()->subHour(),
            'venue_id' => Venue::create(['name' => 'Podium Hall', 'address' => '1 Medal Way'])->id,
            'season_id' => $season->id,
        ]);
    }

    /**
     * A field of $size with the bottom $out places already recorded, exactly as
     * eliminate() would have written them: first out takes last place.
     *
     * The whole field registers before anybody is knocked out, which is both
     * how a tournament runs and what this fixture needs: registering shifts
     * every recorded place down by one, so interleaving the two would leave
     * the places this helper wrote quietly rewritten. The first version did
     * interleave them, and three of the tests below failed on it.
     */
    private function played(PokerTournament $tournament, int $size, int $out): void
    {
        $users = [];

        for ($i = 1; $i <= $size; $i++) {
            $users[$i] = User::factory()->create(['first_name' => 'Player', 'last_name' => (string) $i]);

            PokerTournamentRegistrant::create([
                'tournament_id' => $tournament->id,
                'user_id' => $users[$i]->id,
                'player_name' => 'Player '.$i,
                'registered_at' => now(),
            ]);
        }

        for ($i = 1; $i <= $out; $i++) {
            PokerTournamentResult::create([
                'tournament_id' => $tournament->id,
                'user_id' => $users[$i]->id,
                'player_name' => 'Player '.$i,
                'place' => $size - $i + 1,
                'points' => 0,
            ]);
        }
    }

    private function places(PokerTournament $tournament): array
    {
        return $tournament->fresh(['registrants', 'results'])->podium()->pluck('place')->all();
    }

    public function test_nothing_shows_while_more_than_two_players_are_left(): void
    {
        // Eight of ten out, so third place has been awarded -- but three
        // players are still in and none of them has finished anything yet.
        $tournament = $this->tournament();
        $this->played($tournament, size: 10, out: 7);

        $this->assertSame([], $this->places($tournament));
    }

    public function test_third_shows_once_two_players_are_left(): void
    {
        $tournament = $this->tournament();
        $this->played($tournament, size: 10, out: 8);

        $this->assertSame([3], $this->places($tournament));
    }

    public function test_third_alone_still_shows_with_one_player_left(): void
    {
        // Second is awarded but first is not, so second waits for it.
        $tournament = $this->tournament();
        $this->played($tournament, size: 10, out: 9);

        $this->assertSame([3], $this->places($tournament));
    }

    public function test_all_three_show_once_everyone_is_out(): void
    {
        $tournament = $this->tournament();
        $this->played($tournament, size: 10, out: 10);

        $this->assertSame([1, 2, 3], $this->places($tournament));
    }

    public function test_a_field_of_three_behaves_the_same(): void
    {
        $tournament = $this->tournament();
        $this->played($tournament, size: 3, out: 1);

        $this->assertSame([3], $this->places($tournament), 'One out of three leaves two in.');
    }

    public function test_a_tournament_with_no_results_has_no_podium(): void
    {
        $tournament = $this->tournament();
        $this->played($tournament, size: 6, out: 0);

        $this->assertSame([], $this->places($tournament));
    }

    public function test_the_admin_page_hides_the_podium_until_it_is_settled(): void
    {
        $tournament = $this->tournament();
        $this->played($tournament, size: 10, out: 7);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('>Podium<', false);
    }

    public function test_the_admin_page_shows_a_lone_third_place_as_third(): void
    {
        // By index it would have been dressed as first -- gold disc, tallest
        // riser, the numeral 1 -- for the player who came third.
        $tournament = $this->tournament();
        $this->played($tournament, size: 10, out: 8);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSee('podium__place--3', false)
            ->assertDontSee('podium__place--1', false)
            ->assertDontSee('podium__place--2', false);
    }

    public function test_the_public_archive_uses_the_same_rule(): void
    {
        $settled = $this->tournament();
        $this->played($settled, size: 4, out: 4);

        $unsettled = $this->tournament();
        $unsettled->update(['name' => 'Still Playing Cup']);
        $this->played($unsettled, size: 10, out: 5);

        $html = $this->get('/events')->assertOk()->getContent();

        // The finished tournament's winner is on its card; the unfinished one
        // shows no podium at all.
        $this->assertStringContainsString('Podium Cup', $html);
        $this->assertStringContainsString('Still Playing Cup', $html);

        $unsettledCard = substr($html, strpos($html, 'Still Playing Cup'));
        $this->assertStringNotContainsString('p-podium', substr($unsettledCard, 0, 800));
    }

    public function test_a_first_place_recorded_early_is_still_not_shown(): void
    {
        // Filtering on the place VALUE alone would be enough if every result
        // came from eliminate(), which only ever awards the place the field
        // size allows. Results can also be entered by hand through the results
        // screen, and a first place typed in while nine players are still
        // sitting at the table is not a podium. The remaining-players gate is
        // what refuses it, and this is the case that earns the gate its keep.
        $tournament = $this->tournament();
        $this->played($tournament, size: 10, out: 0);

        $winner = $tournament->registrants()->first();

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $winner->user_id,
            'player_name' => $winner->player_name,
            'place' => 1,
            'points' => 100,
        ]);

        $this->assertSame([], $this->places($tournament));
    }
}
