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
