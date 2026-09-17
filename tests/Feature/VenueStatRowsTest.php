<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The venue page's figures, on a phone.
 *
 * Below 48rem they stop being tiles and become labelled lines -- the same
 * treatment the season page gives its own, so the two read alike. That is
 * entirely CSS and this project has no browser tests, so what can be checked
 * here is that the markup offers the component what it needs, and that the one
 * rule which would silently cancel it is still in place.
 */
class VenueStatRowsTest extends TestCase
{
    use RefreshDatabase;

    private function venuePage(): string
    {
        $season = PokerSeason::create([
            'name' => 'Season 9', 'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(), 'is_current' => true,
        ]);

        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '1 St']);

        PokerTournament::create([
            'name' => 'Night', 'start_time' => now()->subDays(3),
            'venue_id' => $venue->id, 'season_id' => $season->id,
        ]);

        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        return $this->actingAs($admin)
            ->get(route('poker.venues.show', $venue))->assertOk()->getContent();
    }

    public function test_the_figures_carry_the_hook_the_phone_layout_is_scoped_to(): void
    {
        // Counted as a class attribute, not as a substring: "stat-rows" also
        // occurs inside "stat-rows--boxed", so substr_count reports a group
        // that is not there.
        $html = $this->venuePage();

        $this->assertSame(1, preg_match_all('/class="[^"]*\bstat-rows\b/', $html));
    }

    public function test_the_group_is_not_boxed(): void
    {
        // --boxed is for a group standing on the page. These sit inside a card
        // that already draws the edge, and the body around them supplies the
        // gutter -- boxed here would be a box in a box, indented past the
        // venue's own name and description.
        $this->assertStringNotContainsString('stat-rows--boxed', $this->venuePage());
    }

    public function test_the_grid_gets_out_of_the_way_below_the_breakpoint(): void
    {
        // The rule this whole treatment hangs on, and the one that would fail
        // silently. .stat-rows says display:block below 48rem, but it and
        // .venue-show__stats are both single classes -- equal specificity --
        // so the later FILE wins, and 4-pages is imported after 3-components.
        // Without this the grid survives inside the media query and the rows
        // stay in two columns wearing the component's padding and type.
        $css = file_get_contents(resource_path('css/4-pages/_venue-show.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 48rem\) \{\s*\.venue-show__stats \{\s*display: block;/',
            $css,
            'The page grid must yield to .stat-rows below the breakpoint.'
        );
    }

    public function test_the_leaderboard_has_three_columns(): void
    {
        // Earned Count was removed by request. The column count is worth
        // pinning because the empty state's colspan has to match it, and a
        // colspan that is one too wide does not fail -- it draws a cell that
        // overhangs the table, which only shows up on a venue nobody has
        // earned at.
        $html = $this->venuePage();

        $start = strpos($html, 'Venue points leaderboard');
        $table = substr($html, $start, strpos($html, '</table>', $start) - $start);

        $this->assertSame(3, preg_match_all('/<th\b/', $table));
        $this->assertStringContainsString('colspan="3"', $table);

        foreach (['Rank', 'Player', 'Total points'] as $header) {
            $this->assertStringContainsString('>'.$header.'</th>', $table);
        }

        $this->assertStringNotContainsString('Earned Count', $table);
    }

    public function test_the_two_pages_use_the_same_component(): void
    {
        // The point of the exercise: one treatment, not two that resemble each
        // other until one of them is edited.
        foreach (['poker/venues/show.blade.php', 'poker/seasons/show.blade.php'] as $view) {
            $this->assertStringContainsString(
                'stat-rows', file_get_contents(resource_path('views/'.$view)),
                $view.' draws its figures some other way.'
            );
        }
    }
}
