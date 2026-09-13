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

    private function standings(bool $withThresholds = false): string
    {
        PointsStructure::create(['place' => 1, 'points' => 100]);

        $tournament = $this->tournament();
        $player = User::factory()->create([
            'first_name' => 'Wanda', 'last_name' => 'Reeve', 'approval_status' => 'approved',
        ]);

        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        $season = PokerSeason::first();

        if ($withThresholds) {
            $season->forceFill([
                'finale_points_required' => 500,
                'finale_wins_required' => 1,
                'finale_venue_points_required' => 50,
            ])->save();
        }

        return $this->actingAs($this->admin())
            ->get(route('seasons.show', $season))
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
        $this->assertStringContainsString('data-label="venue points"', $html);
    }

    public function test_the_desktop_table_still_has_all_seven_headers(): void
    {
        // The mobile layout hides the header row rather than removing it, and
        // desktop reads exactly as it did.
        $html = $this->standings();

        foreach (['Rank', 'Player', 'Points', 'Played', 'Wins', 'Venue points', 'Finale'] as $header) {
            $this->assertStringContainsString('>'.$header.'</th>', $html);
        }
    }

    public function test_the_season_figures_are_marked_to_become_rows_on_a_phone(): void
    {
        // Three thirds of a 375px screen leave each figure about 100px, which
        // is why the trio already had to drop its type two steps to fit. As
        // rows the label takes the start and the figure the end, both with the
        // whole width -- the way the dashboard's season panel lists the same
        // kind of thing.
        //
        // Which shape is drawn is CSS and was measured instead: at 1440 the
        // group is a grid with the figure stacked under its label at 56px; at
        // 375 and 320 it is a block with the two inline at 18px and a 42px row,
        // with no horizontal overflow.
        // Thresholds set, so BOTH groups render: without them the finale panel
        // shows an empty state instead, and a count over one group would pass
        // whatever the other one did.
        $html = $this->standings(withThresholds: true);

        // Counted as class attributes, not as substrings. "stat-rows" also
        // occurs inside "stat-rows--boxed", so substr_count reported two for a
        // single group and this test passed while measuring nothing.
        $this->assertSame(2, preg_match_all('/class="[^"]*\bstat-rows\b/', $html));
    }

    public function test_only_the_group_outside_a_card_draws_its_own_edge(): void
    {
        // --boxed is the difference. The finale thresholds sit inside a card
        // that already has a border; a second one around them would be a box
        // in a box, and the rows would be indented past the card's own text.
        $html = $this->standings(withThresholds: true);

        $this->assertSame(1, preg_match_all('/\bstat-rows--boxed\b/', $html));

        // And it is the standalone group that has it -- the one drawn before
        // the Finale Qualification card.
        $this->assertLessThan(
            strpos($html, 'Finale Qualification'),
            strpos($html, 'stat-rows--boxed')
        );
    }

    public function test_the_finale_thresholds_read_wins_then_season_then_venue(): void
    {
        // Order is a decision, so it is pinned. The helper sets three DIFFERENT
        // figures on purpose -- 1, 500, 50 -- because the way a reorder goes
        // wrong is a label moving without its value, and three identical
        // numbers would hide exactly that.
        $html = $this->standings(withThresholds: true);

        $this->assertStringContainsString(
            '<span class="stat__label">Tournament Wins</span><span class="stat__value">1</span>',
            preg_replace('/>\s+</', '><', $html)
        );

        $this->assertStringContainsString(
            '<span class="stat__label">Season points</span><span class="stat__value">500</span>',
            preg_replace('/>\s+</', '><', $html)
        );

        $this->assertStringContainsString(
            '<span class="stat__label">Venue points</span><span class="stat__value">50</span>',
            preg_replace('/>\s+</', '><', $html)
        );

        // And in that order down the panel.
        $panel = substr($html, (int) strpos($html, 'Finale Qualification'));

        $this->assertLessThan(strpos($panel, 'Season points'), strpos($panel, 'Tournament Wins'));
        $this->assertLessThan(strpos($panel, 'Venue points'), strpos($panel, 'Season points'));
    }
}
