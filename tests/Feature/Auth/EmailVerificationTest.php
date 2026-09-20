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

    public function test_resending_says_so_without_printing_the_sentinel(): void
    {
        // session('status') holds 'verification-link-sent' on this screen --
        // a SENTINEL a page matches on, not a message. Handing it to an alert
        // would print that word to the reader, which is why the page is sent a
        // boolean and keeps the sentence itself.
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))
            ->assertRedirect();

        $page = $this->actingAs($user)->get('/verify-email')->assertOk()->viewData('page');

        $this->assertTrue($page['props']['link_sent']);
        $this->assertNotContains('verification-link-sent', $page['props'],
            'The sentinel reached the page as a value it could render.');

        // The sentence is the component's, and the sentinel is nowhere in it.
        $source = (string) file_get_contents(resource_path('js/Pages/Auth/VerifyEmail.vue'));

        $this->assertStringContainsString(
            'A new verification link has been sent to the email address you provided',
            $source
        );

        $this->assertStringNotContainsString('{{ link_sent }}', $source);
    }

    public function test_the_prompt_says_nothing_until_a_link_is_sent(): void
    {
        // The other half: an alert that is always there says nothing.
        $user = User::factory()->unverified()->create();

        $this->assertFalse(
            $this->actingAs($user)->get('/verify-email')->assertOk()
                ->viewData('page')['props']['link_sent']
        );

        // And the alert is gated on that. Without this the prop could be
        // correct while the page showed the confirmation to everybody, which
        // is what removing the v-if does and what nothing here noticed.
        $this->assertStringContainsString(
            '<Alert v-if="link_sent"',
            (string) file_get_contents(resource_path('js/Pages/Auth/VerifyEmail.vue'))
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
