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

    /**
     * The notifications the page is sent.
     *
     * The topbar is Vue on every authenticated page now, so the badge and the
     * panel are drawn after mount from this shared prop rather than written
     * into the response. Which is the better thing to assert: the old version
     * had to cut the HTML around 'nav-link__badge' and read the digits out of
     * it, because the data reached the page through a View Composer bound to
     * an @included Blade view, where assertViewHas cannot see it. Both the
     * composer and that view are deleted; the middleware shares this instead.
     *
     * @return array{unread_count: int, recent: list<array<string, mixed>>}
     */
    private function shared(User $user): array
    {
        return $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->viewData('page')['props']['notifications'];
    }

    /** The topbar's markup, which both surfaces are written in. */
    private function navigation(): string
    {
        return (string) file_get_contents(resource_path('js/Layouts/Navigation.vue'));
    }

    public function test_the_badge_counts_unread_only(): void
    {
        // Asserted on the shared prop the page is handed. assertViewHas
        // inspects the RESPONSE'S root view, which for an Inertia response
        // holds the page object rather than the props -- so a view-data
        // assertion here reads null no matter what the page was sent.
        $user = $this->player();
        $this->notify($user);
        $this->notify($user);
        $this->notify($user, read: true);

        $this->assertSame(2, $this->shared($user)['unread_count'],
            'The count included a read notification.');

        // And the badge draws that count rather than a length of the list.
        $this->assertStringContainsString(
            '{{ page.props.notifications.unread_count }}',
            $this->navigation()
        );
    }

    public function test_there_is_no_badge_at_zero(): void
    {
        // A badge showing 0 is a badge saying nothing, loudly.
        $user = $this->player();
        $this->notify($user, read: true);

        $this->assertSame(0, $this->shared($user)['unread_count']);

        // Both badges are gated on the count being above zero. Asserting the
        // absence of a class in the response would pass for the wrong reason
        // now -- no badge is ever IN the response, at any count.
        $this->assertSame(
            2,
            substr_count($this->navigation(), 'v-if="page.props.notifications.unread_count > 0"'),
            'A badge is drawn without checking the count, or a surface lost its gate.'
        );
    }

    public function test_the_dropdown_lists_notifications(): void
    {
        $user = $this->player();
        $this->notify($user, 'Autumn Showdown');

        $recent = $this->shared($user)['recent'];

        $this->assertSame(['Autumn Showdown'], collect($recent)->pluck('title')->all());

        // The tier is semantic in the props and becomes a class in the card --
        // spec §5a. A first place is gold.
        $this->assertSame('gold', $recent[0]['tier']);

        $this->assertStringContainsString(
            'notification--gold',
            (string) file_get_contents(resource_path('js/Components/NotificationCard.vue'))
        );
    }

    public function test_the_dropdown_still_holds_profile_and_log_out(): void
    {
        // The redesign must not lose what the menu was already for.
        $user = $this->player();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $nav = $this->navigation();

        $this->assertStringContainsString('href="/profile"', $nav);
        $this->assertStringContainsString('/logout', $nav);
    }

    public function test_an_empty_list_says_so(): void
    {
        $this->assertSame([], $this->shared($this->player())['recent']);

        // Once per surface, so neither is left with a blank panel.
        $this->assertSame(2, substr_count($this->navigation(), 'No notifications yet.'));
    }

    public function test_clear_read_is_offered_only_when_something_is_read(): void
    {
        $user = $this->player();
        $this->notify($user);

        // Whether anything is read is decided from the list the page is sent,
        // by hasRead() -- so the data has to carry it.
        $this->assertSame([false], collect($this->shared($user)['recent'])->pluck('is_read')->all());

        $this->notify($user, read: true);

        $this->assertContains(true, collect($this->shared($user)['recent'])->pluck('is_read')->all());

        // And the control is gated on that, on both surfaces.
        $this->assertSame(2, substr_count($this->navigation(), 'v-if="hasRead()"'));
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

        $titles = collect($this->shared($user)['recent'])->pluck('title')->all();

        // Ten, sent once and drawn by both surfaces -- the desktop user menu
        // and the mobile bell panel, each hidden at the other's breakpoint.
        // The cap used to be counted in the markup, where ten appeared twice;
        // one list is sent now and both surfaces read it.
        $this->assertCount(10, $titles, 'The list is not capped at ten.');

        $this->assertSame('Tournament 12', $titles[0], 'Newest first.');
        $this->assertSame('Tournament 03', $titles[9]);
        $this->assertNotContains('Tournament 02', $titles);
        $this->assertNotContains('Tournament 01', $titles);

        $this->assertSame(
            2,
            substr_count($this->navigation(), 'v-for="notification in page.props.notifications.recent"'),
            'Both surfaces must draw the one list they are sent.'
        );
    }

    public function test_one_players_notifications_do_not_reach_another(): void
    {
        $mine = $this->player();
        $theirs = $this->player();
        $this->notify($theirs, 'Not Mine');

        // Absent from the props, which is what "does not reach" means. The
        // old assertDontSee would pass now whatever the middleware shared:
        // nothing is in the response either way.
        $shared = $this->shared($mine);

        $this->assertSame([], $shared['recent']);
        $this->assertSame(0, $shared['unread_count']);
    }

    public function test_the_mobile_bell_is_rendered_for_a_signed_in_player(): void
    {
        $user = $this->player();
        $this->notify($user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertStringContainsString('class="topbar__bell"', $this->navigation());
    }

    public function test_the_bell_carries_its_own_badge(): void
    {
        $user = $this->player();
        $this->notify($user);
        $this->notify($user);

        $this->assertSame(2, $this->shared($user)['unread_count']);

        // Its own badge, inside the bell's own button.
        $nav = $this->navigation();
        $bell = substr($nav, (int) strpos($nav, 'class="topbar__bell"'));
        $bell = substr($bell, 0, (int) strpos($bell, '</button>'));

        $this->assertStringContainsString('topbar__bell-badge', $bell);
        $this->assertStringContainsString('{{ page.props.notifications.unread_count }}', $bell);
    }

    public function test_the_bell_has_no_badge_at_zero(): void
    {
        $user = $this->player();
        $this->notify($user, read: true);

        $this->assertSame(0, $this->shared($user)['unread_count']);

        // The bell is always drawn; its badge is not. Both facts read off the
        // one element, so a badge that escaped its v-if would show here.
        $nav = $this->navigation();
        $bell = substr($nav, (int) strpos($nav, 'class="topbar__bell"'));
        $bell = substr($bell, 0, (int) strpos($bell, '</button>'));

        $this->assertMatchesRegularExpression(
            '/v-if="page\.props\.notifications\.unread_count > 0"[^>]*class="topbar__bell-badge"/s',
            $bell,
            'The bell badge is drawn without checking the count.'
        );
    }

    public function test_the_bell_sits_before_the_burger(): void
    {
        // "Left of the menu button" is the requirement, and in a
        // direction-agnostic layout that is document order -- which is now the
        // order of the two slots the topbar is given.
        $this->actingAs($this->player())->get(route('dashboard'))->assertOk();

        $nav = $this->navigation();
        $topbar = (string) file_get_contents(resource_path('js/Components/Topbar.vue'));

        // Both markers asserted present first. strpos returns false for a
        // missing needle, and assertLessThan coerces that to 0 -- so without
        // this the comparison passes when there is no bell at all.
        $this->assertStringContainsString('topbar__bell', $nav);
        $this->assertStringContainsString('topbar__burger', $topbar);

        // The bell fills the topbar's `leading` slot -- "anything that belongs
        // beside the burger rather than inside the panel" -- and that slot is
        // rendered before the burger button.
        $this->assertStringContainsString('<template #leading>', $nav,
            'The bell is no longer in the slot that sits beside the burger.');

        $leadingAt = strpos($topbar, '<slot name="leading" />');
        $burgerAt = strpos($topbar, 'class="topbar__burger"');

        // strpos returns false for a missing needle and assertLessThan coerces
        // that to 0, so a missing slot would pass. Both are asserted present
        // first, which is the trap the previous version of this test recorded
        // and which this one fell into while being ported.
        $this->assertNotFalse($leadingAt, 'The topbar has no leading slot.');
        $this->assertNotFalse($burgerAt);

        $this->assertLessThan($burgerAt, $leadingAt, 'The bell must come before the burger.');
    }

    public function test_the_bell_panel_lists_the_same_notifications(): void
    {
        $user = $this->player();
        $this->notify($user, 'Autumn Showdown');

        // Two surfaces, ONE list. It used to be counted by the name appearing
        // twice in the markup; the list is sent once now and both panels read
        // the same prop, which is the thing that makes them agree.
        $this->assertSame(['Autumn Showdown'], collect($this->shared($user)['recent'])->pluck('title')->all());

        $this->assertSame(
            2,
            substr_count($this->navigation(), ':notification="notification"'),
            'Both surfaces must render the same list.'
        );
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
