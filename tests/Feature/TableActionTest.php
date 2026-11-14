<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\Sponsor;
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

        $this->actingAs($this->admin())->get(route('users.index'))->assertOk();

        // The label is passed once and the component turns it into BOTH the
        // visually-hidden name and the title -- so the page asks for it, and
        // Action.vue is where the two come from.
        $page = file_get_contents(resource_path('js/Pages/Users/Index.vue'));

        foreach (['View', 'Edit', 'Delete'] as $action) {
            $this->assertStringContainsString('label="'.$action.'"', $page);
        }

        $component = file_get_contents(resource_path('js/Components/Action.vue'));

        $this->assertStringContainsString('<span class="u-visually-hidden">{{ label }}</span>', $component);
        $this->assertStringContainsString(':title="label"', $component);
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

        $this->actingAs($this->admin())->get(route('users.index'))->assertOk();

        $page = file_get_contents(resource_path('js/Pages/Users/Index.vue'));

        foreach (['Approve', 'Reject'] as $action) {
            $this->assertStringContainsString('label="'.$action.'"', $page);
        }
    }

    public function test_a_destructive_action_is_still_a_submit_button(): void
    {
        // The component picks <a> or <button> from whether it was given an
        // href. Delete has none and must stay a submit inside its form, or the
        // row grows a control that looks right and does nothing.
        User::factory()->create();

        $this->actingAs($this->admin())->get(route('users.index'))->assertOk();

        // The rule lives in the component: it renders a Link when given an
        // href and a submit button otherwise. Both directions matter -- a
        // destructive action that is a link does nothing, and a navigating
        // action that is a button looks right and goes nowhere.
        $component = file_get_contents(resource_path('js/Components/Action.vue'));

        $this->assertStringContainsString(
            'v-bind="href ? { href } : { type: \'submit\' }"',
            $component,
            'The component must pick a submit button when it has no href.'
        );

        $this->assertStringContainsString(':is="href ? Link : \'button\'"', $component);

        // And the page asks the right way round: Delete carries no href.
        $page = file_get_contents(resource_path('js/Pages/Users/Index.vue'));

        $this->assertStringContainsString('<Action icon="delete" label="Delete" danger />', $page);
        $this->assertMatchesRegularExpression('/<Action icon="view" label="View" :href=/', $page);
    }

    public function test_the_user_tables_are_stacked_and_their_cells_are_labelled(): void
    {
        // Without data-label the stacked view is a column of bare values with
        // nothing naming them -- the header row is off-screen by then.
        User::factory()->create(['approval_status' => 'pending']);

        $this->actingAs($this->admin())->get(route('users.index'))->assertOk();

        $page = file_get_contents(resource_path('js/Pages/Users/Index.vue'));

        // `stacked` on the component, which becomes table--stacked.
        $this->assertSame(2, substr_count($page, '<Table stacked>'),
            'Both user tables stack on a narrow screen.');

        // Approval is gone from this table, by request. A rejected account now
        // reads the same as an approved one here; the status, and the way back
        // from a rejection, are on the account page the View action opens.
        foreach (['Name', 'Nickname', 'Email', 'Role'] as $column) {
            $this->assertStringContainsString('data-label="'.$column.'"', $page);
        }

        // The queue above has its own columns.
        $this->assertStringContainsString('data-label="Registered"', $page);
    }

    public function test_the_other_dashboard_tables_use_icon_actions_too(): void
    {
        // One table from the others, to prove the sweep reached beyond the page
        // the request named. It needs a row: an empty table renders its empty
        // state and no actions at all, and the assertions below would then be
        // testing nothing.
        //
        // The seasons list used to be this sample and no longer has actions at
        // all -- its row is the link, and editing and deleting are on the
        // season's page. Sponsors is a listing that still carries them.
        Sponsor::create(['name' => 'Ace Supplies', 'logo_path' => 'sponsor-logos/ace.png']);

        $this->actingAs($this->admin())->get(route('sponsors.index'))->assertOk();

        // Sponsors is an Inertia page now, so the icon action is rendered by
        // the Action component in the browser. What the page file shows is
        // that it asks for one, with the label the component turns into both a
        // title and a visually-hidden span.
        $page = file_get_contents(resource_path('js/Pages/Sponsors/Index.vue'));

        $this->assertStringContainsString('<Action icon="edit" label="Edit"', $page);

        $component = file_get_contents(resource_path('js/Components/Action.vue'));

        // The class is composed, not written as an attribute: the component
        // builds `action` plus an optional --danger modifier.
        // Single-quoted: PHP interpolates \${ inside double quotes, and this
        // string is looking for exactly that sequence in the Vue source.
        $this->assertStringContainsString('`action${', $component);
        $this->assertStringContainsString(':class="classes"', $component);
        $this->assertStringContainsString('<span class="u-visually-hidden">{{ label }}</span>', $component);
    }
}
