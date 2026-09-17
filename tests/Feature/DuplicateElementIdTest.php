<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\RendersEveryPage;
use Tests\TestCase;

/**
 * No page gives two elements the same id.
 *
 * An id is a document-wide name, and `for` resolves to the FIRST element
 * carrying it. So a duplicate does not fail loudly -- it quietly wires a label
 * to the wrong control, somewhere else on the page.
 *
 * That is what /profile did. It renders three forms, two of which have a field
 * named `password`, and <x-field> derived the id from the name: the Update
 * Password box and the delete-account confirmation both took id="password".
 * Clicking "Password" in the delete dialog focused the New Password box in the
 * form above it -- measured, label.control resolving across forms with
 * sameNode false. A screen reader announces the same association, and a
 * password manager keyed on id sees one field where there are two.
 *
 * The named error bags on that page exist for exactly this collision. The ids
 * had not caught up, which is the whole lesson: one form of a clash was
 * handled and the other was not, and only a check over every page finds that.
 */
class DuplicateElementIdTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase, RendersEveryPage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedEveryPage();
    }

    public function test_no_page_repeats_an_element_id(): void
    {
        $offenders = [];

        foreach ($this->everyPage() as $path => $html) {
            $seen = [];

            foreach ($this->dom($html)->query('//*[@id]') as $node) {
                $id = $node->getAttribute('id');

                if ($id === '') {
                    continue;
                }

                $seen[$id] = ($seen[$id] ?? 0) + 1;
            }

            foreach ($seen as $id => $count) {
                if ($count > 1) {
                    $offenders[] = sprintf('%s — id="%s" on %d elements', $path, $id, $count);
                }
            }
        }

        $this->assertSame([], $offenders,
            "An id names one element. A label's `for` takes the first match, so\n"
            ."a repeat points somewhere the reader is not looking:\n  "
            .implode("\n  ", $offenders)."\n");
    }
}
