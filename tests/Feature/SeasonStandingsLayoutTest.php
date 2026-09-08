<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * The hooks the mobile standings layout is built on.
 *
 * Below 48rem the seven-column table reflows into one card per player -- rank
 * badge spanning the height, name and finale mark on the first line, the meter
 * below it, and the three secondary numbers in a run. That is entirely CSS, and
 * this project has no browser tests, so the layout itself is verified by
 * screenshot.
 *
 * What CAN be checked is that the markup still offers the CSS what it needs.
 * Every class and data-label here is a grid placement or a rendered label; drop
 * one and the mobile layout fails silently on a phone while every other test
 * stays green.
 */
class SeasonStandingsLayoutTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private function standings(): string
    {
        PointsStructure::create(['place' => 1, 'points' => 100]);

        $tournament = $this->tournament();
        $player = User::factory()->create([
            'first_name' => 'Wanda', 'last_name' => 'Reeve', 'approval_status' => 'approved',
        ]);

        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        return $this->actingAs($this->admin())
            ->get(route('seasons.show', PokerSeason::first()))
            ->assertOk()->getContent();
    }

    public function test_the_table_carries_the_hook_the_mobile_layout_is_scoped_to(): void
    {
        // Without this class every rule in the media query addresses nothing.
        $this->assertStringContainsString('season-show__standings', $this->standings());
    }

    public function test_every_cell_the_grid_places_is_classed(): void
    {
        $html = $this->standings();

        foreach ([
            'season-show__rank',
            'season-show__player',
            'season-show__meter-cell',
            'season-show__stat--played',
            'season-show__stat--won',
            'season-show__stat--venue',
            'season-show__finale',
        ] as $hook) {
            $this->assertStringContainsString($hook, $html, "The grid has no cell to place for {$hook}.");
        }
    }

    public function test_the_secondary_stats_carry_the_labels_they_render(): void
    {
        // On a phone these are drawn from data-label by ::after -- "1 played",
        // not a bare number in a column whose header is hidden.
        $html = $this->standings();

        $this->assertStringContainsString('data-label="played"', $html);
        $this->assertStringContainsString('data-label="won"', $html);
        $this->assertStringContainsString('data-label="venue pts"', $html);
    }

    public function test_the_desktop_table_still_has_all_seven_headers(): void
    {
        // The mobile layout hides the header row rather than removing it, and
        // desktop reads exactly as it did.
        $html = $this->standings();

        foreach (['Rank', 'Player', 'Pts', 'Played', 'Won', 'Venue pts', 'Finale'] as $header) {
            $this->assertStringContainsString('>'.$header.'</th>', $html);
        }
    }
}
