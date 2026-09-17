<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Report the resolved reCAPTCHA configuration, and flag anything inert.
 *
 * The same argument as mail:check. A test cannot do this job: the suite runs
 * against testing config, so it says nothing about what the deployed app will
 * do. This reads whatever the server is actually pointed at.
 *
 * It exists because the failure mode is silence. With no keys the registration
 * form fetches no token and applies no rule -- deliberately, so local work and
 * the suite need no Google -- and nothing on the page or in a log says the
 * protection is absent. A form that has quietly stopped checking looks exactly
 * like one that is checking and passing everybody.
 */
class RecaptchaCheck extends Command
{
    protected $signature = 'recaptcha:check
        {--probe : Ask Google whether the secret is the right one}
        {--env-file= : Read the raw .env from here rather than the project root}';

    protected $description = 'Report the reCAPTCHA configuration and flag anything that would silently fail';

    /** Google's own suggested starting point, and the default in config. */
    private const SUGGESTED = 0.5;

    /** Above this, a real person is turned away often enough to notice. */
    private const STEEP = 0.9;

    public function handle(): int
    {
        $siteKey = (string) config('services.recaptcha.site_key');
        $secret = (string) config('services.recaptcha.secret');
        $threshold = (float) config('services.recaptcha.threshold');

        $this->newLine();
        $this->line('  reCAPTCHA configuration');
        $this->line('  ───────────────────────');
        // The site key is public -- it is printed into every register page --
        // so showing it helps. The secret is not, and is never printed here:
        // this output goes to a CI log.
        $this->line(sprintf('  %-22s %s', 'site key', $siteKey !== '' ? $siteKey : '—'));
        $this->line(sprintf('  %-22s %s', 'secret', $secret !== '' ? 'set ('.strlen($secret).' chars)' : '—'));
        $this->line(sprintf('  %-22s %s', 'threshold', $threshold));
        $this->line(sprintf('  %-22s %s', 'protecting', 'POST '.route('register', absolute: false)));
        $this->newLine();

        $problems = [];
        $warnings = [];

        // The trap that costs nothing to fall into: the deploy runs
        // config:cache, and a cached config is read in preference to .env. So
        // editing .env on the server without rebuilding the cache leaves the
        // keys set and the feature off, which is the worst of both -- it looks
        // configured everywhere a person would check.
        foreach (['RECAPTCHA_SITE_KEY' => $siteKey, 'RECAPTCHA_SECRET' => $secret] as $var => $resolved) {
            if ($resolved === '' && filled($this->fromEnvFile($var))) {
                $problems[] = sprintf(
                    '%s has a value in .env but not in the resolved config. The config cache is '
                    .'stale: run `php artisan config:cache` on this server. Until then the '
                    .'registration form checks nothing.',
                    $var
                );
            }
        }

        if ($siteKey === '' && $secret === '') {
            $problems[] = 'Neither RECAPTCHA_SITE_KEY nor RECAPTCHA_SECRET is set. Self-registration '
                .'is open: the form fetches no token and the rule is not applied.';
        } elseif ($siteKey === '') {
            $problems[] = 'RECAPTCHA_SECRET is set but RECAPTCHA_SITE_KEY is not. The form cannot '
                .'fetch a token, so nothing is checked -- see Recaptcha::configured(), which needs both.';
        } elseif ($secret === '') {
            $problems[] = 'RECAPTCHA_SITE_KEY is set but RECAPTCHA_SECRET is not. There is nothing '
                .'to verify the token against, so nothing is checked.';
        }

        if ($threshold < 0.0 || $threshold > 1.0) {
            $problems[] = sprintf(
                'RECAPTCHA_THRESHOLD is %s. Google scores from 0.0 to 1.0, so this either refuses '
                .'everybody or refuses nobody.',
                $threshold
            );
        } elseif ($threshold === 0.0) {
            $problems[] = 'RECAPTCHA_THRESHOLD is 0, which every score clears. The token is fetched '
                .'and verified and then always accepted, which is the expense of the check without '
                .'the benefit.';
        } elseif ($threshold >= self::STEEP) {
            $warnings[] = sprintf(
                'RECAPTCHA_THRESHOLD is %s. v3 gives a refused person nothing to click, so a real '
                .'signup scored below this is simply turned away. %s is Google\'s suggested start.',
                $threshold,
                self::SUGGESTED
            );
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }

        if ($problems === [] && $warnings === []) {
            $this->components->info('Nothing here would silently fail.');
        }

        if ($this->option('probe') && $siteKey !== '' && $secret !== '') {
            $problems = array_merge($problems, $this->probe());
        }

        // Non-zero on a real problem, so a deploy script can gate on this.
        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Ask Google whether the SECRET is right, without a real token.
     *
     * Posting a deliberately invalid response separates the two failures that
     * otherwise look identical from here: a wrong secret answers
     * invalid-input-secret, a right one answers invalid-input-response. That
     * is the only way to tell a working secret from a typo without asking
     * somebody to sign up.
     *
     * Opt-in, like mail:check's --send, so the default gate needs no network
     * and a Google outage cannot fail a deploy.
     *
     * @return array<int, string>
     */
    private function probe(): array
    {
        $this->newLine();

        try {
            $response = Http::asForm()->timeout(10)->post(
                'https://www.google.com/recaptcha/api/siteverify',
                ['secret' => config('services.recaptcha.secret'), 'response' => 'recaptcha-check-probe']
            );
        } catch (ConnectionException $e) {
            $this->components->warn('Could not reach Google to check the secret: '.$e->getMessage());

            return [];
        }

        $codes = (array) $response->json('error-codes', []);

        if (in_array('invalid-input-secret', $codes, true)) {
            return ['Google does not recognise RECAPTCHA_SECRET. Every registration will be refused '
                .'with "we could not check that just now".'];
        }

        if (in_array('invalid-input-response', $codes, true)) {
            $this->components->info('Google recognises the secret. (The probe token was rejected, which is the point.)');

            return [];
        }

        $this->components->warn('Unexpected answer from Google: '.json_encode($codes));

        return [];
    }

    /**
     * Read one variable straight from the .env file on disk.
     *
     * Needed because a cached config means Laravel never loads .env at all, so
     * env() answers null there too -- and the whole point of this check is to
     * notice when the file and the cache disagree.
     */
    private function fromEnvFile(string $variable): ?string
    {
        $path = $this->option('env-file') ?: base_path('.env');

        if (! is_readable($path)) {
            return null;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (! str_starts_with(trim($line), $variable.'=')) {
                continue;
            }

            return trim(explode('=', $line, 2)[1], " \t\"'");
        }

        return null;
    }
}
