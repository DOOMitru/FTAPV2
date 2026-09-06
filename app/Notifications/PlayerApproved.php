<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once, when an administrator admits a player to the league.
 *
 * There is deliberately no counterpart for rejection. An automated refusal
 * invites a reply the league has no process to field, and a rejection is often
 * about a duplicate account or capacity rather than about the person; an
 * administrator who wants to explain can do it directly.
 */
class PlayerApproved extends Notification
{
    use Queueable;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("You're in - ".config('app.name'))
            ->greeting('Hi '.$notifiable->first_name.',')
            ->line('A league administrator has approved your account, so you can now register for tournaments.')
            ->action('See upcoming tournaments', route('events'))
            ->line('The games are free to play. Turn up, and someone will happily explain the rules at the table.')
            ->salutation('See you at the tables,');

        // Verification is a separate gate, and a player can be past one and not
        // the other. Saying "you're approved" to someone who still cannot reach
        // the dashboard would be true and useless, so name the remaining step.
        if (! $notifiable->hasVerifiedEmail()) {
            $message->line(
                'One thing left: confirm your email address using the link we sent when you registered. '
                .'You will need to do that before you can sign in to your dashboard.'
            );
        }

        return $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
