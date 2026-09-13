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
 * rules rather than anything about the player reading them, they do not change
 * from one visit to the next, and the tournament page already prints the whole
 * structure under Points at Stake -- where it is about the tournament in front
 * of you rather than a table on a page about you.
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

    public function test_the_side_column_still_carries_the_active_season(): void
    {
        // The other card in that column. Removing one must not take the stack
        // with it.
        $this->actingAs(User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']))
            ->get(route('dashboard'))->assertOk()
            ->assertSee('Active Season');
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
