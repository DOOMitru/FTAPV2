<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Rules\Recaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The reCAPTCHA gate on self-registration.
 *
 * The token the widget writes into the form proves nothing by itself -- a bot
 * can post any string -- so what is actually being tested here is that the
 * server asks Google, and refuses when Google says no.
 *
 * Http::fake() throughout: a test suite that reached Google would be slow,
 * flaky, and would fail on a laptop with no network.
 */
class RecaptchaRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function withKeys(): void
    {
        config([
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret' => 'test-secret',
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

    private function fakeGoogle(bool $success): void
    {
        Http::fake([
            'www.google.com/recaptcha/*' => Http::response(['success' => $success]),
        ]);
    }

    public function test_the_widget_is_drawn_only_when_the_league_has_keys(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertDontSee('g-recaptcha', false)
            ->assertDontSee('recaptcha/api.js', false);

        $this->withKeys();

        $this->get(route('register'))->assertOk()
            ->assertSee('class="g-recaptcha"', false)
            ->assertSee('data-sitekey="test-site-key"', false)
            ->assertSee('recaptcha/api.js', false);
    }

    public function test_the_secret_never_reaches_the_browser(): void
    {
        // The site key is public and the secret is not. They arrive together
        // in one config block, which is exactly the shape of mistake worth a
        // test of its own.
        $this->withKeys();

        $this->get(route('register'))->assertOk()->assertDontSee('test-secret', false);
    }

    public function test_a_registration_google_accepts_goes_through(): void
    {
        $this->withKeys();
        $this->fakeGoogle(true);

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-token']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'wanda@example.com']);
    }

    public function test_a_registration_google_refuses_does_not(): void
    {
        $this->withKeys();
        $this->fakeGoogle(false);

        $this->post(route('register'), $this->fields(['g-recaptcha-response' => 'a-forged-token']))
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->assertDatabaseMissing('users', ['email' => 'wanda@example.com']);
    }

    public function test_a_missing_token_is_refused_without_asking_google(): void
    {
        $this->withKeys();
        Http::fake();

        $this->post(route('register'), $this->fields())
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->assertDatabaseMissing('users', ['email' => 'wanda@example.com']);
        Http::assertNothingSent();
    }

    public function test_the_token_is_actually_checked_with_google(): void
    {
        // The assertion that makes the rest mean something: without it, a rule
        // that accepted any non-empty string would pass every test above.
        $this->withKeys();
        $this->fakeGoogle(true);

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

    public function test_the_widget_is_scaled_to_fit_a_phone(): void
    {
        // Google's widget is a fixed 304x78 iframe that responds to nothing:
        // no max-width, flex or grid track shrinks it. The register form is
        // 293px at 375 and 238px at 320, so unscaled it spills over the card
        // on one and off the page on the other.
        //
        // Nothing in a PHP suite can measure a transform. Measured in a
        // browser at 320, 336, 345, 360, 375, 384, 400 and 768 with a 304x78
        // stand-in: it fits inside the form at every one, the page never
        // scrolls, and the wrapper's height matches the scaled height exactly
        // so no empty band is left under it.
        $this->withKeys();

        $this->get(route('register'))->assertOk()->assertSee('class="recaptcha"', false);

        $css = file_get_contents(resource_path('css/5-public/_register.css'));

        $this->assertMatchesRegularExpression(
            '/\.recaptcha \{[^}]*height: calc\(78px \* var\(--recaptcha-scale\)\);/s',
            $css,
            'The wrapper must take the scaled height, or a shrunk widget strands 78px of nothing.'
        );

        foreach (['24rem', '22.5rem', '21rem'] as $breakpoint) {
            $this->assertStringContainsString(
                '@media (max-width: '.$breakpoint.')', $css,
                'The widget needs a step at '.$breakpoint.' to stay inside the form.'
            );
        }
    }

    public function test_configured_needs_both_keys(): void
    {
        $this->assertFalse(Recaptcha::configured());

        config(['services.recaptcha.site_key' => 'only-this']);
        $this->assertFalse(Recaptcha::configured(), 'A site key with no secret checks nothing.');

        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret' => 'only-this']);
        $this->assertFalse(Recaptcha::configured(), 'A secret with no site key draws no widget.');

        $this->withKeys();
        $this->assertTrue(Recaptcha::configured());
    }
}
