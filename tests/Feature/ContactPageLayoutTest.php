<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The contact page is an introduction followed by a form, one column.
 *
 * It used to be a two-column split with a list of ways to reach the league --
 * an email address and a Facebook page -- beside the form. Both are gone for
 * now, so the page is the heading and the form, each across the full width.
 */
class ContactPageLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->get(route('contact'))->assertOk()->getContent();
    }

    public function test_the_removed_links_are_gone(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('Facebook', $html);
        $this->assertStringNotContainsString('Email Us', $html);
        $this->assertStringNotContainsString('mailto:', $html);
    }

    public function test_the_intro_comes_before_the_form(): void
    {
        $html = $this->page();

        $hero = strpos($html, 'Get in touch');
        $form = strpos($html, 'Send us a message');

        $this->assertNotFalse($hero, 'The page lost its heading.');
        $this->assertNotFalse($form, 'The page lost its form.');
        $this->assertLessThan($form, $hero, 'The form is above the introduction.');
    }

    public function test_neither_is_penned_into_a_column(): void
    {
        // .p-split is the two-column layout. Full width means not being in it.
        $this->assertStringNotContainsString('p-split', $this->page());
    }

    public function test_the_form_still_works(): void
    {
        // The point of the page. Rearranging it must not cost the one thing it
        // is for.
        $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'topic' => 'general',
            'message' => 'Is there a table on Wednesday?',
        ])->assertSessionHasNoErrors();
    }

    public function test_the_send_button_is_sized_by_its_row(): void
    {
        // btn--block pinned the button to 100% at every width. The row decides
        // now: full width on a phone, and at 40rem it shrinks to its label and
        // moves to the end. The widths themselves are a media query, which this
        // project has no way to assert -- what is checked is that the button is
        // no longer carrying its own.
        $html = $this->page();

        $this->assertStringContainsString('p-form-actions', $html);
        $this->assertStringNotContainsString('btn--block', $html);
    }
}
