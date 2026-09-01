<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The dashboard's horizontal gutter.
 *
 * layouts/app.blade.php used to wrap its slot in an .l-container. Thirty-two of
 * the thirty-three views that use it supply their own, so that outer one was
 * nested inside them -- and .l-container sets padding-inline, so the gutter was
 * applied twice: 32px a side on a phone, where the content has least to give.
 *
 * The layout's was removed, which is only safe while every view brings one.
 * That is what the first test here holds; the widths themselves are a media
 * query and this project has no browser-based tests.
 */
class DashboardGutterTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_dashboard_view_supplies_its_own_container(): void
    {
        $offenders = [];
        $root = resource_path('views').DIRECTORY_SEPARATOR;

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            $content = file_get_contents($file->getRealPath());

            if (! str_contains($content, '<x-app-layout')) {
                continue;
            }

            if (! str_contains($content, 'class="l-container')) {
                $offenders[] = str_replace($root, '', $file->getRealPath());
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['The app layout no longer adds one, so a view without its own has no gutter at all:'],
            $offenders,
        )));
    }

    public function test_the_layout_does_not_nest_a_second_container(): void
    {
        // Nesting is the fault, not the count anywhere on the page: the site
        // footer renders its own .l-container as a SIBLING, which is fine, so
        // this counts only what is inside <main>.
        //
        // Nor is it "main is followed by a container" -- now that the layout's
        // wrapper is gone the view's own container IS main's first child, which
        // is the correct state. Both of those were written and both were wrong;
        // two containers inside main is the actual fault.
        $admin = User::factory()->create(['is_admin' => true]);

        $html = $this->actingAs($admin)->get(route('users.index'))->assertOk()->getContent();

        preg_match('/<main[^>]*class="shell__content"[^>]*>(.*?)<\/main>/s', $html, $matches);

        $this->assertArrayHasKey(1, $matches, 'The dashboard shell did not render a <main>.');

        $this->assertSame(
            1,
            substr_count($matches[1], 'class="l-container'),
            'The layout must not wrap the slot in a container; every view brings its own.'
        );
    }
}
