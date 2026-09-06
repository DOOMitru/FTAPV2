<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent to a player whose account the league created for them.
 *
 * NOT Laravel's stock password-reset notification, which opens "we received a
 * password reset request for your account". Nobody here requested anything --
 * their account was made from the league's own list -- and an email that opens
 * with a false statement about a request is the shape of a phishing message.
 * Several hundred people receiving one at the same moment is how a domain earns
 * a reputation.
 *
 * So it says what actually happened, and it says what to do when the link has
 * expired, because with a mass send most people will read this tomorrow.
 */
class PlayerInvitation extends Notification
{
    use Queueable;

    public function __construct(private string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        // Said in whole units the reader recognises. "Expires in 10080 minutes"
        // is technically true and useless.
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        // ONE reference instant, used twice. Carbon::now() called twice returns
        // two times a few microseconds apart, and diffForHumans floors -- which
        // reported a 60 minute window as "59 minutes", and would have told 205
        // players that a 7 day link lasted 6 days.
        $now = Carbon::now();
        $window = $now->copy()->addMinutes($minutes)->diffForHumans(
            $now, \Carbon\CarbonInterface::DIFF_ABSOLUTE
        );

        return (new MailMessage)
            ->subject('Your '.config('app.name').' account')
            ->greeting('Hi '.$notifiable->first_name.',')
            ->line(
                'We have set up an account for you on the '.config('app.name')
                .' website, so you can see the schedule, register for tournaments and follow the season standings.'
            )
            ->line('Choose a password to finish setting it up.')
            ->action('Set your password', $url)
            ->line('This link works for '.$window.'.')
            ->line(
                'If it has expired by the time you get to it, no problem — go to the site, choose '
                .'"Forgot your password?", and enter this email address to get a fresh one.'
            )
            ->salutation('See you at the tables,');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
