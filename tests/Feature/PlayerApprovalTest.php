<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admission to the league, as distinct from admission to the website.
 *
 * Anyone may hold an account. Only an approved account may enter a tournament,
 * and the rule is enforced at every path that can create a registration --
 * self-service, the administrator override that shares the same controller
 * method, and the administrator registrant form.
 */
class PlayerApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_starts_pending(): void
    {
        // Created directly rather than through the factory. The factory
        // deliberately produces APPROVED users so the sixteen other test files
        // that create a user and then hit a gated route are unaffected, which
        // means the factory cannot also demonstrate the column's default.
        $user = User::create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'irrelevant',
        ]);

        $this->assertSame('pending', $user->fresh()->approval_status);
        $this->assertFalse($user->isApproved());
        $this->assertTrue($user->isPendingApproval());
    }

    public function test_the_factory_produces_approved_users_by_default(): void
    {
        // Asserted rather than assumed: if this default ever flips, sixteen
        // unrelated test files break at once and the cause is not obvious from
        // any of their failures.
        $this->assertTrue(User::factory()->create()->isApproved());
    }

    public function test_the_factory_can_produce_pending_and_rejected_users(): void
    {
        $this->assertTrue(User::factory()->pending()->create()->isPendingApproval());

        $rejected = User::factory()->rejected()->create();

        $this->assertFalse($rejected->isApproved());
        $this->assertFalse($rejected->isPendingApproval());
    }

    public function test_scopes_select_the_right_accounts(): void
    {
        User::factory()->create();
        User::factory()->pending()->create();
        User::factory()->rejected()->create();

        $this->assertSame(1, User::approved()->count());
        $this->assertSame(1, User::awaitingApproval()->count());
    }

    private function makeTournament(): PokerTournament
    {
        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Weekly Freezeout',
            'start_time' => now()->addDay()->addHours(2),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);
    }

    public function test_a_pending_player_cannot_self_register(): void
    {
        $player = User::factory()->pending()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($player)
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_a_rejected_player_cannot_self_register(): void
    {
        // Rejection is not merely absence from the pending queue. A rejected
        // account still exists and still logs in; it must be refused here.
        $player = User::factory()->rejected()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($player)
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_an_approved_player_can_self_register(): void
    {
        $player = User::factory()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($player)->post(route('tournaments.register', $tournament));

        $this->assertDatabaseHas('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_an_admin_cannot_register_a_pending_player_via_the_override(): void
    {
        // One controller method serves self-registration and the administrator
        // user_id override. The gate must read the TARGET user, not the actor,
        // or an administrator becomes a way around the rule rather than a user
        // of it.
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->pending()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($admin)->post(
            route('tournaments.register', $tournament),
            ['user_id' => $player->id]
        )->assertSessionHas('error');

        $this->assertDatabaseMissing('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_a_pending_player_is_told_why_rather_than_shown_a_register_button(): void
    {
        $player = User::factory()->pending()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $response = $this->actingAs($player)->get('/events');

        // Asserted on the form ACTION, not on the word "Register": the page
        // also says "Registration closes" and "Registration open", so a text
        // assertion would pass for the wrong reason.
        $response->assertOk()
            ->assertSee(__('Awaiting approval'))
            ->assertDontSee(route('tournaments.register', $tournament));
    }

    public function test_an_approved_player_still_gets_the_register_button(): void
    {
        // The other half of the pair. Without it, a change that hid the button
        // from everyone would pass the test above.
        $player = User::factory()->create(['is_admin' => false]);
        $tournament = $this->makeTournament();

        $this->actingAs($player)->get('/events')
            ->assertOk()
            ->assertSee(route('tournaments.register', $tournament));
    }

    public function test_the_admin_register_dialog_lists_an_unapproved_player_but_will_not_offer_them(): void
    {
        // This asserted that the tournament page never named an unapproved
        // player, on the reasoning that register() refuses them and offering
        // one is offering a click that fails. The refusal is unchanged; what
        // changed is that saying nothing was its own failure -- an
        // administrator who cannot find somebody learns that they are missing,
        // not that their account is waiting, and waiting on this administrator.
        //
        // So the name is listed with the reason, and the row is inert. The
        // POST below is the part that actually matters and is unchanged.
        $admin = User::factory()->create(['is_admin' => true]);
        $approved = User::factory()->create(['first_name' => 'Approvedy', 'is_admin' => false]);
        $pending = User::factory()->pending()->create(['first_name' => 'Pendingly', 'is_admin' => false]);

        $tournament = $this->makeTournament();

        $response = $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk();

        $rows = collect($response->viewData('registerCandidates'))->keyBy('id');

        // Both listed, told apart by the flag rather than by absence.
        $this->assertTrue($rows->has($pending->id), 'An unapproved player must still be listed.');
        $this->assertFalse($rows[$pending->id]['approved']);
        $this->assertTrue($rows[$approved->id]['approved']);

        $response->assertSee('Waiting for approval');

        // And the server still refuses them, which is the rule the flag mirrors.
        $this->actingAs($admin)
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament), ['user_id' => $pending->id])
            ->assertSessionHas('error');

        $this->assertSame(0, $tournament->registrants()->count());
    }
}
