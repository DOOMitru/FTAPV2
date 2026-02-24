<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePictureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_profile_picture()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('profile.jpg');

        $response = $this->actingAs($user)->patch(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'profile_image' => $file,
        ]);

        $response->assertRedirect(route('profile.edit'));
        
        $user->refresh();
        $this->assertNotNull($user->profile_image);
        Storage::disk('public')->assertExists($user->profile_image);
    }

    public function test_user_has_default_profile_picture()
    {
        $user = User::factory()->create(['profile_image' => null]);

        $this->assertStringContainsString('default_profile.png', $user->profile_image_url);
    }

    public function test_admin_can_update_another_user_profile_picture()
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('other_user.jpg');

        $response = $this->actingAs($admin)->patch(route('users.update', $user), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'is_admin' => $user->is_admin ? 1 : 0,
            'profile_image' => $file,
        ]);

        if ($response->status() !== 302) {
            dump($response->getContent());
        }

        $response->assertRedirect(route('users.index'));
        
        $user->refresh();
        $this->assertNotNull($user->profile_image, 'Profile image path should not be null in database');
        Storage::disk('public')->assertExists($user->profile_image);
    }
}
