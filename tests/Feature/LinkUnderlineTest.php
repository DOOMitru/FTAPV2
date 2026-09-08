<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Underlining is opt-in.
 *
 * The browser underlines every anchor, and in this system most anchors are not
 * prose links: they are rows, cards, menu items, icon buttons, pagination and a
 * logo. Each had to remember to switch the underline off, and two shipped
 * having forgotten -- the venue page drew ten linked rows as thirty underlined
 * fragments, and the tournament picker underlined every option's name and its
 * date. Both were found by eye, weeks apart, with a green suite either time.
 *
 * So the reset takes the underline away and .link puts it back. A component
 * that forgets now looks like the rest of the interface instead of like a
 * mistake, and prose that should read as a link says so.
 */
class LinkUnderlineTest extends TestCase
{
    private function css(string $file): string
    {
        return file_get_contents(resource_path('css/'.$file));
    }

    public function test_the_reset_takes_the_browser_underline_away(): void
    {
        $reset = $this->css('1-base/_reset.css');

        // The `a` rule specifically, not the word appearing anywhere in the file.
        preg_match('/^a \{(.*?)^\}/ms', $reset, $matches);

        $this->assertNotEmpty($matches, 'The reset no longer styles bare anchors.');
        $this->assertStringContainsString('text-decoration: none', $matches[1]);
    }

    public function test_link_is_the_way_back_in(): void
    {
        // Without this the reset silently strips every prose link in the app --
        // "create a new account", "Forgot password?", the footer contacts.
        preg_match('/^\.link \{(.*?)^\}/ms', $this->css('3-components/_link.css'), $matches);

        $this->assertNotEmpty($matches, '.link is gone, so nothing can opt in.');
        $this->assertStringContainsString('text-decoration: underline', $matches[1]);
    }

    public function test_prose_links_still_carry_the_class(): void
    {
        // The reset only holds if the places that want an underline ask for it.
        $login = file_get_contents(resource_path('views/auth/login.blade.php'));

        $this->assertStringContainsString('class="link', $login);
    }
}
