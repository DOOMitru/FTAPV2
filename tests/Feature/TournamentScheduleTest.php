<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_closes_at_scheduled_at_even_though_play_has_not_started()
    {
        $user = User::factory()->create(['is_admin' => false]);

        // Registration closed an hour ago; cards go in the air in an hour.
        $tournament = $this->makeTournament(
            scheduledAt: now()->subHour(),
            startTime: now()->addHour(),
        );

        $response = $this->actingAs($user)->post(route('tournaments.register', $tournament));

        $response->assertSessionHas('error', 'Registration has closed for this tournament.');
        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_dashboard_lists_a_tournament_whose_registration_has_closed_but_has_not_started()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $tournament = $this->makeTournament(
            scheduledAt: now()->subHour(),
            startTime: now()->addHour(),
        );

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('upcomingTournaments', function ($tournaments) use ($tournament) {
            return $tournaments->contains('id', $tournament->id);
        });
    }

    public function test_dashboard_excludes_a_tournament_that_has_already_started()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $tournament = $this->makeTournament(
            scheduledAt: now()->subHours(3),
            startTime: now()->subHours(2),
        );

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertViewHas('upcomingTournaments', function ($tournaments) use ($tournament) {
            return ! $tournaments->contains('id', $tournament->id);
        });
    }

    public function test_start_time_cannot_precede_registration_close()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('poker.tournaments.store'), [
            'name' => 'Weekly Freezeout',
            'scheduled_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'start_time' => now()->addDays(6)->format('Y-m-d H:i:s'),
            'venue_id' => $venue->id,
        ]);

        $response->assertSessionHasErrors('start_time');
        $this->assertEquals(0, PokerTournament::count());
    }

    private function makeTournament(\DateTimeInterface $scheduledAt, \DateTimeInterface $startTime): PokerTournament
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
            'scheduled_at' => $scheduledAt,
            'start_time' => $startTime,
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);
    }
}
