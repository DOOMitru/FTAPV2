<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every index route inside the admin-only /poker prefix.
     */
    public static function adminRouteProvider(): array
    {
        return [
            'seasons' => ['poker.seasons.index'],
            'venues' => ['poker.venues.index'],
            'tournaments' => ['poker.tournaments.index'],
            'results' => ['poker.results.index'],
            'registrants' => ['poker.registrants.index'],
            'venue points' => ['poker.venue-points.index'],
            'points structure' => ['poker.points-structure.index'],
            'users' => ['users.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminRouteProvider')]
    public function test_non_admin_is_forbidden_from_admin_routes(string $routeName)
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route($routeName))->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminRouteProvider')]
    public function test_admin_can_reach_admin_routes(string $routeName)
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route($routeName))->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login_from_admin_routes()
    {
        $this->get(route('poker.seasons.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_can_still_view_a_tournament()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($user)->get(route('tournaments.show', $tournament))->assertStatus(200);
    }

    public function test_non_admin_can_still_view_a_season()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $this->actingAs($user)->get(route('seasons.show', $season))->assertStatus(200);
    }

    public function test_non_admin_can_still_register_and_unregister()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($user)
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('tournaments.unregister', $tournament))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
        ]);
    }

    private function makeTournament(): PokerTournament
    {
        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Weekly Freezeout',
            'scheduled_at' => now()->addDays(7),
            'start_time' => now()->addDays(7)->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);
    }
}
