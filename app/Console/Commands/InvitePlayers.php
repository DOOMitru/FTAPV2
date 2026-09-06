<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PlayerInvitation;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Tell players that an account exists for them, and let them set a password.
 *
 * The dashboard has a per-player invite button, which is right for one person
 * and useless for two hundred. This is the same operation at the scale the
 * league actually needs, with the three things that scale demands:
 *
 *   - a dry run, because the mistake costs an email to every player at once and
 *     cannot be recalled;
 *   - a limit, because a shared mail host caps what it will send in an hour and
 *     the failure is silent -- some arrive, some do not, and nothing says which;
 *   - a record, so the run that stops halfway can be continued rather than
 *     restarted. That record is users.invited_at.
 *
 * Sending is deliberately not queued. There is no queue worker on shared
 * hosting, and a job that sits in a table forever looks exactly like a job that
 * has been sent.
 */
class InvitePlayers extends Command
{
    use ConfirmableTrait;

    protected $signature = 'users:invite
        {--only= : Send to this one address, invited or not. For testing on yourself first}
        {--limit=0 : Send at most this many. 0 means everyone outstanding}
        {--sleep=2 : Seconds to wait between messages}
        {--again : Include players already invited, issuing them a fresh link}
        {--dry-run : List who would be written to and send nothing}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Email players a link to set their password';

    public function handle(): int
    {
        $recipients = $this->recipients();

        if ($recipients->isEmpty()) {
            $this->components->info('Nobody to invite. Every account has been invited already; --again re-sends.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $recipients = $recipients->take($limit);
        }

        $this->report($recipients);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->components->info('Dry run: nothing was sent.');

            return self::SUCCESS;
        }

        // Prompts in production, which is the only place this matters. --force
        // is for a deliberate, unattended run.
        if (! $this->confirmToProceed('Emailing '.$recipients->count().' player(s)')) {
            return self::FAILURE;
        }

        return $this->send($recipients);
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function recipients()
    {
        if ($address = $this->option('only')) {
            return User::where('email', $address)->get();
        }

        return User::query()
            // An invitation is a link to set a password. Sending one to somebody
            // who has already set theirs tells them their account may have been
            // tampered with.
            ->when(! $this->option('again'), fn ($query) => $query->whereNull('invited_at'))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /** @param \Illuminate\Support\Collection<int, User> $recipients */
    private function report($recipients): void
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        $this->newLine();
        $this->line(sprintf('  %-22s %s', 'recipients', $recipients->count()));
        $this->line(sprintf('  %-22s %s', 'from', config('mail.from.address')));
        $this->line(sprintf('  %-22s %s', 'links point at', config('app.url')));
        $this->line(sprintf('  %-22s %d minutes', 'links valid for', $minutes));
        $this->line(sprintf('  %-22s %ss between sends', 'pacing', $this->option('sleep')));
        $this->newLine();

        if ($minutes <= 60 && $recipients->count() > 5) {
            $this->components->warn(
                'Links expire in '.$minutes.' minutes. Most people will read this later than that and will '
                .'have to use "Forgot your password?" instead. Set AUTH_PASSWORD_RESET_EXPIRE higher for the '
                .'invite period if you would rather they did not.'
            );
        }

        if ($recipients->count() <= 25) {
            foreach ($recipients as $user) {
                $this->line('  '.$user->first_name.' '.$user->last_name.'  <'.$user->email.'>');
            }
        }
    }

    /** @param \Illuminate\Support\Collection<int, User> $recipients */
    private function send($recipients): int
    {
        $sent = 0;
        $failed = [];
        $sleep = max(0, (int) $this->option('sleep'));

        $bar = $this->output->createProgressBar($recipients->count());
        $bar->start();

        foreach ($recipients as $user) {
            try {
                // createToken() replaces any token this user already has, so an
                // earlier invitation's link stops working the moment a new one
                // is issued. That is why --again is opt-in.
                $user->notify(new PlayerInvitation(Password::createToken($user)));

                // Written only after the send returns. A crash mid-run leaves
                // this player outstanding, and the next run picks them up --
                // which is the right way round: a duplicate invitation is a
                // nuisance, a missing one is a player who never gets in.
                $user->forceFill(['invited_at' => now()])->save();

                $sent++;
            } catch (Throwable $e) {
                $failed[] = [$user->email, str($e->getMessage())->limit(60)->value()];
            }

            $bar->advance();

            if ($sleep > 0 && $user !== $recipients->last()) {
                sleep($sleep);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->components->info($sent.' invitation(s) sent.');

        if ($failed !== []) {
            $this->newLine();
            $this->components->error(count($failed).' failed. They remain outstanding and the next run will retry them.');
            $this->table(['Email', 'Why'], $failed);

            return self::FAILURE;
        }

        $outstanding = User::whereNull('invited_at')->count();

        if ($outstanding > 0) {
            $this->components->info($outstanding.' still outstanding. Run this again to continue.');
        }

        return self::SUCCESS;
    }
}
