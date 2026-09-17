<?php

namespace Tests\Feature\Auth;

use App\Rules\Recaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The reCAPTCHA v3 gate on self-registration.
 *
 * v3 asks the visitor for nothing and hands back a score. So there is no pass
 * or fail to read off a widget: what is tested here is that the server asks
 * Google what the token is worth, and refuses a token that is forged, spent on
 * another action, or scored below this league's line.
 *
 * Http::fake() throughout: a suite that reached Google would be slow, flaky,
 * and would fail on a laptop with no network.
 */
class RecaptchaRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function withKeys(float $threshold = 0.5): void
    {
        config([
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret' => 'test-secret',
            'services.recaptcha.threshold' => $threshold,
        ]);
    }

    /** @return array<string, string> */
    private function fields(array $extra = []): array
    {
        return array_merge([
            'first_name' => 'Wanda',
            'last_name' => 'Reeve',
            'email' => 'wanda@example.com',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ], $extra);
    }

    private function fakeGoogle(array $body): void
    {
        Http::fake(['www.google.com/recaptcha/*' => Http::response($body)]);
    }

    private function scored(float $score, string $action = 'register'): void
    {
        $this->fakeGoogle(['success' => true, 'score' => $score, 'action' => $action]);
    }

    public function test_the_form_asks_for_a_token_only_when_the_league_has_keys(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertDontSee('data-recaptcha', false)
            ->assertDontSee('recaptcha/api.js', false)
            ->assertDontSee('g-recaptcha-response', false);

        $this->withKeys();

        $this->get(route('register'))->assertOk()
            ->assertSee('data-recaptcha="test-site-key"', false)
            ->assertSee('data-recaptcha-action="register"', false)
            ->assertSee('name="g-recaptcha-response"', false)
            // ?render= is what makes the script v3 rather than a checkbox.
            ->assertSee('recaptcha/api.js?render=test-site-key', false);
    }

    public function test_there_is_no_widget_to_click(): void
    {
        // The v2 checkbox this replaced drew a 304x78 iframe. v3 draws nothing
        // but a badge, and the token field is hidden.
        $this->withKeys();

        $this->get(route('register'))->assertOk()
            ->assertDontSee('class="g-recaptcha"', false)
            ->assertSee('type="hidden" name="g-recaptcha-response"', false);
    }

    public function test_the_secret_never_reaches_the_browser(): void
    {
        // The site key is public and the secret is not. They arrive together
        // in one config block, which is exactly the shape of mistake worth a
        // test of its own.
        $this->withKeys();

        $this->get(route('register'))->assertOk()->assertDontSee('test-secret', false);
    }

    public function test_a_good_score_goes_through(): void
    {
        $this->withKeys(threshold: 0.5);
        $this->scored(0.9);

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-token']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'wanda@example.com']);
    }

    public function test_a_score_below_the_line_does_not(): void
    {
        $this->withKeys(threshold: 0.5);
        $this->scored(0.3);

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-token']))
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->assertDatabaseMissing('users', ['email' => 'wanda@example.com']);
    }

    public function test_the_line_is_the_configured_one(): void
    {
        // The same score, read against two thresholds. Without this a rule
        // with the number hard-coded passes both tests above.
        $this->withKeys(threshold: 0.9);
        $this->scored(0.7);

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-token']))
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->withKeys(threshold: 0.6);
        $this->scored(0.7);

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-token']))
            ->assertSessionHasNoErrors();
    }

    public function test_a_score_exactly_on_the_line_passes(): void
    {
        $this->withKeys(threshold: 0.5);
        $this->scored(0.5);

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-token']))
            ->assertSessionHasNoErrors();
    }

    public function test_a_token_minted_for_another_action_is_refused(): void
    {
        // Otherwise a token from any other form on the site is spendable here,
        // which is most of what makes a v3 token worth checking at all.
        $this->withKeys();
        $this->scored(0.9, action: 'contact');

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-token']))
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->assertDatabaseMissing('users', ['email' => 'wanda@example.com']);
    }

    public function test_a_token_google_rejects_is_refused(): void
    {
        $this->withKeys();
        $this->fakeGoogle(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'forged']))
            ->assertSessionHasErrors('g-recaptcha-response');
    }

    public function test_a_malformed_answer_with_no_score_is_refused(): void
    {
        // success with no score casts to 0.0 rather than passing on a loose
        // comparison against null.
        $this->withKeys();
        $this->fakeGoogle(['success' => true, 'action' => 'register']);

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-token']))
            ->assertSessionHasErrors('g-recaptcha-response');
    }

    public function test_an_empty_token_is_refused_without_asking_google(): void
    {
        // What recaptcha.ts submits when Google is blocked or hangs: the form
        // always goes, and the server says why rather than the button
        // appearing to do nothing.
        $this->withKeys();
        Http::fake();

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => '']))
            ->assertSessionHasErrors('g-recaptcha-response');

        Http::assertNothingSent();
    }

    public function test_the_token_is_actually_checked_with_google(): void
    {
        // The assertion that makes the rest mean something: without it, a rule
        // that accepted any non-empty string would pass most of the above.
        $this->withKeys();
        $this->scored(0.9);

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-token']));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
                && $request['secret'] === 'test-secret'
                && $request['response'] === 'a-token';
        });
    }

    public function test_an_unreachable_google_fails_closed(): void
    {
        // Nobody signs up for a few minutes, rather than anything signing up.
        $this->withKeys();
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-token']))
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->assertDatabaseMissing('users', ['email' => 'wanda@example.com']);
    }

    public function test_without_keys_registration_is_unchanged(): void
    {
        // What local work and this suite rely on. It is also the state
        // production must not be left in -- see Recaptcha::configured().
        Http::fake();

        $this->post(route('register'), $this->fields())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'wanda@example.com']);
        Http::assertNothingSent();
    }

    public function test_configured_needs_both_keys(): void
    {
        $this->assertFalse(Recaptcha::configured());

        config(['services.recaptcha.site_key' => 'only-this']);
        $this->assertFalse(Recaptcha::configured(), 'A site key with no secret checks nothing.');

        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret' => 'only-this']);
        $this->assertFalse(Recaptcha::configured(), 'A secret with no site key fetches no token.');

        $this->withKeys();
        $this->assertTrue(Recaptcha::configured());
    }
}
