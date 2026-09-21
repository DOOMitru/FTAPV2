<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The dashboard's horizontal gutter.
 *
 * The app layout used to wrap its slot in an .l-container. Nearly every page
 * that uses it supplies its own, so that outer one was nested inside them --
 * and .l-container sets padding-inline, so the gutter was applied twice: 32px
 * a side on a phone, where the content has least to give.
 *
 * The layout's was removed, which is only safe while every page brings one.
 * That is what the first test here holds; the widths themselves are a media
 * query and this project has no browser-based tests.
 *
 * Both tests read the Vue layout and the Vue pages. The Blade shell they were
 * written against is deleted -- and the version of this file that scanned for
 * `<x-app-layout` survived that deletion by matching nothing at all, which is
 * indistinguishable from a pass.
 */
class DashboardGutterTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_using_the_layout_supplies_its_own_container(): void
    {
        $offenders = [];
        $root = resource_path('js').DIRECTORY_SEPARATOR;
        $found = 0;

        foreach (Finder::create()->files()->in(resource_path('js/Pages'))->name('*.vue') as $file) {
            $content = (string) file_get_contents($file->getRealPath());

            /*
             * The IMPORT, not the word.
             *
             * The first version of this matched any page whose source
             * contained "AppLayout", and Auth/VerifyEmail.vue -- which uses
             * GuestLayout -- was reported the moment a comment in it mentioned
             * AppLayout by name to explain why. Prose about a thing is not the
             * thing, which this suite has now learned seven times.
             */
            if (! str_contains($content, "import AppLayout from")) {
                continue;
            }

            $found++;

            if (! str_contains($content, 'class="l-container')) {
                $offenders[] = str_replace($root, '', $file->getRealPath());
            }
        }

        // The scan finding nothing looks exactly like every page passing. This
        // is the check the Blade version of this test did not have, and the
        // reason it kept reporting a gutter rule it had stopped enforcing.
        $this->assertGreaterThan(0, $found, 'No page was scanned; the layout name or the path is wrong.');

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['The layout adds no container, so a page without its own has no gutter at all:'],
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
        // wrapper is gone the page's own container IS main's first child,
        // which is the correct state. Both of those were written and both were
        // wrong; two containers inside main is the actual fault.
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('users.index'))->assertOk();

        // Both halves of the rule, each where it now lives.
        //
        // The layout must bring NO container of its own -- .l-container sets
        // padding-inline, so two of them applied the gutter twice: 32px a side
        // on a phone, where the content had least room to give.
        // Comments stripped first. The layout EXPLAINS at length why it has no
        // container, so the words are in the file while the class is not --
        // the same trap ComponentParityTest hit reading class names out of
        // prose.
        $layout = (string) preg_replace(
            '/<!--.*?-->/s',
            ' ',
            (string) file_get_contents(resource_path('js/Layouts/AppLayout.vue'))
        );

        $this->assertStringNotContainsString('l-container', $layout,
            'The layout must not wrap the slot in a container; every page brings its own.');

        // And the page must bring exactly one.
        $page = file_get_contents(resource_path('js/Pages/Users/Index.vue'));

        $this->assertSame(1, substr_count($page, 'class="l-container'),
            'A page brings one container, not none and not two.');
    }
}
