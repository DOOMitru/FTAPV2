<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Every Blade view opens and closes the same number of container tags.
 *
 * This exists because a single stray </div> in components/p-event.blade.php
 * closed the page's .l-container early, and every card below the event card on
 * the tournament details page escaped it and ran to the viewport edge. The
 * whole suite -- 282 tests at the time, including a route smoke sweep that
 * renders that page -- passed while it was broken. Nothing looks at markup
 * structure, so nothing objected.
 *
 * It is the second time this has happened, which is once more than a rule that
 * lives only in someone's memory deserves.
 *
 * Counting is enough: none of these tags is void, so an opener and a closer
 * must balance whatever order they appear in. Blade comments are stripped
 * first, because they discuss markup in prose -- one of them literally reads
 * "An <a> without an href is not a link".
 */
class MarkupBalanceTest extends TestCase
{
    /** Container tags. Void elements are excluded; they have no closer to count. */
    private const TAGS = [
        'div', 'form', 'article', 'section', 'nav', 'main', 'header', 'footer',
        'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
    ];

    public function test_every_view_balances_its_container_tags(): void
    {
        $offenders = [];
        $root = resource_path('views').DIRECTORY_SEPARATOR;
        $checked = 0;

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            $checked++;
            $relative = str_replace($root, '', $file->getRealPath());

            $body = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($file->getRealPath()));

            foreach (self::TAGS as $tag) {
                $open = preg_match_all('/<'.$tag.'[\s>]/', $body);
                $close = preg_match_all('#</'.$tag.'>#', $body);

                if ($open !== $close) {
                    $offenders[] = sprintf('%s — <%s> opened %d, closed %d', $relative, $tag, $open, $close);
                }
            }
        }

        // Guards the guard: a typo in the Finder glob would make this pass by
        // examining nothing at all.
        $this->assertGreaterThan(50, $checked, 'The view sweep found suspiciously few files.');

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['Unbalanced markup. A stray closer ends an ancestor early, and the page below it escapes its container:'],
            $offenders,
        )));
    }
}
