<?php

namespace Tests\Feature;

use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What survives of the registrant model's own behaviour.
 *
 * Two tests here covered the admin registrant form's store(): that it stamped
 * registered_by with whoever was signed in, and is_late_entry when the entry
 * came after the start time. That form is gone, and it was the ONLY writer of
 * either column -- the tournament page's Register players dialog never set
 * them -- so both are now written by nothing and read by nothing. The
 * relationship below is kept because it still describes the model.
 */
class PokerTournamentRegistrantTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registrations_performed_relationship()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();
        $tournament = PokerTournament::factory()->create(['start_time' => now()]);

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
