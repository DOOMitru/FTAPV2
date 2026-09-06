<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PokerTournamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_creation_defaults_to_current_season()
    {
        $user = User::factory()->create(['is_admin' => true]);
        $venue = Venue::create(['name' => 'Venue 1', 'address' => '123 Test St']);
        $season = PokerSeason::create([
            'name' => 'Current Season',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);

        $response = $this->actingAs($user)->post(route('poker.tournaments.store'), [
            'name' => 'Tournament 1',
            'start_time' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'venue_id' => $venue->id,
            // 'season_id' no longer needed in request
        ]);

        $response->assertRedirect(route('poker.tournaments.index'));
        $tournament = PokerTournament::first();
        $this->assertEquals($season->id, $tournament->season_id);
    }

    public function test_tournament_creation_fails_without_current_season()
    {
        $user = User::factory()->create(['is_admin' => true]);
        $venue = Venue::create(['name' => 'Venue 1', 'address' => '123 Test St']);
        
        // No current season created

        $response = $this->actingAs($user)->post(route('poker.tournaments.store'), [
            'name' => 'Tournament 1',
            'start_time' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'venue_id' => $venue->id,
        ]);

        $response->assertSessionHas('error', 'No current active season found. Please create or set an active season first.');
        $this->assertEquals(0, PokerTournament::count());
    }
}
