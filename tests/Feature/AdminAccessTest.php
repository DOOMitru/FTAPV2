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
     * Every index route that is still admin-only.
     *
     * Seasons, venues and tournaments are NOT here any more: their lists are
     * readable by any signed-in player, and PlayerLeagueAccessTest covers what
     * that opened and what it did not. Their create pages stand in for them
     * here, so this provider still proves the /poker prefix refuses a player.
     */
    public static function adminRouteProvider(): array
    {
        return [
            'create a season' => ['poker.seasons.create'],
            'create a venue' => ['poker.venues.create'],
            'create a tournament' => ['poker.tournaments.create'],
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

    /**
     * Admin routes that CHANGE something.
     *
     * The GET provider above reaches create pages and indexes. A write route
     * refused at the form but not at the endpoint is the shape of gate this
     * suite exists to catch, and nothing was asking for one.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function adminWriteRouteProvider(): array
    {
        return [
            'store a season' => ['POST', 'poker.seasons.store'],
            'store a venue' => ['POST', 'poker.venues.store'],
            'store a tournament' => ['POST', 'poker.tournaments.store'],
            'store a registrant' => ['POST', 'poker.registrants.store'],
            'store venue points' => ['POST', 'poker.venue-points.store'],
            'store a points structure' => ['POST', 'poker.points-structure.store'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminWriteRouteProvider')]
    public function test_a_player_cannot_reach_an_admin_write_route(string $verb, string $routeName)
    {
        // 403 and nothing else: not a validation error, which would mean the
        // request got past the gate and was merely malformed.
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->call($verb, route($routeName))->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminWriteRouteProvider')]
    public function test_a_guest_is_redirected_from_an_admin_write_route(string $verb, string $routeName)
    {
        $this->call($verb, route($routeName))->assertRedirect(route('login'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminRouteProvider')]
    public function test_a_guest_is_redirected_from_every_admin_route(string $routeName)
    {
        // Was one route, and the route it named -- poker.seasons.index -- is no
        // longer admin-gated: the three league indexes moved out to the
        // signed-in group. So it proved that `auth` redirects, which was never
        // in question, and proved nothing about `admin` at all.
        //
        // 302 rather than 403 is the point. EnsureUserIsAdmin refuses a guest
        // on its own, but with a 403 -- somebody who could reach the page by
        // signing in should be sent to sign in.
        $this->get(route($routeName))->assertRedirect(route('login'));
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
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertDatabaseHas('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('tournaments.unregister', $tournament))
            ->assertSessionHas('status', fn ($message) => filled($message));

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
            'start_time' => now()->addDays(7)->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);
    }
}
