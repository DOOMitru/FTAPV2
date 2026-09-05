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
