<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Where a player finds their notifications.
 *
 * Two surfaces, because the topbar is two structures rather than one restyled:
 * a dropdown on desktop, and on mobile a flat row that has never had one.
 */
class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, string $tournament = 'Autumn Showdown', bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TournamentPlacement',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'tournament_id' => (string) Str::uuid(), 'tournament_name' => $tournament,
                'played_on' => '2026-09-09', 'place' => 1, 'points' => 100,
            ],
            'read_at' => $read ? now() : null,
        ]);
    }

    private function player(): User
    {
        return User::factory()->create(['approval_status' => 'approved']);
    }

    public function test_the_badge_counts_unread_only(): void
    {
        // Asserted on the rendered badge rather than on view data.
        // assertViewHas inspects the RESPONSE'S root view, and this data is
        // bound by a composer to layouts.navigation, which is @included -- so
        // a view-data assertion here reads null no matter what is on the page.
        $user = $this->player();
        $this->notify($user);
        $this->notify($user);
        $this->notify($user, read: true);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $badge = substr($html, (int) strpos($html, 'nav-link__badge'));
        $badge = substr($badge, 0, (int) strpos($badge, '</span>'));

        $this->assertStringContainsString('2', $badge);
        $this->assertStringNotContainsString('3', $badge, 'The badge counted a read notification.');
    }

    public function test_there_is_no_badge_at_zero(): void
    {
        // A badge showing 0 is a badge saying nothing, loudly.
        $user = $this->player();
        $this->notify($user, read: true);

        // Not assertViewHas: the key is absent from the root view, so it reads
        // null, and assertViewHas compares loosely -- assertEquals(0, null) is
        // true in PHP, so that assertion would pass at any count at all.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertDontSee('nav-link__badge', false);
    }

    public function test_the_dropdown_lists_notifications(): void
    {
        $user = $this->player();
        $this->notify($user, 'Autumn Showdown');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Autumn Showdown')
            ->assertSee('notification--gold', false);
    }

    public function test_the_dropdown_still_holds_profile_and_log_out(): void
    {
        // The redesign must not lose what the menu was already for.
        $user = $this->player();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee(route('profile.edit'), false)
            ->assertSee(route('logout'), false);
    }

    public function test_an_empty_list_says_so(): void
    {
        $this->actingAs($this->player())->get(route('dashboard'))->assertOk()
            ->assertSee('No notifications yet.');
    }

    public function test_clear_read_is_offered_only_when_something_is_read(): void
    {
        $user = $this->player();
        $this->notify($user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Clear read', false);

        $this->notify($user, read: true);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Clear read', false);
    }

    public function test_the_list_is_capped_and_newest_first(): void
    {
        // Zero-padded names, so "Tournament 01" is not a substring of
        // "Tournament 11" and assertDontSee means what it says.
        $user = $this->player();

        for ($i = 1; $i <= 12; $i++) {
            $this->notify($user, sprintf('Tournament %02d', $i))
                ->forceFill(['created_at' => now()->addMinutes($i)])->save();
        }

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $html = $response->getContent();

        // Ten per surface, and an authenticated page draws two of them -- the
        // desktop user menu and the mobile bell panel, both rendered, each
        // hidden at the other's breakpoint.
        $this->assertSame(
            10 * 2,
            substr_count($html, 'class="notification '),
            'The list is not capped at ten per surface.'
        );

        $response->assertSeeInOrder(['Tournament 12', 'Tournament 11', 'Tournament 03'])
            ->assertDontSee('Tournament 02')
            ->assertDontSee('Tournament 01');
    }

    public function test_one_players_notifications_do_not_reach_another(): void
    {
        $mine = $this->player();
        $theirs = $this->player();
        $this->notify($theirs, 'Not Mine');

        $this->actingAs($mine)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Not Mine')
            ->assertDontSee('nav-link__badge', false);
    }

    public function test_the_mobile_bell_is_rendered_for_a_signed_in_player(): void
    {
        $user = $this->player();
        $this->notify($user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('topbar__bell', false);
    }

    public function test_the_bell_carries_its_own_badge(): void
    {
        $user = $this->player();
        $this->notify($user);
        $this->notify($user);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $bell = substr($html, (int) strpos($html, 'topbar__bell'));
        $bell = substr($bell, 0, (int) strpos($bell, '</button>'));

        $this->assertStringContainsString('topbar__bell-badge', $bell);
        $this->assertStringContainsString('2', $bell);
    }

    public function test_the_bell_has_no_badge_at_zero(): void
    {
        $user = $this->player();
        $this->notify($user, read: true);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('topbar__bell', false)
            ->assertDontSee('topbar__bell-badge', false);
    }

    public function test_the_bell_sits_before_the_burger(): void
    {
        // "Left of the menu button" is the requirement, and in a
        // direction-agnostic layout that is document order.
        $html = $this->actingAs($this->player())->get(route('dashboard'))->assertOk()->getContent();

        // Both markers asserted present first. strpos returns false for a
        // missing needle, and assertLessThan coerces that to 0 -- so without
        // this the comparison passes when there is no bell at all.
        $this->assertStringContainsString('topbar__bell', $html);
        $this->assertStringContainsString('topbar__burger', $html);

        $this->assertLessThan(
            strpos($html, 'topbar__burger'),
            strpos($html, 'topbar__bell'),
            'The bell must be rendered before the burger.'
        );
    }

    public function test_the_bell_panel_lists_the_same_notifications(): void
    {
        $user = $this->player();
        $this->notify($user, 'Autumn Showdown');

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        // Two surfaces, one list: the card appears in the bell panel and in the
        // user menu, so the tournament name is rendered twice.
        $this->assertSame(2, substr_count($html, 'Autumn Showdown'));
    }

    public function test_the_public_shell_has_no_bell(): void
    {
        // x-topbar is shared with the public site, which has no signed-in user
        // and must be untouched by the new slot.
        $this->get(route('home'))->assertOk()->assertDontSee('topbar__bell', false);
    }

    public function test_a_guest_visiting_a_public_page_sees_no_notifications(): void
    {
        $this->get(route('events'))->assertOk()
            ->assertDontSee('topbar__bell', false)
            ->assertDontSee('notification--gold', false);
    }

    public function test_a_guest_page_has_no_notification_surface(): void
    {
        // layouts/public renders the same topbar component.
        $this->get(route('home'))->assertOk()->assertDontSee('nav-link__badge', false);
    }
}
