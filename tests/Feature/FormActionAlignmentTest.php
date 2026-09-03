<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Two rules about the row of buttons at the foot of a form.
 *
 * It sits at the END of its line, matching the row actions in every dashboard
 * table and the modal footer -- the edit forms were the one place that had not
 * adopted that, and an exception nobody chose is drift rather than a decision.
 *
 * And the secondary action -- Cancel, or Back where the form returns to
 * itself -- comes BEFORE the primary one, so the button furthest right
 * -- the one a thumb reaches first and a cursor lands on last -- is the one
 * that does the thing. Right-aligning the row without reordering it put Cancel
 * in that spot, which is the opposite of what a form wants.
 *
 * Checked in the source rather than the rendered page: justify-content is a
 * stylesheet, order is markup, and this project has no browser tests. The
 * failure being guarded against is a new form started by copying an older one.
 */
class FormActionAlignmentTest extends TestCase
{
    /** A cluster and its contents. None of these rows nests a <div>. */
    private const CLUSTER = '/<div class="(l-cluster[^"]*)">(.*?)<\/div>/s';

    /** The dashboard only. The public side is a separate register. */
    private const PUBLIC_LAYOUTS = ['<x-public-layout', '<x-guest-layout'];

    public function test_form_action_rows_are_end_aligned_with_cancel_first(): void
    {
        $unaligned = [];
        $misordered = [];
        $rows = 0;
        $withCancel = 0;
        $root = resource_path('views').DIRECTORY_SEPARATOR;

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            $content = file_get_contents($file->getRealPath());
            $name = str_replace($root, '', $file->getRealPath());

            foreach (self::PUBLIC_LAYOUTS as $layout) {
                if (str_contains($content, $layout)) {
                    continue 2;
                }
            }

            preg_match_all(self::CLUSTER, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as [[, $at], [$classes], [$body]]) {
                // A form's action row is one holding a primary button. Clusters
                // of badges, filters and standalone links are not that.
                if (! str_contains($body, 'variant="primary"')) {
                    continue;
                }

                // And it is the footer INSIDE a form, not a toolbar that
                // contains several. users/show draws a row of independent
                // actions -- approve, reject, resend -- each its own little
                // form. There is no Cancel there to order Approve against, and
                // nothing about that row is a form's Save and Cancel.
                $before = substr($content, 0, $at);

                if (substr_count($before, '<form') <= substr_count($before, '</form>')) {
                    continue;
                }

                $rows++;

                if (! str_contains($classes, 'l-cluster--end')) {
                    $unaligned[] = $name;
                }

                // By variant rather than by the word on it. The secondary
                // action is not always called Cancel -- the points-structure
                // form returns to itself after every save, so by the second
                // entry there is nothing left to cancel and the button says
                // Back. What the rule is about is which of the two comes
                // first, not what either is called.
                //
                // Not every row has one: the profile forms save in place and
                // have nowhere to go back to.
                $cancel = strpos($body, 'variant="ghost"');

                if ($cancel === false) {
                    continue;
                }

                $withCancel++;

                if ($cancel > strpos($body, 'variant="primary"')) {
                    $misordered[] = $name;
                }
            }
        }

        $this->assertSame([], $unaligned, implode("\n  ", array_merge(
            ['These form action rows are not end-aligned; add l-cluster--end:'],
            $unaligned,
        )));

        $this->assertSame([], $misordered, implode("\n  ", array_merge(
            ['In these rows the secondary action comes after the primary one; put it first:'],
            $misordered,
        )));

        // Without these the test passes on a repository whose forms have all
        // been renamed out from under the patterns above, which is not the
        // same as passing.
        $this->assertGreaterThanOrEqual(19, $rows, 'Far fewer action rows found than expected.');
        // Two forms below the count of action rows: points-structure create
        // and venue-points create both carry their Back in the page header
        // instead. Each returns to itself after every save, so leaving is not
        // one of the two choices its action row is offering.
        $this->assertGreaterThanOrEqual(15, $withCancel, 'Far fewer secondary actions found than expected.');
    }
}
