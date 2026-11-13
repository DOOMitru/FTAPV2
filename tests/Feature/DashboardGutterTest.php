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
        // Comments stripped first. The layout EXPLAINS at length what it does
        // with containers, so the words are in the file whatever the markup
        // does -- the same trap ComponentParityTest hit reading class names
        // out of prose.
        $layout = (string) preg_replace(
            '/<!--.*?-->/s',
            ' ',
            (string) file_get_contents(resource_path('js/Layouts/AppLayout.vue'))
        );

        /*
         * "No .l-container anywhere in the layout" was the rule until the
         * shell took ownership of the flash, which needs one to line up with
         * the page beneath it. That was always a stand-in for the real rule:
         * the SLOT must not sit inside a container. .l-container sets
         * padding-inline, and a page's own container nested inside the
         * layout's applied the gutter twice -- 32px a side on a phone, where
         * the content had least room to give.
         *
         * So the check is structural, and it survived the flash moving twice:
         * into a container inside <main> as the page's sibling, and out again
         * when the flash became a toast fixed to the viewport, which needs no
         * container at all. Either arrangement satisfies the real rule, which
         * is why the rule is the thing asserted.
         */
        $this->assertTrue($this->slotIsADirectChildOfMain($layout),
            'The layout wraps the slot in a container; every page brings its own, and two apply the gutter twice.');

        // And it brings none of its own, so one cannot creep back in unnoticed.
        $this->assertSame(0, substr_count($layout, 'class="l-container'),
            'The layout has grown a container.');

        // And the page must bring exactly one.
        $page = file_get_contents(resource_path('js/Pages/Users/Index.vue'));

        $this->assertSame(1, substr_count($page, 'class="l-container'),
            'A page brings one container, not none and not two.');
    }

    /**
     * Is <slot /> a direct child of <main>, or is something wrapping it?
     *
     * Walks the tags between the two and counts depth. A tag ending in "/>" is
     * self-closing and opens nothing, which covers the slot itself.
     *
     * Nothing sits between them at all now that the flash is a toast fixed to
     * the viewport. It did when the flash was a band inside <main>, and this
     * survived that arrangement arriving and leaving without being rewritten,
     * which is the argument for asking about structure rather than searching
     * for a string.
     */
    private function slotIsADirectChildOfMain(string $layout): bool
    {
        $from = strpos($layout, '<main');
        $to = strpos($layout, '<slot />', $from === false ? 0 : $from);

        if ($from === false || $to === false) {
            return false;
        }

        preg_match_all(
            '/<(\/?)([a-zA-Z][\w-]*)([^>]*)>/s',
            substr($layout, $from, $to - $from),
            $tags,
            PREG_SET_ORDER
        );

        $depth = 0;

        // Skipping <main> itself, so depth 0 means "inside main, nothing else".
        foreach (array_slice($tags, 1) as $tag) {
            if (str_ends_with(rtrim($tag[3]), '/')) {
                continue;
            }

            $depth += $tag[1] === '/' ? -1 : 1;
        }

        return $depth === 0;
    }
}
