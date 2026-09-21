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

    public function test_each_form_reports_its_own_save(): void
    {
        /*
         * Three forms share this page, so "Saved." has to belong to one of
         * them. It used to be driven by a page-wide flash -- ProfileController
         * set 'profile-updated' and PasswordController 'password-updated',
         * strings each partial matched on to recognise its own save. Neither
         * controller flashes anything now; each form asks its own useForm,
         * which sets recentlySuccessful on success and clears it 2000ms later.
         *
         * Nothing here can press a button, so this asserts the wiring at both
         * ends: no status is flashed, and the indicator is bound to the form.
         * That it actually appears and clears was checked in a browser.
         */
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com',
        ])->assertRedirect('/profile')->assertSessionMissing('status');

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'a-longer-new-password',
            'password_confirmation' => 'a-longer-new-password',
        ])->assertSessionHasNoErrors()->assertSessionMissing('status');

        foreach (['UpdateProfileInformationForm', 'UpdatePasswordForm'] as $partial) {
            $source = $this->withoutProse(
                resource_path("js/Pages/Profile/Partials/{$partial}.vue")
            );

            $this->assertStringContainsString('v-if="form.recentlySuccessful"', $source,
                "{$partial} no longer reports its own save.");

            // And neither partial has kept a sentinel to fall back on.
            $this->assertStringNotContainsString('-updated', $source);
        }
    }

    /**
     * A file with its comments removed.
     *
     * Both partials EXPLAIN which sentinel they replaced, by name, so an
     * assertion that the name is absent would otherwise fail against the
     * explanation of why it passes.
     */
    private function withoutProse(string $path): string
    {
        $source = (string) file_get_contents($path);

        $source = (string) preg_replace('/<!--.*?-->/s', ' ', $source);
        $source = (string) preg_replace('#/\*.*?\*/#s', ' ', $source);

        // Anchored, so a protocol-relative URL keeps its slashes.
        return (string) preg_replace('#^\s*//.*$#m', ' ', $source);
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
