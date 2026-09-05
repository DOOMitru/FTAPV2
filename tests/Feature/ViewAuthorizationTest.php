<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_does_not_see_poker_seasons_link_on_dashboard()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('poker.seasons.index'), false);
    }

    public function test_admin_sees_poker_seasons_link_on_dashboard()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('poker.seasons.index'), false);
    }

    public function test_non_admin_does_not_see_tournament_edit_link_on_tournament_show()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $venue = Venue::create(['name' => 'Venue 1', 'address' => '123 Test St']);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);
        $tournament = PokerTournament::create([
            'name' => 'Tournament 1',
            'scheduled_at' => now()->addDays(7),
            'start_time' => now()->addDays(7),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $response = $this->actingAs($user)->get(route('tournaments.show', $tournament));

        $response->assertOk();
        $response->assertDontSee(route('poker.tournaments.edit', $tournament), false);
    }

    public function test_admin_sees_tournament_edit_link_on_tournament_show()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $venue = Venue::create(['name' => 'Venue 1', 'address' => '123 Test St']);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);
        $tournament = PokerTournament::create([
            'name' => 'Tournament 1',
            'scheduled_at' => now()->addDays(7),
            'start_time' => now()->addDays(7),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $response = $this->actingAs($admin)->get(route('tournaments.show', $tournament));

        $response->assertOk();
        $response->assertSee(route('poker.tournaments.edit', $tournament), false);
    }

    public function test_non_admin_does_not_see_season_edit_link_on_season_show()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);

        $response = $this->actingAs($user)->get(route('seasons.show', $season));

        $response->assertOk();
        $response->assertDontSee(route('poker.seasons.edit', $season), false);
    }

    public function test_admin_sees_season_edit_link_on_season_show()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('seasons.show', $season));

        $response->assertOk();
        $response->assertSee(route('poker.seasons.edit', $season), false);
    }

    public function test_register_control_hidden_when_registration_closed_but_not_started()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $venue = Venue::create(['name' => 'Venue 1', 'address' => '123 Test St']);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);
        $tournament = PokerTournament::create([
            'name' => 'Tournament 1',
            'scheduled_at' => now()->subDay(),
            'start_time' => now()->addDay(),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $response = $this->actingAs($user)->get(route('tournaments.show', $tournament));

        $response->assertOk();
        $response->assertDontSee(route('tournaments.register', $tournament), false);
    }

    public function test_register_control_shown_when_registration_still_open()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $venue = Venue::create(['name' => 'Venue 1', 'address' => '123 Test St']);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);
        $tournament = PokerTournament::create([
            'name' => 'Tournament 1',
            'scheduled_at' => now()->addDay(),
            'start_time' => now()->addDays(2),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $response = $this->actingAs($user)->get(route('tournaments.show', $tournament));

        $response->assertOk();
        $response->assertSee(route('tournaments.register', $tournament), false);
    }
}
