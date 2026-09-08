<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * The hooks the mobile season-list layout is built on.
 *
 * Below 48rem the five-column table reflows into one card per season: name and
 * the Current badge on the first line, the start and end dates as the single
 * range they always were on the second, and the row actions held at the end
 * spanning both. That is entirely CSS, and this project has no browser tests,
 * so the layout is verified by screenshot.
 *
 * What can be checked is that the markup still offers the CSS what it needs.
 * Drop a class and the layout fails silently on a phone while every other test
 * stays green.
 */
class SeasonsIndexLayoutTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private function index(): string
    {
        PokerSeason::create([
            'name' => 'Season 40', 'start_date' => '2026-08-06',
            'end_date' => '2026-10-06', 'is_current' => true,
        ]);

        return $this->actingAs($this->admin())
            ->get(route('poker.seasons.index'))->assertOk()->getContent();
    }

    public function test_the_table_carries_the_hook_the_mobile_layout_is_scoped_to(): void
    {
        // Without this class every rule in the media query addresses nothing.
        $this->assertStringContainsString('seasons-index__table', $this->index());
    }

    public function test_every_cell_the_grid_places_is_classed(): void
    {
        $html = $this->index();

        foreach ([
            'seasons-index__name',
            'seasons-index__current',
            'seasons-index__start',
            'seasons-index__end',
            'table__actions',
        ] as $hook) {
            $this->assertStringContainsString($hook, $html, "The grid has no cell to place for {$hook}.");
        }
    }

    public function test_the_desktop_table_still_has_all_five_headers(): void
    {
        // The mobile layout hides the header row rather than removing it, and
        // desktop reads exactly as it did.
        $html = $this->index();

        foreach (['Name', 'Current', 'Start Date', 'End Date', 'Actions'] as $header) {
            $this->assertStringContainsString('>'.$header.'</th>', $html);
        }
    }

    public function test_the_empty_state_still_spans_every_column(): void
    {
        // It is one cell pretending to be a row. Under the grid it needs
        // grid-column: 1 / -1, and colspan is what the CSS selects on.
        $html = $this->actingAs($this->admin())
            ->get(route('poker.seasons.index'))->assertOk()->getContent();

        $this->assertStringContainsString('colspan="5"', $html);
        $this->assertStringContainsString('No seasons found.', $html);
    }
}
