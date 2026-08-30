<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
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
}
