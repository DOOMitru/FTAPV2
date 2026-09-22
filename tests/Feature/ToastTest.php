<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The flash, pinned to the bottom of the viewport.
 *
 * A toast is an ALERT IN A DIFFERENT PLACE: the variant colours, the
 * live-region role and the emphasis markers all stay in the alert, and the
 * toast adds position, elevation and a way out. So most of what matters here is
 * that the alert underneath it is unchanged -- a toast that grew its own
 * colours or its own role would be a second definition of both.
 *
 * The Blade half is asserted by rendering it; the Vue half by reading it, since
 * PHPUnit cannot mount a component. What neither can see -- that it appears,
 * that it goes after five seconds, that a danger toast stays -- was measured in
 * a browser.
 */
class ToastTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $variant, string $message = 'Venue Casino Regina created.'): string
    {
        return Blade::render(
            '<x-toast :variant="$v">{{ $m }}</x-toast>',
            ['v' => $variant, 'm' => $message]
        );
    }

    private function vue(string $file): string
    {
        return (string) file_get_contents(resource_path('js/Components/'.$file));
    }

    public function test_a_toast_is_announced_as_a_live_region(): void
    {
        // Inherited from the alert rather than restated, which is the whole
        // point of nesting one: a message that slides in after the page has
        // settled is announced only if it is in a live region, and getting
        // that right twice is getting it wrong once.
        $this->assertStringContainsString('role="status"', $this->render('success'));
        $this->assertStringContainsString('role="alert"', $this->render('danger'));

        // The Vue half inherits it the same way, from the same component.
        $this->assertStringContainsString('<Alert', $this->vue('Toast.vue'));
        $this->assertStringContainsString(
            "variant === 'danger' ? 'alert' : 'status'",
            $this->vue('Alert.vue')
        );
    }

    public function test_a_toast_carries_the_alert_variant_it_was_given(): void
    {
        $this->assertStringContainsString('alert--success', $this->render('success'));
        $this->assertStringContainsString('alert--danger', $this->render('danger'));

        // And the toast class alongside, not instead: position and elevation
        // are additions to an alert, not a replacement for one.
        $this->assertStringContainsString('alert alert--success toast', $this->render('success'));
    }

    public function test_the_message_keeps_its_emphasis(): void
    {
        // emph() marks an entity name with U+2063 and emph_split turns the
        // marked run into <strong>. That has to survive being put in a toast,
        // because every CRUD confirmation in the app names the thing it is
        // about.
        $rendered = $this->render('success', 'Venue '.emph('Casino Regina').' created.');

        $this->assertStringContainsString('<strong class="emph">Casino Regina</strong>', $rendered);
        $this->assertStringNotContainsString(EMPH, $rendered,
            'A marker reached the page as a character.');
    }

    public function test_every_toast_offers_a_way_out(): void
    {
        $rendered = $this->render('success');

        $this->assertStringContainsString('toast__dismiss', $rendered);
        $this->assertStringContainsString('data-toast-dismiss', $rendered);

        // Named from content, not aria-label, so it is translated with the rest
        // of the page -- the rule Action.vue states and this follows.
        $this->assertStringContainsString('<span class="u-visually-hidden">Dismiss</span>', $rendered);

        $this->assertStringContainsString('toast__dismiss', $this->vue('Toast.vue'));
        $this->assertStringContainsString('u-visually-hidden', $this->vue('Toast.vue'));
    }

    public function test_a_refusal_waits_to_be_read_and_a_confirmation_does_not(): void
    {
        /*
         * The asymmetry is the point. "Has already started, so you can no
         * longer enter it" is the sentence somebody most needs time with, and
         * the one a timer is most likely to take away from a player who looked
         * down at the table. A confirmation has done its job on being read.
         */
        $this->assertStringContainsString('data-toast-persist', $this->render('danger'));
        $this->assertStringNotContainsString('data-toast-persist', $this->render('success'));

        // The Blade half asks the markup, not the variant class: what the
        // stylesheet calls a colour is not JavaScript's business.
        $script = (string) file_get_contents(resource_path('js/toast.ts'));

        $this->assertStringContainsString("querySelector('[data-toast-persist]') === null", $script);

        // Both halves wait the same five seconds. Two figures that must agree
        // and are written twice is how they stop agreeing, so each is asserted
        // against the same number.
        $this->assertStringContainsString('DISMISS_AFTER_MS = 5000', $script);
        $this->assertStringContainsString('DISMISS_AFTER_MS = 5000', $this->vue('Toast.vue'));

        $this->assertStringContainsString("props.variant !== 'danger'", $this->vue('Toast.vue'),
            'The Vue half no longer lets a refusal stand.');
    }

    public function test_the_public_shell_toasts_so_its_pages_need_not(): void
    {
        // Five views used to draw their own -- events twice, contact, about,
        // and login and forgot-password through x-auth-session-status, which
        // has no caller left. The shell draws it once for all of them.
        //
        // With a flash in the session, because the shell draws NOTHING without
        // one. The first version of this asked a page with an empty session
        // and failed for the right reason.
        $this->withSession(['status' => 'Saved.'])->get(route('events'))->assertOk()
            ->assertSee('data-toast', false);

        foreach (['events', 'contact', 'about/index', 'auth/login', 'auth/forgot-password'] as $view) {
            $this->assertStringNotContainsString(
                '<x-alert',
                (string) file_get_contents(resource_path('views/'.$view.'.blade.php')),
                $view.' draws its own alert again; the public shell is what shows the flash.'
            );
        }
    }

    public function test_a_flashed_message_reaches_the_public_shell_as_a_toast(): void
    {
        // End to end on the Blade half: the session value arrives inside the
        // toast the layout renders, which is the part a source assertion
        // cannot see.
        $response = $this->withSession(['status' => 'Thanks for getting in touch.'])
            ->get(route('events'))->assertOk();

        $response->assertSee('data-toast', false);
        $response->assertSee('Thanks for getting in touch.', false);
        $response->assertSee('alert alert--success toast', false);
    }

    public function test_a_page_inside_the_dashboard_shell_draws_no_toast_of_its_own(): void
    {
        // The Vue half's toasts are mounted by the shell, so nothing is in the
        // response. Asserted as the absence of a SECOND definition rather than
        // the absence of markup, which would pass for the wrong reason.
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('users.index'))->assertOk();

        foreach (glob(resource_path('js/Pages/**/*.vue')) ?: [] as $page) {
            $this->assertStringNotContainsString('<Toast', (string) file_get_contents($page),
                basename($page).' mounts a toast itself; FlashToasts in the shell is what does that.');
        }
    }
}
