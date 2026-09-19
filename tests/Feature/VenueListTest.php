<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The venue list's narrow layout.
 *
 * Below 48rem the row becomes the name on the start edge, its actions on the
 * end edge, and the description across both beneath them. That is a media query
 * and cannot be asserted here; what can be is the markup it depends on, which
 * is easy to break while looking untouched.
 *
 * Since the conversion to Inertia the markup lives in a .vue file, so the hooks
 * are asserted against that file and the decisions against the props.
 */
class VenueListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private const PAGE = 'js/Pages/Poker/Venues/Index.vue';

    public function test_the_description_cell_has_no_whitespace_inside_it(): void
    {
        // The mobile rule hides this cell with :empty, and :empty means NO
        // child nodes at all -- a stray newline between the tag and the
        // interpolation is a text node, and the cell stops being empty as far
        // as CSS is concerned while looking identical in a browser.
        //
        // Asserted against the page source now that the markup lives there.
        // The rendered check this replaces -- assertSee('<td
        // class="venue-row__desc"></td>') -- could only ever see the result,
        // and the result depends on the template being written on one line.
        $page = file_get_contents(resource_path(self::PAGE));

        $this->assertStringContainsString(
            '<td class="venue-row__desc">{{ venue.description }}</td>',
            $page,
            'The description cell must be written on one line, or :empty stops matching it.'
        );
    }

    public function test_the_row_carries_the_hooks_the_narrow_layout_needs(): void
    {
        $page = file_get_contents(resource_path(self::PAGE));

        // venues-table, not the shared card layout: _venues.css gives this
        // list its own narrow treatment keyed on that class.
        $this->assertStringContainsString('venues-table', $page);
        $this->assertStringContainsString('venue-row__name', $page);
        $this->assertStringContainsString('venue-row__desc', $page);
        $this->assertStringContainsString('table__row--link', $page);
    }

    public function test_an_admin_gets_rows_that_link(): void
    {
        Venue::create(['name' => 'Hooked Room', 'address' => '9 Bar St']);

        $this->actingAs($this->admin())->get(route('poker.venues.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows_link', true)
                ->where('venues.data.0.name', 'Hooked Room')
            );
    }

    public function test_a_player_gets_a_row_that_does_not_lead_to_a_403(): void
    {
        // The venue detail page is admin-only, so a row a player could follow
        // is a row that leads to a 403. As a prop this is the decision itself
        // rather than the absence of a URL from some markup.
        Venue::create(['name' => 'Hooked Room', 'address' => '9 Bar St']);

        $this->actingAs(User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']))
            ->get(route('poker.venues.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows_link', false)
                // Reading the list is still the point: the venue is there.
                ->where('venues.data.0.name', 'Hooked Room')
            );
    }
}
