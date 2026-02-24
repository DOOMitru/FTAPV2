<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PokerSeasonTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_season_is_current_by_default()
    {
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
        ]);

        $this->assertTrue($season->is_current);
    }

    public function test_only_one_season_can_be_current_on_creation()
    {
        $season1 = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
        ]);

        $this->assertTrue($season1->is_current);

        $season2 = PokerSeason::create([
            'name' => 'Season 2',
            'start_date' => now()->addMonths(4),
            'end_date' => now()->addMonths(7),
        ]);

        $this->assertTrue($season2->is_current);
        $this->assertFalse($season1->refresh()->is_current);
    }

    public function test_updating_a_season_to_current_unsets_others()
    {
        $season1 = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonths(4),
            'end_date' => now()->subMonths(1),
            'is_current' => true,
        ]);

        $this->assertTrue($season1->is_current);

        $season2 = PokerSeason::create([
            'name' => 'Season 2',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => false,
        ]);

        $this->assertFalse($season2->refresh()->is_current);
        $this->assertTrue($season1->refresh()->is_current);

        $season2->update(['is_current' => true]);

        $this->assertTrue($season2->is_current);
        $this->assertFalse($season1->refresh()->is_current);
    }

    public function test_controller_store_sets_current_correctly()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->post(route('poker.seasons.store'), [
            'name' => 'Season 1',
            'start_date' => '2024-01-01',
            'end_date' => '2024-03-31',
            'is_current' => '1',
        ]);

        $response->assertRedirect(route('poker.seasons.index'));
        $this->assertTrue(PokerSeason::first()->is_current);
    }
}
