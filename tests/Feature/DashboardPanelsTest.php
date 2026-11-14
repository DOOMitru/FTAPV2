<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the dashboard's side column carries.
 *
 * It held Active Season and a Points Structure card: the top five places and
 * their points, with a link out to the full rules. The figures are fixed league
 * rules rather than anything about the player reading them, and they do not
 * change from one visit to the next.
 *
 * This first argued that the tournament page still printed the structure under
 * Points at Stake. That panel has since gone the same way, so the argument is
 * now the simpler one: the points that matter are the ones on offer for the
 * next place, and those are quoted in the Eliminate confirmation at the moment
 * they are awarded. The full table lives on the public rules page.
 */
class DashboardPanelsTest extends TestCase
{
    use RefreshDatabase;

    private function seedStructure(): void
    {
        foreach ([1 => 100, 2 => 85, 3 => 75, 4 => 65, 5 => 55] as $place => $points) {
            PointsStructure::create(['place' => $place, 'points' => $points]);
        }
    }

    public function test_the_dashboard_no_longer_prints_the_points_structure(): void
    {
        $this->seedStructure();

        $this->actingAs(User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']))
            ->get(route('dashboard'))->assertOk()
            ->assertDontSee('Points Structure')
            ->assertDontSee('Full Rules');
    }

    public function test_an_admin_does_not_get_it_either(): void
    {
        // It was never gated, so removing it for one role and not the other
        // would be a half-removal that reads as a permissions rule.
        $this->seedStructure();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('dashboard'))->assertOk()
            ->assertDontSee('Points Structure');
    }

    public function test_the_season_panel_replaced_the_active_season_card(): void
    {
        // The card held Season Rank and Season Points and a link to the full
        // stats. All three live in the season panel now, so keeping it would
        // print two of them twice -- and the link is the only part that was not
        // a duplicate, so it moved rather than went.
        \App\Models\PokerSeason::create([
            'name' => 'Season 9',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $props = $this->actingAs(User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']))
            ->get(route('dashboard'))->assertOk()->viewData('page')['props'];

        // The season IS the heading. "Current Season" is the wording for
        // having none, and the name it displaced sat in a badge in the far
        // corner.
        $this->assertSame('Season 9', $props['season_name']);
        $this->assertNotNull($props['season_href'], 'The link to the full stats went with the card.');

        // Comments stripped first. This page explains what it replaced, and
        // naming the old card in prose is not drawing it -- the same reason
        // ComponentParityTest and UnusedCssClassTest strip them, and the fifth
        // assertion in this suite to have been caught by its own explanation.
        $withoutComments = fn (string $path) => (string) preg_replace(
            ['/<!--.*?-->/s', '#/\*.*?\*/#s'],
            ' ',
            (string) file_get_contents(resource_path($path))
        );

        $page = $withoutComments('js/Pages/Dashboard.vue');
        $panel = $withoutComments('js/Components/SeasonPanel.vue');

        $this->assertStringNotContainsString('Active Season', $page);
        $this->assertStringNotContainsString('Active Season', $panel);
        $this->assertStringContainsString('Full Season Stats', $panel);
    }

    public function test_the_dashboard_renders_with_no_points_structure_at_all(): void
    {
        // The card ran its own query in the template. Nothing on this page
        // reads the table now, so an empty one is not a special case -- which
        // is worth holding, because it used to be.
        $this->assertSame(0, PointsStructure::count());

        $this->actingAs(User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']))
            ->get(route('dashboard'))->assertOk();
    }
}
