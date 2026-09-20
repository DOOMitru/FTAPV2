<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Managing the points structure.
 *
 * The structure is a ladder: a contiguous run of places from 1 to N. Places
 * are not chosen -- a new entry is always one deeper than the deepest, and only
 * the deepest can be removed. A hole in the ladder is invisible on the admin
 * screen and surfaces much later as a finish that scores nothing, so the rule
 * is enforced in the controller rather than left to whoever is typing.
 */
class PointsStructureAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function ladder(int ...$points): void
    {
        foreach ($points as $index => $value) {
            PointsStructure::create(['place' => $index + 1, 'points' => $value]);
        }
    }

    public function test_the_first_entry_takes_first_place(): void
    {
        $this->actingAs($this->admin())
            ->post(route('poker.points-structure.store'), ['points' => 100])
            ->assertRedirect(route('poker.points-structure.create'));

        $this->assertSame(1, PointsStructure::firstOrFail()->place);
    }

    public function test_each_entry_takes_the_next_place_down(): void
    {
        $admin = $this->admin();

        foreach ([100, 85, 75] as $points) {
            $this->actingAs($admin)->post(route('poker.points-structure.store'), ['points' => $points]);
        }

        $this->assertSame([1, 2, 3], PointsStructure::orderBy('place')->pluck('place')->all());
        $this->assertSame([100, 85, 75], PointsStructure::orderBy('place')->pluck('points')->all());
    }

    public function test_a_place_posted_by_hand_is_ignored(): void
    {
        // The place is computed, so a crafted request cannot open a gap at 99
        // or collide with a row that already exists.
        $this->ladder(100);

        $this->actingAs($this->admin())
            ->post(route('poker.points-structure.store'), ['place' => 99, 'points' => 85]);

        $this->assertSame([1, 2], PointsStructure::orderBy('place')->pluck('place')->all());
    }

    public function test_points_must_be_greater_than_zero(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('poker.points-structure.store'), ['points' => 0])
            ->assertSessionHasErrors('points');

        $this->actingAs($admin)
            ->post(route('poker.points-structure.store'), ['points' => -5])
            ->assertSessionHasErrors('points');

        $this->assertSame(0, PointsStructure::count());
    }

    public function test_the_points_for_any_place_can_be_edited(): void
    {
        $this->ladder(100, 85, 75);
        $middle = PointsStructure::where('place', 2)->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('poker.points-structure.update', $middle), ['points' => 90])
            ->assertRedirect(route('poker.points-structure.index'));

        $this->assertSame(90, $middle->fresh()->points);
        $this->assertSame(2, $middle->fresh()->place, 'Editing points must not move the place.');
    }

    public function test_editing_cannot_move_a_place(): void
    {
        $this->ladder(100, 85);
        $first = PointsStructure::where('place', 1)->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('poker.points-structure.update', $first), ['place' => 7, 'points' => 120]);

        $this->assertSame(1, $first->fresh()->place);
        $this->assertSame(120, $first->fresh()->points);
    }

    public function test_edited_points_must_also_be_greater_than_zero(): void
    {
        $this->ladder(100);
        $entry = PointsStructure::firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('poker.points-structure.update', $entry), ['points' => 0])
            ->assertSessionHasErrors('points');

        $this->assertSame(100, $entry->fresh()->points);
    }

    public function test_the_last_place_can_be_removed(): void
    {
        $this->ladder(100, 85, 75);
        $last = PointsStructure::where('place', 3)->firstOrFail();

        $this->actingAs($this->admin())->delete(route('poker.points-structure.destroy', $last));

        $this->assertSame([1, 2], PointsStructure::orderBy('place')->pluck('place')->all());
    }

    public function test_a_middle_place_cannot_be_removed(): void
    {
        // This is the whole point: removing 2 of 1-2-3 leaves a ladder with a
        // rung missing, and nothing on the screen would say so.
        $this->ladder(100, 85, 75);
        $middle = PointsStructure::where('place', 2)->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('poker.points-structure.destroy', $middle))
            ->assertSessionHas('error');

        $this->assertSame([1, 2, 3], PointsStructure::orderBy('place')->pluck('place')->all());
    }

    public function test_removing_the_last_place_twice_over_walks_back_down(): void
    {
        // After the deepest goes, the one above it becomes removable in turn.
        $this->ladder(100, 85, 75);
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('poker.points-structure.destroy',
            PointsStructure::where('place', 3)->firstOrFail()));
        $this->actingAs($admin)->delete(route('poker.points-structure.destroy',
            PointsStructure::where('place', 2)->firstOrFail()));

        $this->assertSame([1], PointsStructure::orderBy('place')->pluck('place')->all());
    }

    public function test_the_index_offers_delete_on_the_last_row_only(): void
    {
        $this->ladder(100, 85, 75);

        // can_delete per row, which is the decision itself.
        //
        // The assertion this replaces matched the delete form's action URL,
        // and its comment records why it had to: a count of the row's id got
        // it wrong, because the id appears twice on the deepest row -- once
        // for edit and once for delete. There is no URL to match now, and
        // nothing to miscount: the row either carries the permission or it
        // does not.
        $props = $this->actingAs($this->admin())->get(route('poker.points-structure.index'))
            ->assertOk()->viewData('page')['props'];

        $byPlace = collect($props['structures']['data'])->keyBy('place');

        $this->assertTrue($byPlace[3]['can_delete'],
            'The deepest place must be offered a delete.');

        foreach ([1, 2] as $place) {
            $this->assertFalse($byPlace[$place]['can_delete'],
                "Place {$place} must not be offered a delete.");
        }

        // And the sentence it would ask, formatted in PHP.
        $this->assertSame('Remove 3rd place, worth 75 points?', $byPlace[3]['delete_confirm']);
    }

    public function test_the_create_form_shows_the_place_it_will_assign(): void
    {
        $this->ladder(100, 85);

        $props = $this->actingAs($this->admin())->get(route('poker.points-structure.create'))
            ->assertOk()->viewData('page')['props'];

        // The ordinal, ready to render. Which suffix a number takes is a
        // language question and PHP answers it -- the same Number::ordinal()
        // the flash message and the delete confirmation use.
        $this->assertSame('3rd', $props['next_place_label']);

        // And no input for it. The place is computed by store(); a posted one
        // could collide with a row that exists or leave a gap.
        $this->assertStringNotContainsString('name="place"', $this->createPage());
    }

    /**
     * The create page's own source, comments stripped.
     *
     * Read rather than rendered: the form is Vue, so the server sends props
     * and an empty shell. Comments go first because this page explains at
     * length why there is no place input, and prose naming the thing it
     * forbids would fail the assertion that forbids it -- the mistake three
     * assertions in this suite have already made.
     */
    private function createPage(): string
    {
        $path = resource_path('js/Pages/Poker/PointsStructure/Create.vue');

        $this->assertFileExists($path);

        return (string) preg_replace(
            '/<!--.*?-->|\/\*.*?\*\//s',
            '',
            (string) file_get_contents($path)
        );
    }

    public function test_a_player_cannot_touch_the_structure(): void
    {
        $this->ladder(100);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('poker.points-structure.store'), ['points' => 50])
            ->assertForbidden();

        $this->assertSame(1, PointsStructure::count());
    }

    public function test_saving_returns_to_the_form_ready_for_the_next_place(): void
    {
        // The whole point of the loop: what comes back is the form again,
        // already asking for the following place, with the input focused so
        // the next figure can just be typed.
        $admin = $this->admin();
        $this->ladder(100, 85);

        $this->actingAs($admin)
            ->post(route('poker.points-structure.store'), ['points' => 75])
            ->assertRedirect(route('poker.points-structure.create'));

        $props = $this->actingAs($admin)->get(route('poker.points-structure.create'))
            ->assertOk()->viewData('page')['props'];

        $this->assertSame('4th', $props['next_place_label']);

        // Focused on arrival. autofocus does that on a fresh page; the page
        // that comes back from a save is the SAME component re-rendered, where
        // the attribute has no effect at all -- so the component focuses the
        // input itself, and this asks for both.
        $page = $this->createPage();

        $this->assertStringContainsString('autofocus', $page);
        $this->assertStringContainsString("getElementById('points')", $page);
    }

    public function test_the_form_reports_what_it_just_saved(): void
    {
        // It is the only place the confirmation can land now. Without it a
        // successful entry and a form that did nothing look the same.
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('poker.points-structure.store'), ['points' => 100])
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->actingAs($admin)->get(route('poker.points-structure.create'))->assertOk()
            ->assertSee('1st place added, worth 100 points.');
    }

    public function test_the_form_offers_the_way_back_to_the_listing(): void
    {
        $page = $this->createPage();

        $this->assertStringContainsString('href="/poker/points-structure"', $page);
        $this->assertStringContainsString('>Back<', $page);
    }

    public function test_entering_a_whole_ladder_in_one_sitting_walks_down(): void
    {
        // Four submissions in a row, each following the redirect the way a
        // browser would, land as 1st through 4th rather than fighting over a
        // place.
        $admin = $this->admin();

        foreach ([100, 85, 75, 60] as $points) {
            $this->actingAs($admin)
                ->post(route('poker.points-structure.store'), ['points' => $points])
                ->assertRedirect(route('poker.points-structure.create'));
        }

        $this->assertSame([1, 2, 3, 4], PointsStructure::orderBy('place')->pluck('place')->all());
        $this->assertSame([100, 85, 75, 60], PointsStructure::orderBy('place')->pluck('points')->all());
    }
}
