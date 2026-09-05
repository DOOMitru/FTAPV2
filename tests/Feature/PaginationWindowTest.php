<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

/**
 * The shared pagination component's page window.
 *
 * One view, registered as Paginator::defaultView, renders every paginated list
 * in the app -- the admin index pages and the public events list. It shows the
 * first two pages, the last two, and the page you are on, with an ellipsis
 * wherever it skipped something.
 *
 * Most of these render the paginator directly rather than going through a
 * route: it takes a hundred rows to make ten pages of an admin list, and the
 * window is a property of the component, not of any one page. The last test
 * closes that gap by checking a real page uses this component.
 */
class PaginationWindowTest extends TestCase
{
    use RefreshDatabase;

    private function render(int $totalPages, int $currentPage): string
    {
        $perPage = 10;

        return (new LengthAwarePaginator(
            items: range(1, $perPage),
            total: $totalPages * $perPage,
            perPage: $perPage,
            currentPage: $currentPage,
            options: ['path' => 'http://localhost/things'],
        ))->links()->toHtml();
    }

    /** The link Laravel renders for a page, which is unambiguous in the markup. */
    private function linkTo(int $page): string
    {
        return 'Go to page '.$page;
    }

    public function test_a_middle_page_shows_only_the_ends_and_itself(): void
    {
        $html = $this->render(totalPages: 10, currentPage: 5);

        foreach ([1, 2, 9, 10] as $shown) {
            $this->assertStringContainsString($this->linkTo($shown), $html, "Page {$shown} should be linked.");
        }

        // The whole point: everything else is gone.
        foreach ([3, 4, 6, 7, 8] as $hidden) {
            $this->assertStringNotContainsString($this->linkTo($hidden), $html, "Page {$hidden} should not be linked.");
        }

        // Current page is marked, not linked.
        $this->assertStringContainsString('aria-current="page">5<', $html);
        $this->assertStringNotContainsString($this->linkTo(5), $html);

        // A gap either side of it.
        $this->assertSame(2, substr_count($html, 'pager__gap'));
    }

    public function test_an_early_page_has_a_single_gap(): void
    {
        // Page 2 is already in the first pair, so there is nothing extra to
        // show and only one run is skipped.
        $html = $this->render(totalPages: 10, currentPage: 2);

        $this->assertStringContainsString($this->linkTo(1), $html);
        $this->assertStringContainsString($this->linkTo(9), $html);
        $this->assertStringContainsString($this->linkTo(10), $html);
        $this->assertStringNotContainsString($this->linkTo(3), $html);
        $this->assertStringContainsString('aria-current="page">2<', $html);
        $this->assertSame(1, substr_count($html, 'pager__gap'));
    }

    public function test_a_late_page_has_a_single_gap(): void
    {
        $html = $this->render(totalPages: 10, currentPage: 9);

        $this->assertStringContainsString($this->linkTo(1), $html);
        $this->assertStringContainsString($this->linkTo(2), $html);
        $this->assertStringContainsString($this->linkTo(10), $html);
        $this->assertStringNotContainsString($this->linkTo(8), $html);
        $this->assertStringContainsString('aria-current="page">9<', $html);
        $this->assertSame(1, substr_count($html, 'pager__gap'));
    }

    public function test_a_short_list_shows_every_page_and_no_gap(): void
    {
        // Four pages is exactly the set already: 1, 2, 3, 4. Rendering an
        // ellipsis here would claim something was skipped when nothing was.
        $html = $this->render(totalPages: 4, currentPage: 2);

        foreach ([1, 3, 4] as $shown) {
            $this->assertStringContainsString($this->linkTo($shown), $html);
        }

        $this->assertStringNotContainsString('pager__gap', $html);
    }

    public function test_the_smallest_paginated_list_does_not_duplicate_a_page(): void
    {
        // Two pages: the set starts as 1, 2, 1, 1, 2 -- $lastPage - 1 is 1, and
        // so is the first entry. Without unique() page 1 renders three times.
        $html = $this->render(totalPages: 2, currentPage: 1);

        $this->assertSame(1, substr_count($html, $this->linkTo(2)));
        $this->assertSame(1, substr_count($html, 'aria-current="page">1<'));
        $this->assertStringNotContainsString('pager__gap', $html);
    }

    public function test_three_pages_do_not_repeat_the_middle_one(): void
    {
        // 1, 2, 2, 2, 3 before deduping.
        $html = $this->render(totalPages: 3, currentPage: 2);

        $this->assertSame(1, substr_count($html, 'aria-current="page">2<'));
        $this->assertSame(1, substr_count($html, $this->linkTo(1)));
        $this->assertSame(1, substr_count($html, $this->linkTo(3)));
        $this->assertStringNotContainsString('pager__gap', $html);
    }

    public function test_the_user_management_list_uses_this_component(): void
    {
        // The page the change was asked for, on the real route. 15 per page,
        // and the admin is one of the rows -- 60 + 1 is 61, which is five
        // pages, not four.
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->count(60)->create();

        $this->assertSame(61, User::count(), 'The page count below depends on this.');

        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertSee('pager__list', false)
            // On page 1 of 5 the window is 1, 2, 4, 5 -- so the last page is
            // reachable and the middle one is not.
            ->assertSee($this->linkTo(5), false)
            ->assertDontSee($this->linkTo(3), false)
            ->assertSee('pager__gap', false);
    }
}
