<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The league's rule about venue points, enforced across the whole application.
 *
 * A player's venue tally is read by that player and by admins. Nobody else,
 * on any page. The one exception is the finale QUALIFICATION THRESHOLD, which
 * is a published target rather than anybody's tally -- it is printed on the
 * landing page for people who have not signed in at all.
 *
 * Checked by walking every registered GET route rather than by naming the
 * pages that show tallies today. A test that named them would go on passing
 * the day somebody adds a page it does not know about, which is exactly the
 * day this rule needs a test.
 */
class VenuePointsPrivacyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The owner's tally. Distinctive so it can be searched for, and searched
     * for in both forms: views print venue points raw in one place and through
     * number_format in another, and a rule enforced against only one spelling
     * is enforced against neither.
     */
    private const TALLY = 91827;

    /** Routes a plain GET cannot exercise. */
    private const SKIPPED = ['up', 'storage/{path}'];

    private User $owner;

    private User $snooper;

    private User $admin;

    private PokerSeason $season;

    /** @var array<string, string> */
    private array $bindings = [];

    protected function setUp(): void
    {
        parent::setUp();

        PointsStructure::create(['place' => 1, 'points' => 100]);

        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        $this->season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
            'finale_points_required' => 100,
            'finale_wins_required' => 1,
            'finale_venue_points_required' => 50,
        ]);

        $tournament = PokerTournament::create([
            'name' => 'Weekly Freezeout',
            'start_time' => now()->subDays(3),
            'venue_id' => $venue->id,
            'season_id' => $this->season->id,
        ]);

        $this->admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        // Every entry before any result. Registering a player shifts the
        // places of results already recorded and reprices them, so entering
        // the second player after the first had finished would have knocked
        // the owner down to second with zero points -- and the qualification
        // assertion below would have been failing for a reason that has
        // nothing to do with venue points.
        $this->owner = $this->enter($tournament, 'Wanda', 'Reeve');
        $this->snooper = $this->enter($tournament, 'Baltazar', 'Whitlock');

        $this->score($tournament, $this->owner, place: 1, points: 500);
        $this->score($tournament, $this->snooper, place: 2, points: 360);

        // The owner's tally, and one of the snooper's own so the walk cannot
        // pass merely because no venue points exist for anybody.
        $tally = VenuePoints::create([
            'venue_id' => $venue->id, 'user_id' => $this->owner->id, 'user_name' => 'Wanda',
            'event_date' => now()->subDays(3)->toDateString(), 'amount' => self::TALLY,
            'season_id' => $this->season->id,
        ]);

        VenuePoints::create([
            'venue_id' => $venue->id, 'user_id' => $this->snooper->id, 'user_name' => 'Baltazar',
            'event_date' => now()->subDays(3)->toDateString(), 'amount' => 64,
            'season_id' => $this->season->id,
        ]);

        $sponsor = Sponsor::create(['name' => 'Sponsor', 'logo_path' => 'sponsor-logos/s.png']);

        $this->bindings = [
            'user' => $this->owner->id,
            // The OWNER, so the walk actually exercises the profile page's
            // copy of the rule rather than a page with no tally on it.
            'player' => $this->owner->id,
            'venue' => $venue->id,
            'season' => $this->season->id,
            'tournament' => $tournament->id,
            'points_structure' => PointsStructure::first()->id,
            'registrant' => PokerTournamentRegistrant::first()->id,
            'result' => PokerTournamentResult::first()->id,
            'venue_point' => $tally->id,
            'sponsor' => $sponsor->id,
            'token' => 'privacy-test-token',
            'id' => $this->owner->id,
            'hash' => sha1($this->owner->email),
        ];
    }

    /** The standings only carry players who entered, so everybody enters. */
    private function enter(PokerTournament $t, string $first, string $last): User
    {
        $user = User::factory()->create([
            'first_name' => $first, 'last_name' => $last, 'approval_status' => 'approved',
        ]);

        PokerTournamentRegistrant::create([
            'tournament_id' => $t->id, 'user_id' => $user->id,
            'player_name' => "$first $last", 'registered_at' => now(),
        ]);

        return $user;
    }

    private function score(PokerTournament $t, User $user, int $place, int $points): void
    {
        PokerTournamentResult::create([
            'tournament_id' => $t->id, 'user_id' => $user->id,
            'player_name' => $user->first_name.' '.$user->last_name,
            'place' => $place, 'points' => $points,
        ]);
    }

    /**
     * Every GET route, fetched as $viewer, as [uri => rendered body].
     *
     * Only 2xx HTML is collected: a redirect body or a 403 page is not
     * rendered view output and scanning it proves nothing.
     *
     * @return array<string, string>
     */
    private function walk(?User $viewer): array
    {
        $pages = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (in_array($uri, self::SKIPPED, true) || str_starts_with($uri, '_')) {
                continue;
            }

            $unbound = false;
            $resolved = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use (&$unbound) {
                if (! array_key_exists($m[1], $this->bindings)) {
                    $unbound = true;

                    return $m[0];
                }

                return $this->bindings[$m[1]];
            }, $uri);

            $this->assertFalse(
                $unbound,
                "No fixture for a parameter of {$uri}. Add it to \$bindings -- an unreachable "
                .'route is a route this rule is not being enforced on.'
            );

            $response = $viewer === null
                ? $this->get('/'.ltrim($resolved, '/'))
                : $this->actingAs($viewer)->get('/'.ltrim($resolved, '/'));

            $status = $response->getStatusCode();

            if ($status < 200 || $status >= 300) {
                continue;
            }

            if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                continue;
            }

            $pages[$uri] = $response->getContent();
        }

        return $pages;
    }

    /**
     * The body with every opaque identifier removed.
     *
     * ULIDs, CSRF tokens, session values and asset hashes are long runs of
     * letters and digits, and a five-digit number turns up inside one often
     * enough to fail this test for no reason. Nothing that is actually a
     * rendered tally survives in such a run: it is printed between tags.
     */
    private function readable(string $html): string
    {
        return preg_replace('/[A-Za-z0-9_-]{20,}/', '', $html);
    }

    /** @param array<string, string> $pages */
    private function pagesShowingTheTally(array $pages): array
    {
        $found = [];

        foreach ($pages as $uri => $html) {
            $body = $this->readable($html);

            foreach ([(string) self::TALLY, number_format(self::TALLY)] as $spelling) {
                if (str_contains($body, $spelling)) {
                    $found[] = $uri.' (as "'.$spelling.'")';
                }
            }
        }

        return $found;
    }

    public function test_no_page_in_the_application_shows_one_players_tally_to_another(): void
    {
        $pages = $this->walk($this->snooper);

        // The walk must have actually rendered the pages that carry tallies,
        // or it proves nothing. Both of these are where a leak would surface.
        $this->assertArrayHasKey('seasons/{season}', $pages, 'The standings were never rendered.');
        $this->assertArrayHasKey('dashboard', $pages, 'The dashboard was never rendered.');

        $this->assertSame([], $this->pagesShowingTheTally($pages), implode("\n  ", array_merge(
            ["A player read another player's venue tally on:"],
            $this->pagesShowingTheTally($pages)
        )));
    }

    public function test_no_page_shows_a_tally_to_a_guest(): void
    {
        $pages = $this->walk(null);

        $this->assertNotEmpty($pages, 'A guest should still reach the public pages.');
        $this->assertSame([], $this->pagesShowingTheTally($pages));
    }

    public function test_the_owner_reads_their_own_tally(): void
    {
        // The positive control. Without it the rule is satisfied by a bug that
        // hides venue points from everybody, and every assertion above passes.
        $this->assertNotSame(
            [],
            $this->pagesShowingTheTally($this->walk($this->owner)),
            'The owner must still be able to read their own venue points.'
        );
    }

    public function test_an_admin_reads_the_tally(): void
    {
        $this->assertNotSame(
            [],
            $this->pagesShowingTheTally($this->walk($this->admin)),
            'An admin awards venue points and must be able to read them.'
        );
    }

    public function test_the_finale_threshold_is_public(): void
    {
        // The stated exception: a target, not a tally. It is on the landing
        // page, which a guest reaches without signing in.
        $this->get('/')->assertOk()->assertSee('Venue points');
    }

    public function test_the_standings_withhold_the_figure_from_the_data_not_just_the_template(): void
    {
        // Gating the column in Blade stops it being rendered today. Leaving the
        // figure in the view data is what lets the next column, partial or
        // debug dump render it tomorrow.
        $rows = collect($this->actingAs($this->snooper)
            ->get(route('seasons.show', $this->season))->assertOk()->viewData('leaderboard'));

        $mine = $rows->firstWhere('user.id', $this->snooper->id);
        $theirs = $rows->firstWhere('user.id', $this->owner->id);

        $this->assertNotNull($theirs);
        $this->assertNull($theirs['venue_points'], "Another player's tally reached the view.");
        $this->assertSame(64, $mine['venue_points'], 'The viewer should still get their own.');
    }

    public function test_an_admin_gets_every_figure_in_the_data(): void
    {
        $rows = collect($this->actingAs($this->admin)
            ->get(route('seasons.show', $this->season))->assertOk()->viewData('leaderboard'));

        $this->assertSame(self::TALLY, $rows->firstWhere('user.id', $this->owner->id)['venue_points']);
        $this->assertSame(64, $rows->firstWhere('user.id', $this->snooper->id)['venue_points']);
    }

    public function test_the_verdict_survives_even_where_the_figure_does_not(): void
    {
        // Qualification is computed from the tally, and stays public: the
        // thresholds it is measured against are printed above the table.
        $rows = collect($this->actingAs($this->snooper)
            ->get(route('seasons.show', $this->season))->assertOk()->viewData('leaderboard'));

        $theirs = $rows->firstWhere('user.id', $this->owner->id);

        $this->assertTrue($theirs['qualified'], 'The owner meets all three thresholds.');
        $this->assertNull($theirs['venue_points']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function readers(): array
    {
        return [
            'the owner' => ['owner', 'owner', true],
            'an admin' => ['admin', 'owner', true],
            'another player' => ['snooper', 'owner', false],
            'an admin, for a tally with no owner' => ['admin', 'nobody', true],
            'a player, for a tally with no owner' => ['snooper', 'nobody', false],
            'a guest' => ['guest', 'owner', false],
            'a guest, for a tally with no owner' => ['guest', 'nobody', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('readers')]
    public function test_the_rule_itself(string $viewer, string $owner, bool $expected): void
    {
        // One definition, so no surface can hold a different opinion of it.
        $who = match ($viewer) {
            'guest' => null,
            'owner' => $this->owner,
            'admin' => $this->admin,
            'snooper' => $this->snooper,
        };

        $ownerId = $owner === 'nobody' ? null : $this->owner->id;

        $this->assertSame($expected, VenuePoints::readableBy($who, $ownerId));
    }
}
