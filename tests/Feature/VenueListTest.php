<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The venue list's narrow layout.
 *
 * Below 48rem the row becomes the name on the start edge, its actions on the
 * end edge, and the description across both beneath them. That is a media query and
 * cannot be asserted here; what can be is the markup it depends on, which is
 * easy to break while looking untouched.
 */
class VenueListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_a_venue_without_a_description_renders_a_truly_empty_cell(): void
    {
        // The layout hides this cell with :empty, so a venue with no
        // description does not leave a blank line under its name. :empty means
        // no child nodes AT ALL -- one newline inside the td is a text node and
        // defeats it, while reading identically in the template.
        Venue::create(['name' => 'Spartan Room', 'address' => '1 Bare Street']);

        $this->actingAs($this->admin())->get(route('poker.venues.index'))->assertOk()
            ->assertSee('<td class="venue-row__desc"></td>', false);
    }

    public function test_a_described_venue_puts_its_description_in_that_cell(): void
    {
        Venue::create([
            'name' => 'Furnished Room',
            'address' => '2 Full Street',
            'description' => 'Eight tables and a decent kettle.',
        ]);

        $this->actingAs($this->admin())->get(route('poker.venues.index'))->assertOk()
            ->assertSee('<td class="venue-row__desc">Eight tables and a decent kettle.</td>', false);
    }

    public function test_the_row_carries_the_hooks_the_narrow_layout_needs(): void
    {
        // Three placements and the header hiding all key off these. Losing any
        // one of them leaves the page looking correct on a desktop.
        Venue::create(['name' => 'Hooked Room', 'address' => '3 Hook Street']);

        $response = $this->actingAs($this->admin())->get(route('poker.venues.index'))->assertOk();

        $response->assertSee('venues-table', false);
        $response->assertSee('venue-row__name', false);
        // venue-row__actions went with the controls it held; they are offered
        // on the venue's own page now, which the row links to for an admin --
        // so the row class carries the link hook alongside its own.
        $response->assertSee('class="venue-row table__row--link"', false);
    }

    public function test_a_player_gets_a_row_that_does_not_lead_to_a_403(): void
    {
        // The venue detail page is admin-only. A linked row would offer a
        // player a click that refuses them -- the same reason this table gave
        // them no View Stats control when it had one.
        Venue::create(['name' => 'Hooked Room', 'address' => '3 Hook Street']);

        $response = $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('poker.venues.index'))->assertOk();

        $response->assertSee('Hooked Room');
        $response->assertSee('class="venue-row"', false);
        $response->assertDontSee('table__row--link', false);
        $response->assertDontSee(route('poker.venues.show', Venue::first()), false);
    }
}
