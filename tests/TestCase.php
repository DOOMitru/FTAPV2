<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A message with its emphasis markers removed.
     *
     * Entity names in confirmations and flash messages are wrapped in U+2063 so
     * the renderer can bold them -- see emph() in app/Support/helpers.php. The
     * marker is invisible, so a test asserting the sentence a reader sees is
     * asserting something subtly different from the string in the attribute,
     * and the failure ("Delete Ada Lovelace? != Delete Ada Lovelace?") is
     * unreadable. Strip them and compare what is actually said.
     */
    protected function withoutEmphasis(?string $text): string
    {
        // Two forms, because a name is emphasised at two different moments. In
        // a data-confirm attribute it is still plain text carrying the markers,
        // and confirm.ts bolds it in the browser. In a rendered alert the
        // markers are already gone and <strong> is there instead. A test
        // asserting the sentence a reader sees has to undo whichever it meets.
        $text = preg_replace('#<strong class="emph">(.*?)</strong>#s', '$1', (string) $text);

        return str_replace(EMPH, '', (string) $text);
    }

    //
}
