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

    /**
     * The store path has been guarded since Phase 0; update was not, and it
     * carries the same rule. A seeder bug produced tournaments whose
     * registration closed up to two hours after play began, which is data this
     * validation exists to prevent -- but nothing proved the rule still fired
     * on the edit form, which is where an existing tournament gets broken.
     */
    public function test_start_time_cannot_be_moved_before_registration_close_on_update()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $tournament = $this->makeTournament(
            scheduledAt: now()->addDays(6),
            startTime: now()->addDays(7),
        );

        $response = $this->actingAs($admin)->put(
            route('poker.tournaments.update', $tournament),
            [
                'name' => $tournament->name,
                // Pulled back a day, so play would begin before registration closes.
                'scheduled_at' => now()->addDays(6)->format('Y-m-d H:i:s'),
                'start_time' => now()->addDays(5)->format('Y-m-d H:i:s'),
                'venue_id' => $tournament->venue_id,
                'season_id' => $tournament->season_id,
            ]
        );

        $response->assertSessionHasErrors('start_time');

        // And the stored row is untouched.
        $this->assertTrue(
            $tournament->fresh()->start_time->greaterThanOrEqualTo($tournament->fresh()->scheduled_at),
            'The persisted tournament must still start at or after its registration close.'
        );
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
