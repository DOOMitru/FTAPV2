<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The application's own confirmation dialog.
 *
 * What it does when opened is a browser behaviour and this project has no
 * browser tests, so what is checked here is the contract between the markup and
 * confirm.ts: the dialog is present wherever a form might ask, there is exactly
 * one of it, and the two buttons report themselves the way the script reads
 * them. Those are the things that break silently -- a dialog missing from a
 * layout falls back to window.confirm and nobody notices until someone looks.
 */
class ConfirmDialogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    public function test_a_page_with_a_confirmable_form_also_carries_the_dialog(): void
    {
        PokerSeason::create([
            'name' => 'Season 42',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        $html = $this->actingAs($this->admin())->get(route('poker.seasons.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-confirm=', $html, 'Expected a form that asks first.');
        $this->assertStringContainsString('data-confirm-dialog', $html, 'The form asks, but nothing on the page can ask it.');
    }

    public function test_the_public_shell_carries_it_too(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('data-confirm-dialog', false);
    }

    public function test_there_is_exactly_one_dialog_on_a_page(): void
    {
        // It is addressed by attribute and labelled by a fixed id. Two of them
        // would give the script the first one and the screen reader an
        // ambiguous label.
        $html = $this->actingAs($this->admin())->get(route('users.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-confirm-dialog'));
        $this->assertSame(1, substr_count($html, 'id="confirm-dialog-message"'));
    }

    public function test_the_buttons_report_which_one_closed_it(): void
    {
        // method="dialog" plus a value on each submit button is the whole
        // mechanism: it puts the answer in returnValue with no handler of its
        // own. confirm.ts compares against "confirm" exactly.
        $html = $this->actingAs($this->admin())->get(route('users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<form method="dialog">', $html);
        $this->assertStringContainsString('value="confirm"', $html);
        $this->assertStringContainsString('value="cancel"', $html);
        $this->assertStringContainsString('data-confirm-accept', $html);
        $this->assertStringContainsString('data-confirm-message', $html);
    }

    public function test_the_message_survives_as_a_value_not_as_source(): void
    {
        // The reason data-confirm exists rather than an inline onsubmit. This
        // name would close a JS string literal and run what followed if it were
        // ever interpolated into one, so it has to come back out of the
        // document as text, intact, with no handler anywhere holding it.
        $name = "'); alert(1); //";

        PokerSeason::create([
            'name' => $name,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        $html = $this->actingAs($this->admin())->get(route('poker.seasons.index'))
            ->assertOk()->getContent();

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        $messages = [];

        foreach ((new \DOMXPath($dom))->query('//form[@data-confirm]') as $form) {
            $messages[] = $form->getAttribute('data-confirm');
        }

        $this->assertContains(
            "Delete {$name}? This cannot be undone.",
            $messages,
            'The name did not survive the round trip through the attribute.'
        );

        // And nowhere is it JS source. Not an inline handler, not a <script>.
        $this->assertStringNotContainsString('onsubmit', $html);
        $this->assertStringNotContainsString('onclick="', $html);
    }

    public function test_the_dialog_is_not_shown_until_it_is_asked_for(): void
    {
        // A <dialog> without the open attribute is display: none. Rendering it
        // open would put a stray confirmation on every page.
        $html = $this->actingAs($this->admin())->get(route('users.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<dialog class="confirm" open', $html);
        $this->assertStringContainsString('<dialog class="confirm"', $html);
    }
}
