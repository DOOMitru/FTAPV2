<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The deploy gate for reCAPTCHA.
 *
 * Its whole job is to make one silent failure loud: with no keys the
 * registration form fetches no token and applies no rule, and nothing on the
 * page or in a log says the protection is absent. A form that has quietly
 * stopped checking looks exactly like one that is checking and passing
 * everybody.
 *
 * So what matters here is the EXIT CODE -- the deploy gates on it -- and that
 * the secret never reaches the output, which on a deploy is a CI log.
 */
class RecaptchaCheckTest extends TestCase
{
    private function configure(?string $siteKey, ?string $secret, float $threshold = 0.5): void
    {
        config([
            'services.recaptcha.site_key' => $siteKey,
            'services.recaptcha.secret' => $secret,
            'services.recaptcha.threshold' => $threshold,
        ]);
    }

    public function test_it_fails_when_nothing_is_configured(): void
    {
        $this->configure(null, null);

        $this->artisan('recaptcha:check')
            ->expectsOutputToContain('Self-registration is open')
            ->assertExitCode(1);
    }

    public function test_it_fails_on_half_a_configuration(): void
    {
        // Either half alone is worse than neither: it looks configured.
        $this->configure('a-site-key', null);
        $this->artisan('recaptcha:check')->assertExitCode(1);

        $this->configure(null, 'a-secret');
        $this->artisan('recaptcha:check')->assertExitCode(1);
    }

    public function test_it_passes_when_both_keys_are_set(): void
    {
        $this->configure('a-site-key', 'a-secret');

        $this->artisan('recaptcha:check')
            ->expectsOutputToContain('Nothing here would silently fail.')
            ->assertExitCode(0);
    }

    public function test_the_secret_is_never_printed(): void
    {
        // This output goes to a CI log on every deploy.
        $this->configure('a-site-key', 'super-secret-value');

        $this->artisan('recaptcha:check')
            ->doesntExpectOutputToContain('super-secret-value')
            ->assertExitCode(0);
    }

    public function test_a_threshold_outside_the_scale_fails(): void
    {
        foreach ([-0.1, 1.5] as $nonsense) {
            $this->configure('a-site-key', 'a-secret', $nonsense);
            $this->artisan('recaptcha:check')->assertExitCode(1);
        }
    }

    public function test_a_threshold_of_zero_fails(): void
    {
        // Every score clears it, so the token is fetched and verified and then
        // always accepted: the expense without the benefit.
        $this->configure('a-site-key', 'a-secret', 0.0);

        $this->artisan('recaptcha:check')->assertExitCode(1);
    }

    public function test_a_steep_threshold_warns_but_does_not_fail(): void
    {
        // It is a judgement, not a mistake -- so it is said out loud and the
        // deploy still goes.
        $this->configure('a-site-key', 'a-secret', 0.95);

        $this->artisan('recaptcha:check')
            ->expectsOutputToContain('turned away')
            ->assertExitCode(0);
    }

    public function test_it_catches_a_stale_config_cache(): void
    {
        // The trap that costs nothing to fall into and says nothing when you
        // do: the deploy runs config:cache, and a cached config is read in
        // preference to .env. Editing .env on the server afterwards leaves the
        // keys set and the feature off -- which looks configured everywhere a
        // person would think to look.
        $envFile = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($envFile, "APP_ENV=production
RECAPTCHA_SITE_KEY=a-site-key
RECAPTCHA_SECRET=a-secret
");

        // What a stale cache looks like from inside the app: config empty,
        // .env full.
        $this->configure(null, null);

        $this->artisan('recaptcha:check --env-file='.$envFile)
            // One short phrase, not the whole sentence: the error component
            // hard-wraps to the terminal width, and a longer fragment gets
            // split across lines and silently stops matching.
            ->expectsOutputToContain('config cache is stale')
            ->assertExitCode(1);

        unlink($envFile);
    }

    public function test_an_env_file_that_agrees_with_the_config_is_not_flagged(): void
    {
        // The control: it must not cry stale whenever the keys are simply
        // absent from both, which is every developer's laptop.
        $envFile = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($envFile, "APP_ENV=local
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET=
");

        $this->configure(null, null);

        $this->artisan('recaptcha:check --env-file='.$envFile)
            ->doesntExpectOutputToContain('config cache is stale')
            ->assertExitCode(1);

        unlink($envFile);
    }

    public function test_the_probe_fails_on_a_secret_google_does_not_know(): void
    {
        $this->configure('a-site-key', 'a-wrong-secret');

        Http::fake(['www.google.com/recaptcha/*' => Http::response([
            'success' => false, 'error-codes' => ['invalid-input-secret'],
        ])]);

        $this->artisan('recaptcha:check --probe')->assertExitCode(1);
    }

    public function test_the_probe_passes_on_a_secret_google_knows(): void
    {
        // A deliberately invalid token: a working secret answers
        // invalid-input-RESPONSE, which is the point of sending one.
        $this->configure('a-site-key', 'a-good-secret');

        Http::fake(['www.google.com/recaptcha/*' => Http::response([
            'success' => false, 'error-codes' => ['invalid-input-response'],
        ])]);

        $this->artisan('recaptcha:check --probe')->assertExitCode(0);
    }

    public function test_an_unreachable_google_does_not_fail_the_probe(): void
    {
        // Opt-in and advisory: a Google outage must not fail a deploy that is
        // otherwise correctly configured.
        $this->configure('a-site-key', 'a-secret');

        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->artisan('recaptcha:check --probe')->assertExitCode(0);
    }

    public function test_the_probe_is_not_run_unless_asked(): void
    {
        $this->configure('a-site-key', 'a-secret');
        Http::fake();

        $this->artisan('recaptcha:check')->assertExitCode(0);

        Http::assertNothingSent();
    }
}
