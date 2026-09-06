<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The command exists because a passing suite says nothing about deployed mail:
 * tests run against testing config. These tests therefore check the command's
 * JUDGEMENT -- that it recognises a configuration which would silently fail --
 * rather than checking the configuration itself.
 */
class MailCheckTest extends TestCase
{
    public function test_it_fails_when_mail_is_only_written_to_the_log(): void
    {
        config([
            'mail.default' => 'log',
            'mail.from.address' => 'league@firsttoact.com',
            'mail.league_contact' => 'league@firsttoact.com',
        ]);

        $this->artisan('mail:check')
            ->expectsOutputToContain('reaches nobody')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_the_from_address_is_still_the_placeholder(): void
    {
        // The nastiest case: mail sends, nothing errors, and it lands in spam
        // because the domain does not own the address it claims to be from.
        // Indistinguishable from mail that was never sent.
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'hello@example.com',
            'mail.league_contact' => 'league@firsttoact.com',
        ]);

        $this->artisan('mail:check')
            ->expectsOutputToContain('fails SPF')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_the_from_name_is_still_the_placeholder(): void
    {
        // The case this command missed. It reported "nothing here would
        // silently fail" for a production mailer whose messages arrived from
        // "Example", because it had only ever looked at the from ADDRESS -- and
        // the name is what a recipient reads first, before the subject.
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'league@firsttoact.com',
            'mail.from.name' => 'Example',
            'mail.league_contact' => 'league@firsttoact.com',
        ]);

        $this->artisan('mail:check')
            ->expectsOutputToContain('MAIL_FROM_NAME')
            ->assertExitCode(1);
    }

    public function test_it_passes_a_real_from_name(): void
    {
        // The other half. Without this the test above passes just as well
        // against a command that complains about every from-name there is.
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'league@firsttoact.com',
            'mail.from.name' => 'First to Act Poker',
            'mail.league_contact' => 'league@firsttoact.com',
            'app.url' => 'https://firsttoactpoker.com',
        ]);

        $this->artisan('mail:check')->assertExitCode(0);
    }

    public function test_the_from_name_falls_back_to_the_league_rather_than_to_laravels_placeholder(): void
    {
        // A server .env missing MAIL_FROM_NAME is exactly what happened, and
        // Laravel's stock config answers 'Example'. The fallback is checked by
        // evaluating the config file itself with the variable cleared, because
        // config('mail.from.name') only ever reports what THIS machine's .env
        // happens to say.
        $repository = \Illuminate\Support\Env::getRepository();
        $before = $repository->get('MAIL_FROM_NAME');
        $repository->clear('MAIL_FROM_NAME');

        try {
            $mail = require config_path('mail.php');

            $this->assertNotSame('Example', $mail['from']['name']);
            $this->assertSame(env('APP_NAME'), $mail['from']['name']);
        } finally {
            if ($before !== null) {
                $repository->set('MAIL_FROM_NAME', $before);
            }
        }
    }

    public function test_it_fails_when_the_league_contact_is_still_the_placeholder(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'league@firsttoact.com',
            'mail.league_contact' => 'hello@example.com',
        ]);

        $this->artisan('mail:check')
            ->expectsOutputToContain('LEAGUE_CONTACT_EMAIL')
            ->assertExitCode(1);
    }

    public function test_it_passes_on_a_configuration_that_would_actually_deliver(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'league@firsttoact.com',
            'mail.league_contact' => 'league@firsttoact.com',
            // Delivering is not enough on its own: a message whose links point
            // at localhost is delivered and still useless.
            'app.url' => 'https://firsttoact.com',
        ]);

        $this->artisan('mail:check')
            ->expectsOutputToContain('Nothing here would silently fail')
            ->assertExitCode(0);
    }

    public function test_it_fails_when_the_links_would_point_at_the_sending_machine(): void
    {
        // The failure a working mailer cannot save you from. Every link this
        // application puts in an email is built from APP_URL, so mail that
        // sends, arrives and opens perfectly still goes nowhere when clicked.
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'league@firsttoact.com',
            'mail.league_contact' => 'league@firsttoact.com',
            'app.url' => 'http://localhost',
        ]);

        $this->artisan('mail:check')
            ->expectsOutputToContain('every link in every message points at the machine that sent it')
            ->assertExitCode(1);
    }

    public function test_it_names_the_link_base_it_checked(): void
    {
        config(['app.url' => 'https://firsttoact.com']);

        $this->artisan('mail:check')->expectsOutputToContain('https://firsttoact.com');
    }

    public function test_a_loopback_address_counts_as_local_too(): void
    {
        // Same fault wearing a different hostname.
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'league@firsttoact.com',
            'mail.league_contact' => 'league@firsttoact.com',
            'app.url' => 'http://127.0.0.1:8000',
        ]);

        $this->artisan('mail:check')->assertExitCode(1);
    }
}
