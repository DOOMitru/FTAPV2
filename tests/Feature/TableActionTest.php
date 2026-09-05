<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Row actions across the dashboard tables, and the stacked table view.
 *
 * The actions are icons now, which means every one of them has lost the text
 * that used to be its accessible name. That name is the whole risk of the
 * change: an icon-only control with nothing else in it announces as "link" or
 * "button", and nothing about the page looks wrong when it happens.
 *
 * The stacked view is the same table reflowed by CSS rather than a second copy
 * of the rows, so what is testable server-side is the markup it depends on --
 * the modifier and the per-cell labels. The reflow itself is a media query, and
 * this project has no browser-based tests.
 */
class TableActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_every_row_action_carries_its_name(): void
    {
        User::factory()->create(['first_name' => 'Listed', 'last_name' => 'Player']);

        $response = $this->actingAs($this->admin())->get(route('users.index'))->assertOk();

        foreach (['View', 'Edit', 'Delete'] as $action) {
            $response->assertSee('<span class="u-visually-hidden">'.$action.'</span>', false);
            $response->assertSee('title="'.$action.'"', false);
        }
    }

    public function test_the_approval_queue_actions_carry_their_names(): void
    {
        // Approve and Reject exist only on this table, so the icon switch has a
        // branch nothing else reaches.
        User::factory()->create([
            'first_name' => 'Waiting',
            'last_name' => 'Candidate',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin())->get(route('users.index'))->assertOk();

        foreach (['Approve', 'Reject'] as $action) {
            $response->assertSee('<span class="u-visually-hidden">'.$action.'</span>', false);
        }
    }

    public function test_a_destructive_action_is_still_a_submit_button(): void
    {
        // The component picks <a> or <button> from whether it was given an
        // href. Delete has none and must stay a submit inside its form, or the
        // row grows a control that looks right and does nothing.
        User::factory()->create();

        $html = $this->actingAs($this->admin())->get(route('users.index'))
            ->assertOk()->getContent();

        // Matched as a tag, not as the string type="submit" -- the search
        // form's button carries that too, so the looser assertion passed even
        // when every action was rendered as an anchor.
        $this->assertMatchesRegularExpression(
            '/<button[^>]*class="action action--danger"/',
            $html,
            'Delete must render as a submit button.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*class="action action--danger"/',
            $html,
            'A destructive action must never be a link.'
        );

        // And the other direction, which was unguarded: rendering everything as
        // a button leaves View and Edit looking right and navigating nowhere.
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]+"[^>]*class="action"/',
            $html,
            'A navigating action must render as an anchor with an href.'
        );
    }

    public function test_the_user_tables_are_stacked_and_their_cells_are_labelled(): void
    {
        // Without data-label the stacked view is a column of bare values with
        // nothing naming them -- the header row is off-screen by then.
        User::factory()->create(['approval_status' => 'pending']);

        $response = $this->actingAs($this->admin())->get(route('users.index'))->assertOk();

        $response->assertSee('table--stacked', false);

        foreach (['Name', 'Nickname', 'Email', 'Role', 'Approval'] as $column) {
            $response->assertSee('data-label="'.$column.'"', false);
        }

        // The queue above has its own columns.
        $response->assertSee('data-label="Registered"', false);
    }

    public function test_the_other_dashboard_tables_use_icon_actions_too(): void
    {
        // One table from the other eight, to prove the sweep reached beyond
        // the page the request named. It needs a row: an empty table renders
        // its empty state and no actions at all, and the assertions below would
        // then be testing nothing.
        PokerSeason::create([
            'name' => 'Season 21',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        $this->actingAs($this->admin())->get(route('poker.seasons.index'))->assertOk()
            ->assertSee('class="action"', false)
            ->assertSee('<span class="u-visually-hidden">View Stats</span>', false);
    }
}
