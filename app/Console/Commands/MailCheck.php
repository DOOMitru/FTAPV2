<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Report the resolved mail configuration, and optionally prove it works.
 *
 * A test cannot do this job. Tests run in the testing environment against
 * testing config, so a suite that passes says nothing about what the deployed
 * app will do when it tries to send a verification link. This command checks
 * whatever it is actually pointed at, which is the thing that matters.
 *
 * It exists because two shipped features -- email verification and the admin
 * invite link -- are inert without a working mailer, and their failure mode is
 * silence: nothing errors, the mail simply never arrives.
 */
class MailCheck extends Command
{
    protected $signature = 'mail:check {--send= : Send a test message to this address}';

    protected $description = 'Report the mail configuration and flag anything that would silently fail';

    /** Stock Laravel values that mean "nobody configured this". */
    private const PLACEHOLDERS = ['hello@example.com', 'example@example.com', ''];

    /** Hosts that only resolve on the machine that sent the mail. */
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];

    public function handle(): int
    {
        $mailer = config('mail.default');
        $from = config('mail.from.address');
        $fromName = config('mail.from.name');
        $contact = config('mail.league_contact');

        $this->newLine();
        $this->line('  Mail configuration');
        $this->line('  ──────────────────');
        $this->line(sprintf('  %-22s %s', 'transport', $mailer));
        $this->line(sprintf('  %-22s %s', 'host', config('mail.mailers.smtp.host') ?? '—'));
        $this->line(sprintf('  %-22s %s', 'port', config('mail.mailers.smtp.port') ?? '—'));
        $this->line(sprintf('  %-22s %s <%s>', 'from', $fromName, $from));
        $this->line(sprintf('  %-22s %s', 'league contact', $contact));
        $this->line(sprintf('  %-22s %s', 'link base (APP_URL)', config('app.url')));
        $this->newLine();

        $problems = [];

        if ($mailer === 'log') {
            $problems[] = 'MAIL_MAILER is "log": mail is written to storage/logs and reaches nobody. '
                .'Email verification and the admin invite link are both inert.';
        }

        if ($mailer === 'array') {
            $problems[] = 'MAIL_MAILER is "array": mail is discarded entirely.';
        }

        if (in_array(strtolower((string) $from), self::PLACEHOLDERS, true)) {
            $problems[] = sprintf(
                'MAIL_FROM_ADDRESS is still the placeholder (%s). Mail sent from an address the '
                .'domain does not own fails SPF and lands in spam, which looks identical to mail '
                .'that was never sent.',
                $from
            );
        }

        if (in_array(strtolower((string) $contact), self::PLACEHOLDERS, true)) {
            $problems[] = sprintf(
                'LEAGUE_CONTACT_EMAIL is still the placeholder (%s). Contact-form submissions are '
                .'being delivered there, and the contact page displays it.',
                $contact
            );
        }

        // The one that survives a perfectly configured mailer. Every link this
        // application puts in an email -- verification, password reset, the
        // admin invite -- is built from APP_URL, so pointed at localhost the
        // mail is delivered, opened, clicked, and goes nowhere. That failure
        // looks like a working mailer right up until a player tries to use it.
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (in_array(strtolower((string) $host), self::LOCAL_HOSTS, true)) {
            $problems[] = sprintf(
                'APP_URL is %s, so every link in every message points at the machine that sent it. '
                .'Verification, password reset and the invite link all arrive unusable. Set it to '
                .'the address players actually visit.',
                config('app.url')
            );
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        if ($problems === []) {
            $this->components->info('Nothing here would silently fail.');
        }

        if ($address = $this->option('send')) {
            $this->newLine();
            $this->components->task(
                'Sending a test message to '.$address,
                function () use ($address) {
                    Mail::raw(
                        "This is a test message from ".config('app.name').".\n\n"
                        ."If you are reading it, the mailer is configured and delivering.",
                        fn ($message) => $message->to($address)->subject(config('app.name').' — mail check')
                    );

                    return true;
                }
            );

            if ($this->laravel['mail.manager']->getSymfonyTransport() instanceof \Symfony\Component\Mailer\Transport\NullTransport) {
                $this->components->warn('The transport discards messages, so nothing was actually delivered.');
            }
        }

        // Non-zero on a real problem, so a deploy script can gate on this.
        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
