<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_resending_says_so_in_words_a_reader_can_read(): void
    {
        // This flash used to be the sentinel 'verification-link-sent', which
        // two screens matched on while each kept its own copy of the sentence.
        // It is the sentence itself now, so what reaches the page is what the
        // page shows -- and this asserts the words, not a flag.
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status', 'A new verification link has been sent to your email address.');

        $page = $this->actingAs($user)->get('/verify-email')->assertOk()->viewData('page');

        $this->assertSame(
            'A new verification link has been sent to your email address.',
            $page['props']['flash']['status']
        );

        // Nothing sentinel-shaped survives anywhere a reader could meet it.
        $source = $this->verifyEmailMarkup();

        $this->assertStringNotContainsString('verification-link-sent', $source);
        $this->assertStringNotContainsString('link_sent', $source);

        // And the page keeps no second copy of the wording, which is how the
        // two screens used to disagree about it. It draws nothing at all now:
        // GuestLayout toasts the flash, as AppLayout does on the other half.
        $this->assertStringNotContainsString('Alert', $source,
            'The prompt draws its own alert again; the shell is what shows the flash.');

        $this->assertStringContainsString(
            '<FlashToasts',
            (string) file_get_contents(resource_path('js/Layouts/GuestLayout.vue')),
            'The shell this page wears shows no flash.'
        );
    }

    /**
     * The page with its prose removed.
     *
     * A comment naming the sentinel is not the sentinel, and this file's own
     * comment explains at length which one it replaced -- so the first version
     * of the assertion above failed against the explanation of why it passes.
     * That trap has now caught this suite seven times; strip first, assert
     * second.
     */
    private function verifyEmailMarkup(): string
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Auth/VerifyEmail.vue'));

        $source = (string) preg_replace('/<!--.*?-->/s', ' ', $source);
        $source = (string) preg_replace('#/\*.*?\*/#s', ' ', $source);

        // Anchored, so a protocol-relative URL keeps its slashes.
        return (string) preg_replace('#^\s*//.*$#m', ' ', $source);
    }

    public function test_the_prompt_says_nothing_until_a_link_is_sent(): void
    {
        // The other half: an alert that is always there says nothing.
        $user = User::factory()->unverified()->create();

        $this->assertNull(
            $this->actingAs($user)->get('/verify-email')->assertOk()
                ->viewData('page')['props']['flash']['status']
        );

        // And the toast is gated on it. Without this the flash could be empty
        // while the page showed the confirmation to everybody, which is what
        // removing the v-if does and what nothing here noticed. The gate moved
        // to the shared component with the alert itself.
        $this->assertStringContainsString(
            'v-if="flash.status"',
            (string) file_get_contents(resource_path('js/Components/FlashToasts.vue'))
        );
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * The three tests above pass whether or not verification is enforced: they
     * call the verification routes directly with signed URLs. Nothing exercised
     * the GATE -- the `verified` middleware on the dashboard route -- which was
     * inert for as long as User did not implement MustVerifyEmail. These two
     * cover the rule itself, so switching it back off fails the suite.
     */
    public function test_an_unverified_user_cannot_reach_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_a_verified_user_reaches_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->hasVerifiedEmail(), 'The factory should produce a verified user by default.');

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_registering_dispatches_the_verification_notification(): void
    {
        Notification::fake();

        $this->post('/register', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'ada@example.com')->first();

        $this->assertNotNull($user, 'Registration should have created the account.');
        $this->assertFalse($user->hasVerifiedEmail(), 'A new registration starts unverified.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
