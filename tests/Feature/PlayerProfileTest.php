<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One player's figures, read by another.
 *
 * The dashboard's season panel and recent results, about somebody else. Not
 * its Upcoming Tournaments: that is a to-do list rather than a record, and
 * another player's diary is neither this reader's business nor any use to them.
 *
 * Venue points obey the league's rule wherever they appear -- the owner and
 * admins, nobody else. VenuePointsPrivacyTest enforces that across every route
 * without naming this one; what is pinned here is the same rule at close range,
 * so a failure says which page rather than only that some page leaked.
 */
class PlayerProfileTest extends TestCase
{
    use RefreshDatabase;

    private const TALLY = 137;

    private User $player;

    private User $other;

    private User $admin;

    private PokerTournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        PointsStructure::create(['place' => 1, 'points' => 500]);

        $season = PokerSeason::create([
            'name' => 'Season 9', 'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(), 'is_current' => true,
        ]);

        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '1 St'])->id;

        $this->tournament = PokerTournament::create([
            'name' => 'Wednesday Night Poker', 'start_time' => now()->subDays(7),
            'venue_id' => $venue, 'season_id' => $season->id,
        ]);

        $this->player = $this->makePlayer('Wanda', 'Reeve');
        $this->other = $this->makePlayer('Baltazar', 'Whitlock');
        $this->admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        foreach ([$this->player, $this->other] as $u) {
            PokerTournamentRegistrant::create([
                'tournament_id' => $this->tournament->id, 'user_id' => $u->id,
                'player_name' => $u->first_name.' '.$u->last_name, 'registered_at' => now()->subDays(7),
            ]);
        }

        PokerTournamentResult::create([
            'tournament_id' => $this->tournament->id, 'user_id' => $this->player->id,
            'player_name' => 'Wanda Reeve', 'place' => 1, 'points' => 500,
        ]);

        VenuePoints::create([
            'user_id' => $this->player->id, 'user_name' => 'Wanda', 'venue_id' => $venue,
            'amount' => self::TALLY, 'event_date' => now()->subDays(5)->toDateString(),
            'season_id' => $season->id,
        ]);
    }

    private function makePlayer(string $first, string $last): User
    {
        return User::factory()->create([
            'first_name' => $first, 'last_name' => $last, 'approval_status' => 'approved',
        ]);
    }

    private function profile(?User $viewer)
    {
        $url = route('players.show', $this->player);

        return $viewer === null ? $this->get($url) : $this->actingAs($viewer)->get($url);
    }

    public function test_a_guest_cannot_read_a_players_figures(): void
    {
        $this->profile(null)->assertRedirect(route('login'));
    }

    public function test_any_signed_in_player_can(): void
    {
        // Not admin-only: the league looking at itself is the point of the page.
        $this->profile($this->other)->assertOk()->assertSee('Wanda Reeve');
    }

    public function test_it_shows_the_season_figures(): void
    {
        $response = $this->profile($this->other)->assertOk();

        $figures = $response->viewData('season');

        $this->assertSame(1, $figures['events']);
        $this->assertSame(500, $figures['points']);
        $this->assertSame(1, $figures['first']);
        $this->assertSame(1, $figures['rank']);
    }

    public function test_the_figures_are_the_dashboards_figures(): void
    {
        // Same method, so the two pages cannot disagree about a player. A
        // second copy of the arithmetic is what this is guarding against.
        $mine = $this->actingAs($this->player)->get(route('dashboard'))
            ->assertOk()->viewData('season');

        $theirs = $this->profile($this->other)->assertOk()->viewData('season');

        unset($mine['venuePoints'], $theirs['venuePoints']);

        $this->assertSame($mine, $theirs);
    }

    public function test_it_shows_their_recent_results(): void
    {
        $this->profile($this->other)->assertOk()
            ->assertSee('Wednesday Night Poker')
            ->assertSee('Recent Results');
    }

    public function test_it_does_not_show_upcoming_tournaments(): void
    {
        // Explicitly excluded, and the reason is not screen space: another
        // player's diary is not this reader's business.
        PokerTournament::create([
            'name' => 'Next Thursday', 'start_time' => now()->addWeek(),
            'venue_id' => Venue::first()->id, 'season_id' => PokerSeason::first()->id,
        ]);

        $response = $this->profile($this->other)->assertOk();

        $response->assertDontSee('Upcoming Tournaments');
        $response->assertDontSee('Next Thursday');
    }

    public function test_another_player_does_not_read_the_venue_tally(): void
    {
        $response = $this->profile($this->other)->assertOk();

        $this->assertNull($response->viewData('season')['venuePoints']);
        $response->assertDontSee('Venue Points');
        $response->assertDontSee((string) self::TALLY);
    }

    public function test_the_owner_reads_their_own(): void
    {
        $response = $this->profile($this->player)->assertOk();

        $this->assertSame(self::TALLY, $response->viewData('season')['venuePoints']);
        $response->assertSee('Venue Points');
    }

    public function test_an_admin_reads_it_too(): void
    {
        $response = $this->profile($this->admin)->assertOk();

        $this->assertSame(self::TALLY, $response->viewData('season')['venuePoints']);
    }

    public function test_the_owner_is_offered_their_dashboard(): void
    {
        // The page is not a replacement for it -- the dashboard has the diary.
        $this->profile($this->player)->assertOk()->assertSee('Your dashboard');
        $this->profile($this->other)->assertOk()->assertDontSee('Your dashboard');
    }

    public function test_a_player_with_nothing_yet_still_has_a_page(): void
    {
        $newcomer = $this->makePlayer('Newly', 'Joined');

        $this->actingAs($this->other)
            ->get(route('players.show', $newcomer))->assertOk()
            ->assertSee('Newly')
            ->assertSee('No results recorded yet.');
    }

    public function test_a_result_with_no_account_behind_it_is_not_a_link(): void
    {
        // user_id is nullable with nullOnDelete, so a departed player leaves a
        // name on every result they ever scored. A link for one of those is a
        // 404 with somebody's name on it.
        PokerTournamentResult::create([
            'tournament_id' => $this->tournament->id, 'user_id' => null,
            'player_name' => 'Departed Player', 'place' => 2, 'points' => 360,
        ]);

        $html = $this->actingAs($this->other)
            ->get(route('tournaments.show', $this->tournament))->assertOk()->getContent();

        $this->assertStringContainsString('Departed Player', $html, 'The name still shows.');

        // The name renders, but not inside an anchor.
        preg_match_all('/<a[^>]*players\/[^>]*>(.*?)<\/a>/s', $html, $links);

        $this->assertNotContains(
            'Departed Player',
            array_map(fn ($t) => trim(strip_tags($t)), $links[1]),
            'A name with no account behind it was linked.'
        );
    }

    public function test_a_name_with_an_account_is_a_link(): void
    {
        // The positive control: without it the test above is satisfied by a
        // page that links nobody at all.
        $html = $this->actingAs($this->other)
            ->get(route('tournaments.show', $this->tournament))->assertOk()->getContent();

        preg_match_all('/<a[^>]*players\/[^>]*>(.*?)<\/a>/s', $html, $links);

        $this->assertContains(
            'Wanda Reeve',
            array_map(fn ($t) => trim(strip_tags($t)), $links[1])
        );
    }

    public function test_the_admin_players_list_links_both_its_tables(): void
    {
        // Two tables on that page -- awaiting approval above, approved below --
        // and the link belongs in both. A pending player has no figures yet,
        // which is an honest empty page rather than a reason to withhold the
        // way to it.
        $pending = User::factory()->create([
            'first_name' => 'Pending', 'last_name' => 'Person', 'approval_status' => 'pending',
        ]);

        $html = $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->getContent();

        // Sliced per table. The main table lists EVERYONE, pending accounts
        // included, so a page-wide search for the pending player's link finds
        // the one in the lower table and says nothing about the queue above --
        // unlinking the queue left this green until it was scoped.
        $queue = substr($html, 0, strpos($html, 'Clear search') ?: strpos($html, 'name="search"'));
        $main = substr($html, strlen($queue));

        $this->assertStringContainsString(
            route('players.show', $pending), $queue,
            'The awaiting-approval queue lists a player without a way to their figures.'
        );

        $this->assertStringContainsString(
            route('players.show', $this->player), $main,
            'The main table lists a player without a way to their figures.'
        );

        // And the account view is still reachable: the two answer different
        // questions and neither replaces the other.
        // The exact href, closing quote included: route('users.show', $u) is a
        // prefix of route('users.edit', $u), so a bare substring search is
        // satisfied by the Edit link and proves nothing about View.
        $this->assertStringContainsString(
            'href="'.route('users.show', $this->player).'"', $html
        );
    }

    public function test_an_unranked_player_is_told_so_in_the_third_person(): void
    {
        // The panel's copy addresses the viewer on the dashboard -- "Enter a
        // tournament to be ranked" -- which is the wrong thing to say about
        // somebody else.
        $newcomer = $this->makePlayer('Newly', 'Joined');

        $this->actingAs($this->other)
            ->get(route('players.show', $newcomer))->assertOk()
            ->assertSee('A player is ranked once they enter a tournament.')
            ->assertDontSee('Enter a tournament to be ranked.');

        $this->actingAs($newcomer)->get(route('dashboard'))->assertOk()
            ->assertSee('Enter a tournament to be ranked.');
    }
}
