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
            ->assertRedirect(route('poker.points-structure.index'));

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

        $html = $this->actingAs($this->admin())->get(route('poker.points-structure.index'))
            ->assertOk()->getContent();

        // The delete form's action, not a count of the row's id -- the id
        // appears twice on the deepest row, once for edit and once for delete,
        // which is what a count-of-one assertion got wrong here.
        $this->assertStringContainsString(
            'action="'.route('poker.points-structure.destroy', PointsStructure::where('place', 3)->value('id')).'"',
            $html,
            'The deepest place must be offered a delete form.'
        );

        foreach ([1, 2] as $place) {
            $id = PointsStructure::where('place', $place)->value('id');
            $this->assertStringNotContainsString(
                'action="'.route('poker.points-structure.destroy', $id).'"',
                $html,
                "Place {$place} must not be offered a delete form."
            );
        }
    }

    public function test_the_create_form_shows_the_place_it_will_assign(): void
    {
        $this->ladder(100, 85);

        $this->actingAs($this->admin())->get(route('poker.points-structure.create'))->assertOk()
            ->assertSee('3rd')
            ->assertDontSee('name="place"', false);
    }

    public function test_a_player_cannot_touch_the_structure(): void
    {
        $this->ladder(100);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('poker.points-structure.store'), ['points' => 50])
            ->assertForbidden();

        $this->assertSame(1, PointsStructure::count());
    }
}
