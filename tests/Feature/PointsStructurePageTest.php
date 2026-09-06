<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointsStructurePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_structure_page_shows_the_current_season()
    {
        PointsStructure::create(['place' => 1, 'points' => 100]);

        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $response = $this->get(route('rules.points-structure'));

        $response->assertStatus(200);
        $response->assertViewHas('currentSeason', function ($viewSeason) use ($season) {
            return $viewSeason !== null && $viewSeason->id === $season->id;
        });
    }

    public function test_points_structure_page_loads_when_no_season_exists()
    {
        PointsStructure::create(['place' => 1, 'points' => 100]);

        $response = $this->get(route('rules.points-structure'));

        $response->assertStatus(200);
        $this->assertNull($response->viewData('currentSeason'));
    }

    /** A season with one tournament in it, ready to be scored. */
    private function season(): PokerTournament
    {
        PointsStructure::create(['place' => 1, 'points' => 100]);

        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Wednesday Night Poker',
            'start_time' => now()->subWeek(),
            'venue_id' => Venue::create(['name' => 'Diamond Club', 'address' => '1 Card Street'])->id,
            'season_id' => $season->id,
        ]);
    }

    public function test_the_leaders_panel_is_absent_before_anyone_has_scored(): void
    {
        // The query takes the top three by summed points and, with no results
        // recorded, that is three arbitrary rows of the users table each
        // showing nought. A leaderboard of people who have not played is worse
        // than no leaderboard.
        $this->season();

        User::factory()->count(5)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('rules.points-structure'))->assertOk()
            ->assertDontSee('Current Season Leaders');
    }

    public function test_only_players_who_have_scored_are_listed(): void
    {
        $tournament = $this->season();

        $scorer = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $blank = User::factory()->create(['first_name' => 'Nobody', 'last_name' => 'Yet']);

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $scorer->id,
            'player_name' => 'Ada Lovelace',
            'place' => 1,
            'points' => 100,
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('rules.points-structure'))->assertOk();

        $response->assertSee('Current Season Leaders');
        $response->assertSee('Lovelace');
        $response->assertDontSee('Nobody');

        $response->assertViewHas('topPerformers', fn ($performers) => $performers->count() === 1);
    }

    public function test_a_finish_worth_nothing_is_not_a_place_on_the_board(): void
    {
        // Places outside the structure score zero, so a player can have a
        // result and still have nothing. Having played is not the test; having
        // scored is.
        $tournament = $this->season();

        $scorer = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $unpaid = User::factory()->create(['first_name' => 'Rodney', 'last_name' => 'Ninth']);

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id, 'user_id' => $scorer->id,
            'player_name' => 'Ada Lovelace', 'place' => 1, 'points' => 100,
        ]);

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id, 'user_id' => $unpaid->id,
            'player_name' => 'Rodney Ninth', 'place' => 9, 'points' => 0,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('rules.points-structure'))->assertOk()
            ->assertSee('Lovelace')
            ->assertDontSee('Rodney');
    }

    public function test_a_scorer_in_another_season_does_not_lead_this_one(): void
    {
        // The old season is built FIRST, on purpose: a new season is current by
        // default and unsets the others, so creating it afterwards would hand
        // it the crown and quietly test the opposite of what this says.
        $old = PokerSeason::create([
            'name' => 'Season 0',
            'start_date' => now()->subYear(),
            'end_date' => now()->subMonths(6),
        ]);

        $venue = Venue::create(['name' => 'Old Hall', 'address' => '2 Past Street']);

        $elsewhere = PokerTournament::create([
            'name' => 'Last Year',
            'start_time' => now()->subYear(),
            'venue_id' => $venue->id,
            'season_id' => $old->id,
        ]);

        PokerTournamentResult::create([
            'tournament_id' => $elsewhere->id,
            'user_id' => User::factory()->create(['first_name' => 'Lastyear', 'last_name' => 'Champion'])->id,
            'player_name' => 'Lastyear Champion', 'place' => 1, 'points' => 500,
        ]);

        $this->season();

        $this->assertTrue(PokerSeason::where('is_current', true)->where('name', 'Season 1')->exists());

        $this->actingAs(User::factory()->create())
            ->get(route('rules.points-structure'))->assertOk()
            ->assertDontSee('Current Season Leaders')
            ->assertDontSee('Lastyear');
    }
}
