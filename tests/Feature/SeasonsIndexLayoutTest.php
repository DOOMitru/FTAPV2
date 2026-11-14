<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * The season list: what it is sent, and the hooks its layout is built on.
 *
 * Below 48rem the four-column table reflows into one card per season: name and
 * the Current badge on the first line, the start and end dates as the single
 * range they always were on the second. That is entirely CSS, and this project
 * has no browser tests, so the layout is verified by screenshot.
 *
 * Since the conversion to Inertia the markup lives in a .vue file rather than
 * in the response, so the hooks are asserted against that file. The DATA is
 * asserted against the props, which is the better half of the trade: the
 * dates are checked as the strings a reader sees rather than as substrings of
 * a page.
 */
class SeasonsIndexLayoutTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private const PAGE = 'js/Pages/Poker/Seasons/Index.vue';

    private function season(): PokerSeason
    {
        return PokerSeason::create([
            'name' => 'Season 40', 'start_date' => '2026-08-06',
            'end_date' => '2026-10-06', 'is_current' => true,
        ]);
    }

    public function test_the_table_carries_the_hook_the_mobile_layout_is_scoped_to(): void
    {
        // Without this class every rule in the media query addresses nothing.
        $this->assertStringContainsString(
            'seasons-index__table',
            file_get_contents(resource_path(self::PAGE))
        );
    }

    public function test_every_cell_the_grid_places_is_classed(): void
    {
        $page = file_get_contents(resource_path(self::PAGE));

        foreach ([
            'seasons-index__name',
            'seasons-index__current',
            'seasons-index__start',
            'seasons-index__end',
        ] as $hook) {
            $this->assertStringContainsString($hook, $page, "The grid has no cell to place for {$hook}.");
        }
    }

    public function test_both_date_columns_are_right_aligned(): void
    {
        // Headers included: .table__num on a bare th loses to .table th, so a
        // header scoped anywhere else sits at the start edge above its own
        // right-aligned figures.
        $page = file_get_contents(resource_path(self::PAGE));

        $this->assertStringContainsString('<th scope="col" class="table__num">Start Date</th>', $page);
        $this->assertStringContainsString('<th scope="col" class="table__num">End Date</th>', $page);
        $this->assertStringContainsString('class="table__num seasons-index__start"', $page);
        $this->assertStringContainsString('class="table__num seasons-index__end"', $page);
    }

    public function test_the_desktop_table_still_has_all_four_headers(): void
    {
        $page = file_get_contents(resource_path(self::PAGE));

        foreach (['Name', 'Current', 'Start Date', 'End Date'] as $header) {
            $this->assertStringContainsString('>'.$header.'</th>', $page);
        }
    }

    public function test_the_empty_state_still_spans_every_column(): void
    {
        // One cell pretending to be a row. Under the grid it needs
        // grid-column: 1 / -1, and colspan is what the CSS selects on.
        $page = file_get_contents(resource_path(self::PAGE));

        $this->assertStringContainsString('colspan="4"', $page);
        $this->assertStringContainsString('No seasons found.', $page);
    }

    public function test_the_dates_arrive_formatted_rather_than_raw(): void
    {
        // The half that moved from markup to props, and reads better for it:
        // the exact string a person sees, not a substring of a rendered page.
        $this->season();

        $this->actingAs($this->admin())->get(route('poker.seasons.index'))->assertOk()
            // No ->component() assertion: Inertia's helper verifies the page
            // file through a view finder that reads config('inertia.testing.*'),
            // keys the published config does not define -- and publishing it
            // turns ssr.enabled on, which this app deliberately does not use.
            // InertiaPropContractTest asserts the component name directly off
            // viewData('page') instead.
            ->assertInertia(fn (Assert $page) => $page
                ->where('seasons.data.0.name', 'Season 40')
                ->where('seasons.data.0.is_current', true)
                ->where('seasons.data.0.start_label', 'Aug 06, 2026')
                ->where('seasons.data.0.end_label', 'Oct 06, 2026')
            );
    }

    /*
     * There is no test for a missing date.
     *
     * seasons.start_date and end_date are NOT NULL in the schema, so a dateless
     * season cannot exist and the controller's `?? '—'` is unreachable. It is
     * kept because the Blade it replaces has the same guard, and parity between
     * the two is worth more than removing a line that costs nothing -- but a
     * test for it would assert a state the database forbids.
     */
}
