<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test', $user->first_name);
        $this->assertSame('User', $user->last_name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_a_wrong_password_sends_the_delete_error_back_to_the_page(): void
    {
        // The dialog has to reopen so the person can try again -- otherwise a
        // typo silently dismisses the whole thing and looks like it worked.
        //
        // This used to assert `show: true,` in the markup, which was Alpine's
        // way of saying it. /profile is an Inertia page now and that string is
        // gone, so the assertion is on the CONTRACT the reopening is built on:
        // the page comes back carrying the userDeletion error bag, and the Vue
        // dialog opens itself when that bag has a password error. The visible
        // reopening is checked in a browser, since no server-side assertion can
        // see it.
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->followingRedirects()
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response->assertOk();

        // Inertia shares errors as an object, not an array, so this is cast
        // before it is read -- assertArrayHasKey on a stdClass is a TypeError,
        // not a failed assertion, and reads as a broken test rather than a
        // broken page.
        $errors = (array) $response->viewData('page')['props']['errors'];

        $this->assertArrayHasKey('userDeletion', $errors,
            'The delete form uses a named error bag; without it a failure here lights up the other two forms on the page.');
        $this->assertArrayHasKey('password', (array) $errors['userDeletion']);

        $this->assertNotNull($user->fresh());
    }
}
