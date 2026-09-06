<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PlayerInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Emailing the league.
 *
 * The mistake this command can make costs an email to every player at once and
 * cannot be recalled, so its guards are the part worth testing: that a dry run
 * sends nothing, that nobody is written to twice by accident, and that a run
 * which stops halfway can be continued rather than restarted.
 */
class InvitePlayersTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $email, ?string $invited = null): User
    {
        return User::factory()->create([
            'email' => $email,
            'invited_at' => $invited,
            'approval_status' => 'approved',
        ]);
    }

    public function test_a_dry_run_sends_nothing_and_records_nothing(): void
    {
        Notification::fake();
        $player = $this->player('ada@example.test');

        $this->artisan('users:invite --dry-run')->assertExitCode(0);

        Notification::assertNothingSent();
        $this->assertNull($player->fresh()->invited_at);
    }

    public function test_it_invites_everyone_outstanding(): void
    {
        Notification::fake();
        $this->player('ada@example.test');
        $this->player('grace@example.test');

        $this->artisan('users:invite --sleep=0 --force')->assertExitCode(0);

        Notification::assertSentTimes(PlayerInvitation::class, 2);
    }

    public function test_it_records_who_it_reached(): void
    {
        Notification::fake();
        $player = $this->player('ada@example.test');

        $this->artisan('users:invite --sleep=0 --force');

        $this->assertNotNull($player->fresh()->invited_at);
    }

    public function test_it_skips_players_already_invited(): void
    {
        // The point of the record. A second run after a partial one must reach
        // the people the first did not, and nobody else.
        Notification::fake();
        $this->player('done@example.test', invited: now()->subDay()->toDateTimeString());
        $this->player('outstanding@example.test');

        $this->artisan('users:invite --sleep=0 --force')->assertExitCode(0);

        Notification::assertSentTimes(PlayerInvitation::class, 1);
        Notification::assertSentTo(User::where('email', 'outstanding@example.test')->first(), PlayerInvitation::class);
    }

    public function test_again_re_invites_everyone(): void
    {
        Notification::fake();
        $this->player('done@example.test', invited: now()->subDay()->toDateTimeString());

        $this->artisan('users:invite --again --sleep=0 --force')->assertExitCode(0);

        Notification::assertSentTimes(PlayerInvitation::class, 1);
    }

    public function test_only_targets_a_single_address(): void
    {
        // How you test the whole chain on yourself before mailing the league.
        Notification::fake();
        $me = $this->player('me@example.test');
        $this->player('everyone-else@example.test');

        $this->artisan('users:invite --only=me@example.test --sleep=0 --force')->assertExitCode(0);

        Notification::assertSentTimes(PlayerInvitation::class, 1);
        Notification::assertSentTo($me, PlayerInvitation::class);
    }

    public function test_only_reaches_someone_already_invited(): void
    {
        // Testing on yourself twice in a row has to work, or the rehearsal is
        // single-use.
        Notification::fake();
        $me = $this->player('me@example.test', invited: now()->subHour()->toDateTimeString());

        $this->artisan('users:invite --only=me@example.test --sleep=0 --force')->assertExitCode(0);

        Notification::assertSentTo($me, PlayerInvitation::class);
    }

    public function test_limit_caps_a_run(): void
    {
        // How you stay under a mail host's hourly ceiling.
        Notification::fake();

        foreach (range(1, 5) as $i) {
            $this->player("player{$i}@example.test");
        }

        $this->artisan('users:invite --limit=2 --sleep=0 --force')->assertExitCode(0);

        Notification::assertSentTimes(PlayerInvitation::class, 2);
        $this->assertSame(3, User::whereNull('invited_at')->count());
    }

    public function test_nothing_to_do_is_not_a_failure(): void
    {
        Notification::fake();
        $this->player('done@example.test', invited: now()->toDateTimeString());

        $this->artisan('users:invite --sleep=0 --force')
            ->expectsOutputToContain('Nobody to invite')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_the_message_does_not_claim_the_player_asked_for_it(): void
    {
        // Laravel's stock reset notification opens "we received a password reset
        // request for your account". Nobody here requested anything, and a mass
        // email that opens with a false claim about a request is the shape of a
        // phishing message.
        $player = $this->player('ada@example.test');
        $mail = (new PlayerInvitation('test-token'))->toMail($player);
        $body = implode(' ', array_merge($mail->introLines, $mail->outroLines));

        $this->assertStringNotContainsStringIgnoringCase('password reset request', $body);
        $this->assertStringContainsString('We have set up an account for you', $body);
        $this->assertStringContainsString('Forgot your password?', $body);
        $this->assertSame('Set your password', $mail->actionText);
        $this->assertStringContainsString('test-token', $mail->actionUrl);
    }

    public function test_a_send_that_fails_leaves_the_player_outstanding(): void
    {
        // The ordering inside the loop, which is the difference between a
        // nuisance and a player who never gets in: invited_at is written only
        // after the send returns. Recorded first, a crashed send would mark
        // somebody as reached who was never written to, and no later run would
        // ever pick them up.
        $player = $this->player('ada@example.test');

        $this->app->instance(
            \Illuminate\Contracts\Notifications\Dispatcher::class,
            new class implements \Illuminate\Contracts\Notifications\Dispatcher
            {
                public function send($notifiables, $notification)
                {
                    throw new \RuntimeException('smtp said no');
                }

                public function sendNow($notifiables, $notification, ?array $channels = null)
                {
                    throw new \RuntimeException('smtp said no');
                }
            }
        );

        $this->artisan('users:invite --sleep=0 --force')->assertExitCode(1);

        $this->assertNull(
            $player->fresh()->invited_at,
            'A failed send must leave the player outstanding so the next run retries them.'
        );
    }
}
