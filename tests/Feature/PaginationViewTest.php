<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

/**
 * Both kinds of paginator have to render.
 *
 * The design-system view windows page numbers, so it asks for total() and
 * lastPage(). A LengthAwarePaginator has those; a simple Paginator does not --
 * it knows only whether there is another page. Registering that view as the
 * default for BOTH turned the first ever call to simplePaginate() into a fatal
 * error, and since nothing in the app calls it, the fault was waiting for
 * whoever reached for it first.
 */
class PaginationViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_simple_paginator_renders(): void
    {
        $paginator = (new Paginator([1, 2, 3], perPage: 2, currentPage: 1))->withPath('/things');

        $html = (string) $paginator->links();

        $this->assertNotSame('', trim($html), 'A simple paginator rendered nothing.');
    }

    public function test_a_length_aware_paginator_still_gets_the_design_system_view(): void
    {
        // The half that IS in use, on seven admin pages and the events list.
        // Unregistering the simple view must not have taken this with it.
        $paginator = (new LengthAwarePaginator([1, 2, 3], total: 30, perPage: 3, currentPage: 2))
            ->withPath('/things');

        $html = (string) $paginator->links();

        $this->assertStringContainsString('pager', $html, 'The design-system view is no longer the default.');
    }

    public function test_the_paginated_admin_pages_still_page(): void
    {
        // End to end, on a page that really paginates. The listing pages at
        // fifteen, so twenty is a second page; approved, because pending
        // players go to the queue above the table rather than into it.
        User::factory()->count(20)->create([
            'approval_status' => 'approved',
            'approval_decided_at' => now(),
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertSee('pager', false);
    }
}
