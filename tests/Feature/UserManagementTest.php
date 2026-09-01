<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Notifications\PlayerApproved;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_user_index()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertSee('User Management');
    }

    public function test_non_admin_cannot_access_user_index()
    {
        $user = User::factory()->create(['is_admin' => false]);
        
        $response = $this->actingAs($user)->get(route('users.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_update_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['first_name' => 'Original']);

        $response = $this->actingAs($admin)->patch(route('users.update', $user), [
            'first_name' => 'Updated',
            'last_name' => $user->last_name,
            'email' => $user->email,
            'is_admin' => 0,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertEquals('Updated', $user->fresh()->first_name);
    }

    public function test_admin_can_delete_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->delete(route('users.destroy', $user));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_themselves()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->delete(route('users.destroy', $admin));

        $response->assertSessionHas('error', 'You cannot delete yourself.');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_the_pending_queue_lists_accounts_awaiting_a_decision()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->pending()->create(['first_name' => 'Pendingly']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Pendingly')
            ->assertSee('Awaiting approval');
    }

    public function test_the_queue_is_absent_when_nothing_is_pending()
    {
        // Absent, not empty-stated. A heading over an empty table is furniture:
        // it costs attention on every visit and says nothing.
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('Awaiting approval');
    }

    public function test_approving_records_who_decided_and_when()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->pending()->create();

        $this->actingAs($admin)->patch(route('users.approve', $player))->assertRedirect();

        $player->refresh();

        $this->assertTrue($player->isApproved());
        $this->assertNotNull($player->approval_decided_at);
        $this->assertSame($admin->id, $player->approval_decided_by);
    }

    public function test_rejecting_keeps_the_account_and_records_the_decision()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->pending()->create();

        $this->actingAs($admin)->patch(route('users.reject', $player));

        $player->refresh();

        $this->assertSame('rejected', $player->approval_status);
        $this->assertSame($admin->id, $player->approval_decided_by);
        // Kept, not deleted: the decision has to be reversible and a refused
        // person must not be able to re-register into a clean slate.
        $this->assertDatabaseHas('users', ['id' => $player->id]);
    }

    public function test_a_rejected_account_can_be_approved_again()
    {
        // The only route back once it has left the pending queue. Without this,
        // "reversible" is a claim the interface does not support.
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->rejected()->create();

        $this->actingAs($admin)->patch(route('users.approve', $player));

        $this->assertTrue($player->fresh()->isApproved());
    }

    public function test_a_player_cannot_approve_anyone()
    {
        $player = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->pending()->create();

        $this->actingAs($player)->patch(route('users.approve', $other))->assertForbidden();

        $this->assertFalse($other->fresh()->isApproved());
    }

    public function test_the_main_table_shows_approval_state()
    {
        // What makes rejection reversible in fact: a rejected account has left
        // the queue, so without a status on the main list there is no route
        // back to it.
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->rejected()->create(['first_name' => 'Refusedly']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Refusedly')
            ->assertSee('Rejected');
    }

    public function test_an_admin_can_register_a_player_who_is_approved_immediately()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'Dana',
            'last_name' => 'Whitlock',
            'email' => 'dana@example.com',
        ])->assertRedirect();

        $player = User::where('email', 'dana@example.com')->first();

        $this->assertNotNull($player);
        $this->assertTrue($player->isApproved());
        $this->assertSame($admin->id, $player->approval_decided_by);

        // Approval only. An administrator creating the account vouches for the
        // person, not for the address.
        $this->assertFalse($player->hasVerifiedEmail());
    }

    public function test_an_admin_created_player_is_sent_an_invite_to_set_a_password()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'Dana',
            'last_name' => 'Whitlock',
            'email' => 'dana@example.com',
        ]);

        Notification::assertSentTo(
            User::where('email', 'dana@example.com')->first(),
            ResetPassword::class
        );
    }

    public function test_the_invite_link_shown_to_the_admin_is_the_one_that_works()
    {
        // MAIL_MAILER is log, so without a copyable link the button produces an
        // account nobody can get into.
        //
        // This asserts the surfaced token is VALID, not merely present.
        // Password::createToken deletes any existing token for the user, so an
        // implementation that sent one link and surfaced a second would email a
        // dead one -- and a test that only checked the session key would pass.
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'Dana',
            'last_name' => 'Whitlock',
            'email' => 'dana@example.com',
        ]);

        $response->assertSessionHas('invite_url');

        $player = User::where('email', 'dana@example.com')->first();
        $url = session('invite_url');

        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->assertTrue(
            Password::tokenExists($player, $token),
            'The link handed to the administrator must carry a token that is still valid.'
        );
        $this->assertSame($player->email, $query['email'] ?? null);
    }

    public function test_an_admin_created_player_has_no_usable_password()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('users.store'), [
            'first_name' => 'Dana',
            'last_name' => 'Whitlock',
            'email' => 'dana@example.com',
        ]);

        // Nobody chose it and nobody was told it, so it must not be guessable.
        $this->assertFalse(auth()->attempt(['email' => 'dana@example.com', 'password' => 'password']));
    }

    public function test_a_player_cannot_register_a_player()
    {
        $player = User::factory()->create(['is_admin' => false]);

        $this->actingAs($player)->get(route('users.create'))->assertForbidden();
        $this->actingAs($player)->post(route('users.store'), [
            'first_name' => 'Dana',
            'last_name' => 'Whitlock',
            'email' => 'dana@example.com',
        ])->assertForbidden();

        $this->assertNull(User::where('email', 'dana@example.com')->first());
    }

    public function test_an_admin_can_resend_a_verification_link()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->unverified()->create();

        $this->actingAs($admin)->post(route('users.verification', $player))
            ->assertRedirect()
            ->assertSessionHas('verification_url');

        Notification::assertSentTo($player, VerifyEmail::class);
    }

    public function test_the_surfaced_verification_link_actually_verifies_the_account()
    {
        // Asserts the link WORKS, not merely that a session key is set. A
        // signed URL with the wrong expiry or a mistyped hash looks identical
        // in the session and fails only when someone clicks it.
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->unverified()->create();

        $this->actingAs($admin)->post(route('users.verification', $player));

        $this->actingAs($player)->get(session('verification_url'));

        $this->assertTrue($player->fresh()->hasVerifiedEmail());
    }

    public function test_resending_verification_is_refused_for_a_verified_account()
    {
        // An action that cannot do anything should not pretend it did.
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();

        $this->actingAs($admin)->post(route('users.verification', $player))
            ->assertSessionHas('error');
    }

    public function test_an_admin_can_reissue_an_invite_link_that_works()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();

        $this->actingAs($admin)->post(route('users.invite', $player))
            ->assertSessionHas('invite_url');

        Notification::assertSentTo($player, ResetPassword::class);

        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));

        $this->assertTrue(
            Password::tokenExists($player, $token),
            'The reissued link must carry a token that is still valid.'
        );
    }

    public function test_a_player_cannot_send_links_for_anyone()
    {
        $player = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->unverified()->create();

        $this->actingAs($player)->post(route('users.verification', $other))->assertForbidden();
        $this->actingAs($player)->post(route('users.invite', $other))->assertForbidden();

        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_approving_a_player_tells_them()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->pending()->create();

        $this->actingAs($admin)->patch(route('users.approve', $player));

        Notification::assertSentTo($player, PlayerApproved::class);
    }

    public function test_rejecting_a_player_sends_nothing()
    {
        // Deliberate. An automated refusal invites a reply the league has no
        // process to field, and a rejection is often about a duplicate account
        // rather than the person. An administrator can explain directly.
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->pending()->create();

        $this->actingAs($admin)->patch(route('users.reject', $player));

        Notification::assertNothingSentTo($player);
    }

    public function test_re_approving_an_already_approved_player_does_not_notify_again()
    {
        // The approve action doubles as the route back for a rejected account,
        // so it can be reached for someone who is already approved. Sending a
        // fresh "you're in"每 time an administrator opens that page would be
        // noise.
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create();

        $this->actingAs($admin)->patch(route('users.approve', $player));

        Notification::assertNothingSentTo($player);
    }
}
