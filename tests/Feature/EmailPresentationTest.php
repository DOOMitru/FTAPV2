<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PlayerApproved;
use App\Notifications\PlayerInvitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What every message from the league looks like and sounds like.
 *
 * Four emails reach players -- two the app writes and two the framework does --
 * and until now the framework's two arrived in Laravel's voice: "Whoops!", a
 * button reading "Verify Email Address", signed "Regards, First to Act Poker".
 * A player cannot tell a genuine account email from a forged one by inspecting
 * headers; they tell by whether it looks and sounds like the last one. So the
 * masthead and the sign-off are held here for ALL FOUR together, which is the
 * only way a fifth notification added later is caught looking like neither.
 */
class EmailPresentationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: \Closure}> */
    public static function emails(): array
    {
        return [
            'invitation' => [fn (User $u) => (new PlayerInvitation('token'))->toMail($u)],
            'approved' => [fn (User $u) => (new PlayerApproved)->toMail($u)],
            'password reset' => [fn (User $u) => (new ResetPassword('token'))->toMail($u)],
            'email verification' => [fn (User $u) => (new VerifyEmail)->toMail($u)],
        ];
    }

    private function player(): User
    {
        return User::factory()->create(['first_name' => 'Dumitru', 'email_verified_at' => now()]);
    }

    #[DataProvider('emails')]
    public function test_every_email_carries_the_league_masthead(callable $make): void
    {
        $html = (string) $make($this->player())->render();

        $this->assertStringContainsString('First to Act', $html);
        $this->assertStringContainsString('Play hard. Play smart. Be first to act.', $html);
        $this->assertStringContainsString('hero_logo.png', $html);
    }

    #[DataProvider('emails')]
    public function test_every_email_is_signed_by_the_team(callable $make): void
    {
        $html = (string) $make($this->player())->render();

        $this->assertStringContainsString('The First to Act Team', $html);

        // And not by the framework's default, which signs with the app name and
        // reads as a machine talking.
        $this->assertStringNotContainsString(">Regards,<br>\nFirst to Act Poker", $html);
    }

    #[DataProvider('emails')]
    public function test_every_email_greets_the_player_by_name(callable $make): void
    {
        $html = (string) $make($this->player())->render();

        $this->assertStringContainsString('Hi Dumitru,', $html);

        // Laravel's stock greeting for a message with no level set.
        $this->assertStringNotContainsString('Hello!', $html);
        $this->assertStringNotContainsString('Whoops!', $html);
    }

    #[DataProvider('emails')]
    public function test_every_email_offers_exactly_one_action(callable $make): void
    {
        // A message with two buttons has no primary action, and one with none
        // asks the reader to go and find the site themselves.
        $message = $make($this->player());

        $this->assertNotNull($message->actionText, 'No call to action.');
        $this->assertSame(
            1,
            substr_count((string) $message->render(), 'class="button button-primary"'),
            'More than one primary button.'
        );
    }

    public function test_the_framework_emails_no_longer_use_laravels_wording(): void
    {
        $player = $this->player();

        $reset = (string) (new ResetPassword('token'))->toMail($player)->render();
        $verify = (string) (new VerifyEmail)->toMail($player)->render();

        // The exact sentences Laravel ships. Each was replaced for a reason:
        // "we received a password reset request" is the framework's, and
        // "Verify Email Address" is a label rather than something a person says.
        $this->assertStringNotContainsString('we received a password reset request', $reset);
        $this->assertStringNotContainsString('Reset Password</a>', $reset);
        $this->assertStringNotContainsString('Verify Email Address', $verify);

        $this->assertStringContainsString('Choose a new password', $reset);
        $this->assertStringContainsString('Confirm my email address', $verify);
    }

    public function test_a_link_window_is_stated_in_units_a_reader_recognises(): void
    {
        // Two faults in one assertion. "10080 minutes" is the raw config value,
        // and "59 minutes" is what diffForHumans returns when now() is called
        // twice -- which is what this said before, for a link lasting an hour.
        config(['auth.passwords.users.expire' => 60]);

        $html = (string) (new PlayerInvitation('token'))->toMail($this->player())->render();

        $this->assertStringContainsString('This link works for 1 hour.', $html);
    }

    public function test_a_week_long_window_reads_as_a_week(): void
    {
        // The value the mass invite actually runs with.
        config(['auth.passwords.users.expire' => 60 * 24 * 7]);

        $html = (string) (new PlayerInvitation('token'))->toMail($this->player())->render();

        $this->assertStringContainsString('This link works for 1 week.', $html);
    }

    public function test_the_footer_names_the_league_rather_than_reserving_rights(): void
    {
        $html = (string) (new PlayerInvitation('token'))->toMail($this->player())->render();

        $this->assertStringContainsString('First to Act Poker League', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
    }

    public function test_the_plain_text_part_carries_the_masthead_too(): void
    {
        // The half that spam filters read, and that a text-only client shows.
        // It used to be the bare word "First to Act Poker: https://..." where
        // the masthead was.
        $message = (new PlayerInvitation('token'))->toMail($this->player());

        $text = (string) app(\Illuminate\Mail\Markdown::class)
            ->renderText('notifications::email', $message->data());

        $this->assertStringContainsString('FIRST TO ACT POKER LEAGUE', $text);
        $this->assertStringContainsString('Play hard. Play smart. Be first to act.', $text);
        $this->assertStringContainsString('The First to Act Team', $text);
    }

    public function test_a_notification_without_its_own_sign_off_is_still_signed(): void
    {
        // The signature must not depend on each notification remembering to ask
        // for one -- that is precisely how the framework's two ended up signed
        // "Regards, First to Act Poker" while the app's two were not.
        $message = (new MailMessage)->greeting('Hi Dumitru,')->line('A line.');

        $this->assertStringContainsString('The First to Act Team', (string) $message->render());
    }
}
