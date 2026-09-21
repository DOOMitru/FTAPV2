<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel's stock pagination views are Tailwind-only. Registering the
        // design-system view as the default converts every paginated page in
        // the app -- the seven admin index pages and the public events list --
        // without touching a single call site.
        Paginator::defaultView('vendor.pagination.design-system');

        // defaultSimpleView is deliberately NOT set to the same view. That view
        // windows page numbers, so it asks the paginator for total() and
        // lastPage() -- neither of which exists on a simple paginator, which
        // knows only whether there is another page. Registering it here made
        // the first ever call to simplePaginate() a fatal error instead of a
        // page, and nothing in the app calls it, so the fault would have been
        // discovered by whoever first tried rather than by anyone who could
        // have prevented it.
        //
        // Left at Laravel's stock view: unstyled, but working. A design-system
        // previous/next view is easy enough to add the day something actually
        // needs one, and building it now is a view for a caller that does not
        // exist.

        $this->composeAuthenticationEmails();
    }

    /**
     * Put the league's voice into the two emails Laravel writes itself.
     *
     * Password reset and email verification are sent by the framework, so their
     * wording is Laravel's: "Whoops!", "This password reset link will expire in
     * 60 minutes", a button reading "Verify Email Address". Every other message
     * this app sends greets the player by name and signs off from the team, and
     * an account email that reads like a stock framework notice is the one a
     * player is most likely to distrust -- these two are exactly the pair a
     * phishing message imitates.
     *
     * toMailUsing is the supported hook and takes the whole message, which is
     * why this is here rather than in a subclass: overriding the notification
     * classes means also intercepting where the framework constructs them.
     */
    private function composeAuthenticationEmails(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Reset your '.config('app.name').' password')
                ->greeting('Hi '.$notifiable->first_name.',')
                ->line('Somebody asked to reset the password for this email address.')
                ->action('Choose a new password', $url)
                // Whole units, as in the invitation. "Expires in 10080 minutes"
                // is true and useless.
                ->line('This link works for '.self::window().'.')
                // Said plainly, and without alarm. The common case for an
                // unexpected reset email is a mistyped address, not an attack.
                ->line('If you did not ask for this, you can ignore this email — your password stays as it is.')
                ->salutation('See you at the tables,');
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Confirm your email address')
                ->greeting('Hi '.$notifiable->first_name.',')
                ->line(
                    'Please confirm this is your email address. It is how the league reaches you about '
                    .'tournaments, and you will need it confirmed before you can sign in.'
                )
                ->action('Confirm my email address', $url)
                ->line('If you did not sign up at '.config('app.name').', you can ignore this email.')
                ->salutation('See you at the tables,');
        });
    }

    /** How long a signed link lasts, in words a reader recognises. */
    private static function window(): string
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        // One instant, used twice -- see PlayerInvitation. Two calls to now()
        // differ by microseconds and diffForHumans floors the result.
        $now = Carbon::now();

        return $now->copy()->addMinutes($minutes)->diffForHumans(
            $now, \Carbon\CarbonInterface::DIFF_ABSOLUTE
        );
    }
}
