<?php

namespace Tests\Feature;

use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PokerTournamentRegistrantTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrant_store_sets_registered_by_to_authenticated_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();
        $tournament = PokerTournament::factory()->create(['start_time' => now(), 'scheduled_at' => now()]);

        $response = $this->actingAs($admin)->post(route('poker.registrants.store'), [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name . ' ' . $player->last_name,
            'player_nickname' => $player->nickname,
            'registered_at' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('poker.registrants.index'));
        
        $registrant = PokerTournamentRegistrant::first();
        $this->assertEquals($admin->id, $registrant->registered_by);
        $this->assertEquals($admin->id, $registrant->registeredBy->id);
        $this->assertFalse($registrant->is_late_entry);
    }

    public function test_registrant_store_sets_is_late_entry_to_true_when_registering_after_start_time()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();
        $startTime = now()->subHour();
        $tournament = PokerTournament::factory()->create(['start_time' => $startTime, 'scheduled_at' => $startTime]);

        $response = $this->actingAs($admin)->post(route('poker.registrants.store'), [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name . ' ' . $player->last_name,
            'player_nickname' => $player->nickname,
            'registered_at' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('poker.registrants.index'));
        
        $registrant = PokerTournamentRegistrant::first();
        $this->assertTrue($registrant->is_late_entry);
    }

    public function test_user_registrations_performed_relationship()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();
        $tournament = PokerTournament::factory()->create(['start_time' => now(), 'scheduled_at' => now()]);

        $registrant = PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name . ' ' . $player->last_name,
            'player_nickname' => $player->nickname,
            'registered_at' => now(),
            'registered_by' => $admin->id,
        ]);

        $this->assertTrue($admin->registrationsPerformed->contains($registrant));
    }
}
