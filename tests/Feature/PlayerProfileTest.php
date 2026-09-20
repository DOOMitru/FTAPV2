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

    /**
     * What the page is sent, rather than what it renders.
     *
     * The page is Vue: the server sends props and an empty shell, so assertSee
     * on a figure would be asserting against markup the browser has not built.
     * The props are the better question anyway -- a withheld venue tally is
     * ABSENT here, where the old assertion could only look for a number and
     * hope it was not on the page for another reason.
     *
     * @return array<string, mixed>
     */
    private function props(?User $viewer, ?User $subject = null): array
    {
        return $this->actingAs($viewer)
            ->get(route('players.show', $subject ?? $this->player))->assertOk()
            ->viewData('page')['props'];
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
        // Labels, not numbers: SeasonFigures decides the formatting so that
        // the panel places a value rather than computing one (spec §5a).
        $figures = $this->props($this->other)['season'];

        $this->assertSame('1', $figures['events_label']);
        $this->assertSame('500', $figures['points_label']);
        $this->assertSame('1', $figures['first']);
        $this->assertSame('#1', $figures['rank_label']);
    }

    public function test_the_figures_are_the_dashboards_figures(): void
    {
        // Same source, so the two pages cannot disagree about a player. A
        // second copy of the arithmetic is what this is guarding against, and
        // App\Support\SeasonFigures is where that copy would have gone.
        //
        // The two are compared in the forms they currently emit: the dashboard
        // is still Blade and hands its view raw figures, while this page sends
        // labels. When the dashboard converts this collapses to comparing one
        // props array with another -- but not before, because a comparison
        // that runs both sides through the same formatter would prove nothing.
        $raw = $this->actingAs($this->player)->get(route('dashboard'))
            ->assertOk()->viewData('season');

        $labels = $this->props($this->other)['season'];

        $this->assertSame((string) $raw['events'], $labels['events_label']);
        $this->assertSame(number_format($raw['points']), $labels['points_label']);
        $this->assertSame('#'.$raw['rank'], $labels['rank_label']);
        $this->assertSame((string) $raw['first'], $labels['first']);
    }

    public function test_it_shows_their_recent_results(): void
    {
        $results = $this->props($this->other)['results'];

        $this->assertSame(
            ['Wednesday Night Poker'],
            collect($results)->pluck('tournament_name')->all()
        );

        // And the card they sit in, which is the page's, not the props'.
        $this->assertStringContainsString(
            'title="Recent Results"',
            (string) file_get_contents(resource_path('js/Pages/Players/Show.vue'))
        );
    }

    public function test_the_nickname_leads_where_there_is_one(): void
    {
        // The full name titles the page and the nickname sits above it.
        // display_name is not used here: it PREFERS a nickname and falls back
        // to the first name alone, which is right beside an avatar and wrong
        // at the head of a profile. Untested while this was Blade, so the
        // eyebrow could have read "Player" for everybody and nothing would
        // have said so.
        $this->player->update(['nickname' => 'Wolf']);

        $props = $this->props($this->other);

        $this->assertSame('Wolf', $props['eyebrow']);
        $this->assertSame('Wanda Reeve', $props['title']);

        $this->player->update(['nickname' => null]);

        $this->assertSame('Player', $this->props($this->other)['eyebrow']);
    }

    public function test_only_five_results_are_sent_and_the_note_says_so(): void
    {
        // The cap was ->take(5) in the template and a ->count() on the whole
        // collection for the note. Both are the controller's now, and neither
        // was covered by anything -- changing the five to a six broke no test.
        for ($i = 0; $i < 6; $i++) {
            $tournament = PokerTournament::create([
                'name' => 'Night '.$i,
                'start_time' => now()->subDays(20 - $i),
                'venue_id' => Venue::first()->id,
                'season_id' => PokerSeason::first()->id,
            ]);

            PokerTournamentResult::create([
                'tournament_id' => $tournament->id, 'user_id' => $this->player->id,
                'player_name' => 'Wanda Reeve', 'place' => 4, 'points' => 10,
            ]);
        }

        $props = $this->props($this->other);

        $this->assertCount(5, $props['results'], 'The page is sent more rows than it draws.');
        $this->assertTrue($props['has_more_results']);
    }

    public function test_a_short_career_gets_no_note(): void
    {
        // "Showing latest 5" over a list of one is noise, and the same rule
        // the dashboard's upcoming note follows.
        $this->assertFalse($this->props($this->other)['has_more_results']);
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
        // Absent from the props, which is stronger than absent from the page:
        // the figure never reaches the browser at all, so no future markup
        // can show it by accident.
        $this->assertNull($this->props($this->other)['season']['venue_points_label']);

        $this->profile($this->other)->assertOk()->assertDontSee((string) self::TALLY);
    }

    public function test_the_owner_reads_their_own(): void
    {
        $this->assertSame(
            (string) self::TALLY,
            $this->props($this->player)['season']['venue_points_label']
        );
    }

    public function test_an_admin_reads_it_too(): void
    {
        $this->assertSame(
            (string) self::TALLY,
            $this->props($this->admin)['season']['venue_points_label']
        );
    }

    public function test_the_owner_is_offered_their_dashboard(): void
    {
        // The page is not a replacement for it -- the dashboard has the diary.
        $this->assertTrue($this->props($this->player)['is_self']);
        $this->assertFalse($this->props($this->other)['is_self']);

        // And the page hangs the link on that flag rather than on anything of
        // its own, so the two cannot come apart.
        $this->assertStringContainsString(
            '<template v-if="is_self" #actions>',
            (string) file_get_contents(resource_path('js/Pages/Players/Show.vue'))
        );
    }

    public function test_a_player_with_nothing_yet_still_has_a_page(): void
    {
        $newcomer = $this->makePlayer('Newly', 'Joined');

        $props = $this->props($this->other, $newcomer);

        $this->assertSame('Newly Joined', $props['title']);
        $this->assertSame([], $props['results']);

        // The empty state is the page's answer to an empty list.
        $this->assertStringContainsString(
            'title="No results recorded yet."',
            (string) file_get_contents(resource_path('js/Pages/Players/Show.vue'))
        );
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

        $props = $this->actingAs($this->admin)->get(route('users.index'))
            ->assertOk()->viewData('page')['props'];

        // The two tables are two props now, so there is nothing to slice.
        //
        // The old version cut the HTML in half at the search box, and its
        // comment says why: the main table lists EVERYONE, pending accounts
        // included, so a page-wide search for the pending player's link found
        // the one in the LOWER table and said nothing about the queue above --
        // unlinking the queue left it green until it was scoped. Separate
        // props cannot be confused for each other.
        $this->assertContains(
            $pending->id,
            collect($props['pending'])->pluck('id')->all(),
            'The awaiting-approval queue does not list the pending player.'
        );

        $this->assertContains(
            $this->player->id,
            collect($props['users']['data'])->pluck('id')->all(),
            'The main table does not list the player.'
        );

        // Both tables reach the figures through <PlayerLink>, which builds
        // /players/{id} from the id above, and both keep a View action to the
        // account: the two answer different questions and neither replaces the
        // other.
        $page = file_get_contents(resource_path('js/Pages/Users/Index.vue'));

        $this->assertSame(2, substr_count($page, '<PlayerLink :player-id='),
            'Both tables must link their names to the player\'s figures.');
        $this->assertSame(2, substr_count($page, '<Action icon="view" label="View"'),
            'Both tables must keep a View action to the account.');
    }

    public function test_an_unranked_player_is_told_so_in_the_third_person(): void
    {
        // The panel's copy addresses the viewer on the dashboard -- "Enter a
        // tournament to be ranked" -- which is the wrong thing to say about
        // somebody else.
        $newcomer = $this->makePlayer('Newly', 'Joined');

        // `self` is what chooses between the two sentences, and it is the
        // controller's answer rather than the panel's: a person reading their
        // own figures is told what to DO, a person reading somebody else's is
        // told what is true.
        $this->assertFalse($this->props($this->other, $newcomer)['self']);
        $this->assertTrue($this->props($newcomer, $newcomer)['self']);

        // Both sentences live in the panel, one per branch.
        $panel = (string) file_get_contents(resource_path('js/Components/SeasonPanel.vue'));

        $this->assertStringContainsString('A player is ranked once they enter a tournament.', $panel);
        $this->assertStringContainsString('Enter a tournament to be ranked.', $panel);
    }
}
