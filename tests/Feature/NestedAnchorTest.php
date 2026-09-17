<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\RendersEveryPage;
use Tests\TestCase;

/**
 * No page nests one link inside another.
 *
 * <a> may not contain <a>: it is interactive content, and the content model of
 * an anchor excludes it. Nothing complains. The server sends exactly what the
 * template says, every test passes, and the BROWSER is where it goes wrong --
 * on the nested start tag the parser acts as though it had seen </a>, closes
 * the outer link, and leaves the rest of what was inside it as a SIBLING.
 *
 * On the events page that turned each archive card into three: a truncated
 * card, a loose podium and a stray "Full results" foot, each landing in the
 * grid as its own cell. Forty-five cards became a hundred and thirty-five
 * items in four columns.
 *
 * It arrived when player names were linked throughout the app. The archive
 * card is a whole-card anchor, so a link on a name inside it was a link inside
 * a link -- and only when signed in, because a guest is shown "Sign in to see
 * the results" instead of the podium and so never nests anything.
 *
 * The CONSEQUENCE cannot be asserted here: libxml keeps the nesting as written,
 * so server-side the card still contains its podium and only a real parser
 * takes it apart. That half was verified in a browser -- 45 cards, 135 grid
 * children, heights of 244, 228 and 34px in a row -- and the nesting below is
 * what stands guard.
 */
class NestedAnchorTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase, RendersEveryPage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedEveryPage();
    }

    public function test_no_page_nests_a_link_inside_a_link(): void
    {
        $offenders = [];

        foreach ($this->everyPage() as $path => $html) {
            foreach ($this->dom($html)->query('//a//a') as $node) {
                $offenders[] = sprintf('%s — <a href="%s"> inside another link',
                    $path, $node->getAttribute('href'));
            }
        }

        $this->assertSame([], array_unique($offenders),
            "A link inside a link. The browser closes the outer one and spills\n"
            ."the rest of it into the parent as siblings:\n  "
            .implode("\n  ", array_unique($offenders))."\n");
    }
}
