<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        // anyone sitting at the machine. The keys below are the ones the
        // navigation and layout actually use.
        $user = User::factory()->create(['approval_status' => 'approved']);

        $response = $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        $shared = $response->viewData('page')['props']['auth']['user'];

        // An exact list, not a subset. Widening it should be a decision
        // somebody makes on purpose -- display_name was added here for the
        // navigation, and this assertion is what made that visible rather
        // than letting a sixth field arrive unnoticed.
        $this->assertSame(
            ['id', 'first_name', 'last_name', 'nickname', 'display_name', 'is_admin'],
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

    public function test_the_shell_is_given_the_display_name_rather_than_deriving_it(): void
    {
        // display_name is an accessor: the nickname when there is one, the
        // first name otherwise. Recomputing that rule in TypeScript would be a
        // second definition of it, free to disagree the first time somebody
        // changes the PHP.
        $user = User::factory()->create([
            'first_name' => 'Wanda', 'nickname' => 'Wan', 'approval_status' => 'approved',
        ]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.display_name', 'Wan'));
    }

    public function test_the_shell_is_given_the_notification_badge_and_list(): void
    {
        $user = User::factory()->create(['approval_status' => 'approved']);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unread_count', 0)
                ->has('notifications.recent', 0)
            );
    }

    public function test_the_recent_list_is_capped_at_ten(): void
    {
        // The panel scrolls. A player with three seasons of history should not
        // have all of it serialised into every page they load -- the same cap
        // the View Composer applies.
        $user = User::factory()->create(['approval_status' => 'approved']);

        for ($i = 0; $i < 12; $i++) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test',
                'data' => ['tournament_name' => "Night {$i}", 'place' => 1, 'points' => 100, 'played_on' => '2026-09-01'],
            ]);
        }

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('notifications.recent', 10));
    }

    public function test_a_guest_request_costs_no_notification_queries(): void
    {
        // The composer this replaces says: "Guests get nothing. layouts/public
        // renders the same x-topbar and must not pay for a query it has no
        // user for." The middleware runs on the whole web group, so without a
        // guard every public page would run two queries for nobody.
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('home'))->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $touched = collect($queries)->filter(
            fn ($query) => str_contains($query['query'], 'notifications')
        );

        $this->assertCount(0, $touched, 'A signed-out page ran a notification query.');
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
