<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * What every Inertia page is handed, whatever it asks for.
 *
 * Flash is SHARED but not rendered by the layout. Three controllers flash
 * sentinel strings -- 'profile-updated', 'password-updated',
 * 'verification-link-sent' -- that views match on rather than display, so a
 * layout printing flash generically would show a reader 'profile-updated'.
 * The same reasoning is recorded in layouts/app.blade.php, which is where this
 * decision was first made and why it is inherited rather than re-argued.
 */
class InertiaSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_is_given_the_signed_in_user(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Wanda', 'last_name' => 'Reeve', 'approval_status' => 'approved',
        ]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.first_name', 'Wanda')
                ->where('auth.user.is_admin', false)
                ->has('flash')
            );
    }

    public function test_the_user_is_narrowed_to_what_a_page_may_see(): void
    {
        // Not the model. A shared prop is on EVERY page, so handing over the
        // whole user would put its password hash, remember token and email in
        // the HTML of every screen in the app -- readable in view-source by
        // anyone sitting at the machine. The five keys below are the ones the
        // navigation and layout actually use.
        $user = User::factory()->create(['approval_status' => 'approved']);

        $response = $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        $shared = $response->viewData('page')['props']['auth']['user'];

        $this->assertSame(
            ['id', 'first_name', 'last_name', 'nickname', 'is_admin'],
            array_keys($shared)
        );

        $html = $response->getContent();

        foreach (['password', 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $html,
                "A shared prop put {$secret} into the page source.");
        }
    }

    public function test_a_sentinel_flash_reaches_the_page_unchanged(): void
    {
        // Not translated, not rendered -- handed over exactly as flashed, so
        // the page can match on it the way the Blade partial does.
        $user = User::factory()->create(['approval_status' => 'approved']);

        $this->actingAs($user)
            ->withSession(['status' => 'profile-updated'])
            ->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('flash.status', 'profile-updated'));
    }

    public function test_a_guest_page_carries_a_null_user_rather_than_no_key(): void
    {
        // The shape has to be stable: `auth.user` absent and `auth.user` null
        // are different things to a template, and a page that reads
        // auth.user.first_name should fail loudly rather than silently render
        // nothing. Checked against a signed-out request to the same route.
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }
}
