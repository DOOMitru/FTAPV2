<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ordering the season standings.
 *
 * One order since 2026-11-14: the season's rank, which is PokerSeason::rankings()
 * -- points, venue points breaking a tie, a tie on both sharing the place. The
 * Points/Rank toggle went with the per-event average it switched to, because
 * once rank means points the two orders are the same order.
 *
 * The rule itself is pinned in SeasonRankingsTest. What is pinned here is that
 * this page READS it, rather than sorting on its own and agreeing by luck.
 */
class SeasonStandingsOrderTest extends TestCase
{
    use RefreshDatabase;

    private PokerSeason $season;

    /** @var array<string, User> */
    private array $players = [];

    protected function setUp(): void
    {
        parent::setUp();

        PointsStructure::create(['place' => 1, 'points' => 500]);
        PointsStructure::create(['place' => 2, 'points' => 360]);
        PointsStructure::create(['place' => 3, 'points' => 220]);

        $this->season = PokerSeason::create([
            'name' => 'Season 9', 'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(), 'is_current' => true,
        ]);

        $venue = Venue::create(['name' => 'Hall', 'address' => '1 St'])->id;

        $nights = collect(range(1, 4))->map(fn ($i) => PokerTournament::create([
            'name' => "Night {$i}", 'start_time' => now()->subDays(10 - $i),
            'venue_id' => $venue, 'season_id' => $this->season->id,
        ]));

        $entries = [
            'Grinder' => [1, 2, 3],
            'Sharp' => [3, 4],
            // Entered three nights, finished two: night four is still in play.
            // This is what separates "points per ENTRY", which is the
            // dashboard's rank, from "points per FINISH", which is not.
            'Middling' => [1, 2, 4],
            'Oneshot' => [1],
        ];

        foreach ($entries as $name => $plays) {
            $user = User::factory()->create([
                'first_name' => $name, 'last_name' => 'Player', 'approval_status' => 'approved',
            ]);
            $this->players[$name] = $user;

            foreach ($plays as $n) {
                PokerTournamentRegistrant::create([
                    'tournament_id' => $nights[$n - 1]->id, 'user_id' => $user->id,
                    'player_name' => $name.' Player', 'registered_at' => now(),
                ]);
            }
        }

        // Results after every entry: registering a player shifts and reprices
        // results already recorded, so interleaving the two would rewrite the
        // scores this fixture is built on.
        $finishes = [
            [1, 'Oneshot', 1, 400], [1, 'Grinder', 2, 360], [1, 'Middling', 3, 220],
            [2, 'Grinder', 1, 500], [2, 'Middling', 2, 360],
            [3, 'Sharp', 1, 500], [3, 'Grinder', 2, 360],
            [4, 'Sharp', 1, 500],
        ];

        foreach ($finishes as [$night, $who, $place, $points]) {
            PokerTournamentResult::create([
                'tournament_id' => $nights[$night - 1]->id,
                'user_id' => $this->players[$who]->id,
                'player_name' => $who.' Player',
                'place' => $place, 'points' => $points,
            ]);
        }

        // Totals, and so the order:
        //
        //   Grinder  1220
        //   Sharp    1000
        //   Middling  580   (two finishes, three entries)
        //   Oneshot    400
        //
        // It was built so that points per entry would order them differently,
        // which is how the old Rank toggle was tested. That disagreement is now
        // what a stale ?order=rank link would have shown, and must not.
    }

    private function standings(?string $order = null, ?User $viewer = null)
    {
        $url = $order === null
            ? route('seasons.show', $this->season)
            : route('seasons.show', ['season' => $this->season, 'order' => $order]);

        return $this->actingAs($viewer ?? $this->admin())->get($url)->assertOk();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    /**
     * The standings the page is sent.
     *
     * The page is Vue, so the row map the view used to be handed is a prop and
     * every figure in it is a finished label. The ORDER is what most of this
     * file is about, and a list of props preserves it exactly.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function rows($response): \Illuminate\Support\Collection
    {
        return collect($response->viewData('page')['props']['standings']);
    }

    /** @return array<int, string> the player names, in the order the table lists them */
    private function names($response): array
    {
        // shown_name is what the table prints: the nickname where there is
        // one, the full name otherwise. These players have none.
        return $this->rows($response)->pluck('shown_name')->all();
    }

    /** The page's own markup, for the questions that are about the template. */
    private function page(): string
    {
        return (string) file_get_contents(resource_path('js/Pages/Poker/Seasons/Show.vue'));
    }

    /**
     * A player who enters a night of their own and scores $points there.
     *
     * Their own night, because registering into a night that already has
     * results shifts and reprices them -- this fixture's scores included.
     */
    private function newcomer(string $name, int $points, int $venuePoints = 0): User
    {
        $user = User::factory()->create([
            'first_name' => $name, 'last_name' => 'Player', 'approval_status' => 'approved',
        ]);
        $this->players[$name] = $user;

        $night = PokerTournament::create([
            'name' => "{$name}'s night", 'start_time' => now()->subDays(2),
            'venue_id' => Venue::first()->id, 'season_id' => $this->season->id,
        ]);

        PokerTournamentRegistrant::create([
            'tournament_id' => $night->id, 'user_id' => $user->id,
            'player_name' => $name.' Player', 'registered_at' => now(),
        ]);

        PokerTournamentResult::create([
            'tournament_id' => $night->id, 'user_id' => $user->id,
            'player_name' => $name.' Player', 'place' => 1, 'points' => $points,
        ]);

        if ($venuePoints > 0) {
            \App\Models\VenuePoints::create([
                'venue_id' => Venue::first()->id, 'user_id' => $user->id, 'user_name' => $name.' Player',
                'event_date' => now()->subDays(2)->toDateString(), 'amount' => $venuePoints,
                'season_id' => $this->season->id,
            ]);
        }

        return $user;
    }

    public function test_standings_follow_the_season_ranking(): void
    {
        $rows = $this->rows($this->standings());

        $this->assertSame(
            ['Grinder Player', 'Sharp Player', 'Middling Player', 'Oneshot Player'],
            $rows->pluck('shown_name')->all()
        );
        $this->assertSame([1, 2, 3, 4], $rows->pluck('rank')->all());

        // The same board, row for row -- not a second sort that happens to agree.
        $this->assertSame(
            $this->season->rankings()->pluck('user_id')->all(),
            $rows->pluck('player_id')->all()
        );
    }

    public function test_a_shared_place_is_shown_on_both_rows(): void
    {
        // Level with Middling on points, and neither holds venue points.
        $this->newcomer('Twin', 580);

        $rows = $this->rows($this->standings());

        $this->assertSame([1, 2, 3, 3, 5], $rows->pluck('rank')->all());
        // Listed by recorded name within the tie.
        $this->assertSame('Middling Player', $rows[2]['shown_name']);
        $this->assertSame('Twin Player', $rows[3]['shown_name']);
    }

    public function test_a_tie_on_points_goes_to_more_venue_points(): void
    {
        $this->newcomer('Twin', 580, venuePoints: 3);

        $rows = $this->rows($this->standings());

        $this->assertSame('Twin Player', $rows[2]['shown_name']);
        $this->assertSame([1, 2, 3, 4, 5], $rows->pluck('rank')->all());
    }

    public function test_a_player_on_zero_points_is_not_listed(): void
    {
        $this->newcomer('Blank', 0);

        $response = $this->standings();

        $this->assertNotContains('Blank Player', $this->names($response));

        // The tile is counted from the table, so the two keep agreeing.
        $this->assertSame('4', $response->viewData('page')['props']['players_label']);
    }

    public function test_there_is_no_order_toggle(): void
    {
        $props = $this->standings()->viewData('page')['props'];

        foreach (['order', 'order_options', 'figure_heading'] as $gone) {
            $this->assertArrayNotHasKey($gone, $props);
        }

        $this->assertStringNotContainsString('season-show__order', $this->page());
        $this->assertStringNotContainsString('order_options', $this->page());
    }

    public function test_a_stale_rank_link_still_loads(): void
    {
        // Bookmarks and shared links carry ?order=rank. They load the page in
        // its one order -- not the per-event order they used to mean.
        foreach (['rank', 'points', '', 'points; DROP TABLE'] as $stale) {
            $this->assertSame($this->names($this->standings()), $this->names($this->standings($stale)));
        }
    }

    public function test_the_standings_rank_is_the_dashboards_rank(): void
    {
        $this->newcomer('Twin', 580);

        foreach ($this->rows($this->standings()) as $row) {
            $this->assertSame(
                PokerSeason::figuresFor($this->season, $row['player_id'])['rank'],
                $row['rank'],
                "{$row['shown_name']} has one rank on this page and another on their dashboard."
            );
        }
    }

    public function test_the_badge_carries_the_place_and_nothing_else(): void
    {
        $rows = $this->rows($this->standings());

        // The average that used to sit behind the badge is gone, and with it
        // the label and the tooltip that carried it.
        $this->assertArrayNotHasKey('rank_label', $rows[0]);
        $this->assertArrayNotHasKey('rank_title', $rows[0]);
        $this->assertStringNotContainsString('rank_title', $this->page());
    }

    public function test_the_meter_shows_points_against_the_leader(): void
    {
        $rows = $this->rows($this->standings());

        $this->assertSame(['1,220', '1,000', '580', '400'], $rows->pluck('meter_value_label')->all());
        $this->assertSame(1220, $rows[0]['meter_value']);
        $this->assertSame(1220, $rows[0]['meter_max'], "The leader's bar is full.");
        $this->assertSame('Points for Sharp Player', $rows[1]['meter_label']);
    }

    public function test_the_stat_run_is_sized_to_its_content(): void
    {
        // As equal thirds, "137 VENUE PTS" was given less width than it needed
        // while "1 WON" held slack it could not lend, so the longest stat
        // wrapped and made that row taller than its neighbours.
        $css = file_get_contents(resource_path('css/4-pages/_season-show.css'));

        $this->assertStringContainsString(
            'grid-template-columns: auto auto auto minmax(0, 1fr) auto;',
            $css,
            'The two short stat columns size to content; the venue column keeps the rest.'
        );
    }

    public function test_venue_points_stay_private(): void
    {
        // The rows are now ordered by a figure that includes venue points;
        // the rule that a stranger is not SENT anyone's tally has to survive
        // that.
        $rows = $this->rows($this->standings(viewer: $this->players['Oneshot']));

        $this->assertNull($rows->firstWhere('shown_name', 'Grinder Player')['venue_points_label']);
    }

    public function test_the_empty_table_is_true_when_finishes_exist_but_nobody_scored(): void
    {
        // Players on nil are not listed, so a night where every finish so far
        // is worth nothing leaves the table empty while a tournament is plainly
        // under way. "No results yet" would be false then; the copy has to be
        // true either way.
        $page = $this->page();

        $this->assertStringContainsString('title="Nobody has scored yet"', $page);
        $this->assertStringContainsString('Standings appear once a player scores points this season.', $page);
        $this->assertStringNotContainsString('title="No results yet"', $page);
    }
}
