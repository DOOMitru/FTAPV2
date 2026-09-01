<?php

namespace Tests\Feature;

use App\Models\User;
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
}
