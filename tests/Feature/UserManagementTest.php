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
}
