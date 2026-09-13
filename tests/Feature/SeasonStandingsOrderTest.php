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
 * Two ways of reading the same season: the running TOTAL, and points per
 * tournament entered -- the figure the player's dashboard calls Season Rank,
 * which does not punish somebody who missed half the nights for missing them.
 *
 * The fixture is built so the two orders DISAGREE. A player who entered one
 * night and won it leads on average and comes fourth on total; a player who
 * entered all three leads on total and comes second on average. Every
 * assertion here would pass on a season where the two agree, which is why it
 * is worth saying that this one does not.
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

        // Totals, and the two orders they produce:
        //
        //   Grinder  1220 over 3 entries = 406.7
        //   Sharp    1000 over 2 entries = 500.0
        //   Middling  580 over 3 entries = 193.3   (two finishes, three entries)
        //   Oneshot    400 over 1 entry  = 400.0
        //
        //   by points: Grinder, Sharp, Middling, Oneshot
        //   by rank:   Sharp, Grinder, Oneshot, Middling
        //
        // Every position moves. The rank leader's average (500.0) is also
        // deliberately unlike their total (1000), so a meter measured against
        // the wrong figure cannot draw the leading bar full by coincidence.
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

    /** @return array<int, string> the player names, in the order the table lists them */
    private function names($response): array
    {
        return collect($response->viewData('leaderboard'))->pluck('player_name')->all();
    }

    public function test_the_default_is_the_season_total(): void
    {
        $response = $this->standings();

        $this->assertSame('points', $response->viewData('order'));
        $this->assertSame(
            ['Grinder Player', 'Sharp Player', 'Middling Player', 'Oneshot Player'],
            $this->names($response)
        );
    }

    public function test_rank_orders_by_points_per_event(): void
    {
        // Every position moves, so no partial ordering can pass by accident.
        $response = $this->standings('rank');

        $this->assertSame('rank', $response->viewData('order'));
        $this->assertSame(
            ['Sharp Player', 'Grinder Player', 'Oneshot Player', 'Middling Player'],
            $this->names($response)
        );
    }

    public function test_the_rank_order_is_the_dashboards_rank_order(): void
    {
        // Not "sorted by some average" -- sorted by the one definition the
        // dashboard and the landing page's rank card already read. A second
        // division written here is a second definition waiting to drift.
        $expected = $this->season->rankings()->pluck('user_id')->all();

        $actual = collect($this->standings('rank')->viewData('leaderboard'))
            ->pluck('user.id')->all();

        $this->assertSame($expected, $actual);
    }

    public function test_the_ratio_matches_the_dashboards_figure(): void
    {
        $rows = collect($this->standings('rank')->viewData('leaderboard'))->keyBy('player_name');

        $this->assertEqualsWithDelta(500.0, $rows['Sharp Player']['ratio'], 0.001);
        $this->assertEqualsWithDelta(1220 / 3, $rows['Grinder Player']['ratio'], 0.001);
        $this->assertSame(3, $rows['Grinder Player']['events']);

        // The divisor is ENTRIES, not finishes. Middling entered three nights
        // and finished two, so 580/3 and not 580/2 -- dividing by the results
        // already in hand would read 290.0 and put them two places higher.
        $this->assertSame(3, $rows['Middling Player']['events']);
        $this->assertSame(2, $rows['Middling Player']['played']);
        $this->assertEqualsWithDelta(580 / 3, $rows['Middling Player']['ratio'], 0.001);
    }

    public function test_a_tie_on_the_average_is_broken_by_the_total(): void
    {
        // Two players on 360.0: one from a single night, one from two. The
        // order must not depend on what the database happened to return.
        $night = PokerTournament::create([
            'name' => 'Tie Night A', 'start_time' => now()->subDays(4),
            'venue_id' => Venue::first()->id, 'season_id' => $this->season->id,
        ]);

        foreach (['Even' => 1, 'Steven' => 1] as $name => $_) {
            $user = User::factory()->create([
                'first_name' => $name, 'last_name' => 'Player', 'approval_status' => 'approved',
            ]);
            $this->players[$name] = $user;

            PokerTournamentRegistrant::create([
                'tournament_id' => $night->id, 'user_id' => $user->id,
                'player_name' => $name.' Player', 'registered_at' => now(),
            ]);
        }

        // Same average as Grinder's 406.7? No -- give both exactly 400.0, one
        // over a single night and one over two, so only the total separates
        // them.
        $second = PokerTournament::create([
            'name' => 'Tie Night B', 'start_time' => now()->subDays(3),
            'venue_id' => Venue::first()->id, 'season_id' => $this->season->id,
        ]);

        PokerTournamentRegistrant::create([
            'tournament_id' => $second->id, 'user_id' => $this->players['Steven']->id,
            'player_name' => 'Steven Player', 'registered_at' => now(),
        ]);

        PokerTournamentResult::create([
            'tournament_id' => $night->id, 'user_id' => $this->players['Even']->id,
            'player_name' => 'Even Player', 'place' => 1, 'points' => 400,
        ]);
        PokerTournamentResult::create([
            'tournament_id' => $night->id, 'user_id' => $this->players['Steven']->id,
            'player_name' => 'Steven Player', 'place' => 2, 'points' => 400,
        ]);
        PokerTournamentResult::create([
            'tournament_id' => $second->id, 'user_id' => $this->players['Steven']->id,
            'player_name' => 'Steven Player', 'place' => 1, 'points' => 400,
        ]);

        $names = $this->names($this->standings('rank'));

        $this->assertLessThan(
            array_search('Even Player', $names, true),
            array_search('Steven Player', $names, true),
            'On the same average, the larger total goes first.'
        );
    }

    public function test_an_unknown_order_falls_back_to_the_total(): void
    {
        // It arrives from a query string, where a stale link or a typo is
        // ordinary and a 500 is not.
        foreach (['', 'wins', 'points; DROP TABLE', '1'] as $garbage) {
            $response = $this->standings($garbage);

            $this->assertSame('points', $response->viewData('order'));
            $this->assertSame('Grinder Player', $this->names($response)[0]);
        }
    }

    public function test_the_column_header_names_the_figure_on_show(): void
    {
        $this->standings()->assertSee('>Pts</th>', false);
        $this->standings('rank')->assertSee('>Per event</th>', false);
        $this->standings('rank')->assertDontSee('>Pts</th>', false);
    }

    /**
     * The meter value cells, in table order, as [text, title].
     *
     * @return array<int, array{0: string, 1: ?string}>
     */
    private function meterValues(string $html): array
    {
        preg_match_all(
            '/<span class="meter__value"(?<attrs>[^>]*)>(?<text>.*?)<\/span>/s',
            $html, $matches, PREG_SET_ORDER
        );

        return array_map(function (array $m) {
            preg_match('/title="(?<title>[^"]*)"/', $m['attrs'], $t);

            return [trim($m['text']), $t['title'] ?? null];
        }, $matches);
    }

    /**
     * The rank badges, in table order, as [text, title].
     *
     * @return array<int, array{0: string, 1: ?string}>
     */
    private function badges(string $html): array
    {
        preg_match_all(
            '/<span (?<attrs>[^>]*class="rank[^"]*"[^>]*)>(?<text>.*?)<\/span>/s',
            $html, $matches, PREG_SET_ORDER
        );

        return array_map(function (array $m) {
            preg_match('/title="(?<title>[^"]*)"/', $m['attrs'], $t);

            return [trim($m['text']), $t['title'] ?? null];
        }, $matches);
    }

    public function test_rank_order_puts_the_position_in_the_badge(): void
    {
        // "#2" is the thing a reader is looking for; 406.7 is the working out.
        $badges = $this->badges($this->standings('rank')->getContent());

        $this->assertSame(
            ['#1', '#2', '#3', '#4'],
            array_column($badges, 0),
            'Every row shows its position, numbered down the table.'
        );
    }

    public function test_the_average_is_on_the_badge(): void
    {
        // Hovering the position gives the figure behind it, to one decimal:
        // 406.7 and 406.6 are different positions in this table; 407 and 407
        // are not. The dashboard prints the same figure the same way.
        $badges = $this->badges($this->standings('rank')->getContent());

        $this->assertSame('500.0 points per event', $badges[0][1]);
        $this->assertSame('406.7 points per event', $badges[1][1]);
    }

    public function test_the_position_is_not_printed_twice_on_a_row(): void
    {
        // The badge carries it; the meter beside it keeps the bar and drops
        // its number. Printing "#2" in both says nothing twice and costs the
        // bar the width to say it in.
        // Scoped to the standings. The Venues panel further down the page
        // draws meters of its own, so a count over the whole document reports
        // five bars for a four-row table.
        $html = $this->standings('rank')->getContent();
        $start = strpos($html, 'season-show__standings');
        $table = substr($html, $start, strpos($html, '</table>', $start) - $start);

        $this->assertSame([], $this->meterValues($table), 'The meter should show no value.');
        $this->assertSame(4, substr_count($table, 'meter__track'), 'The bars are still drawn.');
    }

    public function test_the_average_is_reachable_without_a_pointer(): void
    {
        // A title attribute is not reachable by keyboard and is announced
        // inconsistently, so the figure is in the meter's accessible name as
        // well. The tooltip is the sighted-pointer copy of it, not the only one.
        $this->standings('rank')->assertSee(
            'aria-label="Rank #2, 406.7 points per event, for Grinder Player"',
            false
        );
    }

    public function test_points_order_still_shows_the_total_and_a_plain_badge(): void
    {
        $html = $this->standings()->getContent();

        $this->assertSame(
            ['1,220', '1,000', '580', '400'],
            array_column($this->meterValues($html), 0)
        );

        // No hash and nothing to reveal on hover: in this order the badge is a
        // row number beside the figure, not the figure itself.
        $badges = $this->badges($html);

        $this->assertSame(['1', '2', '3', '4'], array_column($badges, 0));
        $this->assertSame([null, null, null, null], array_column($badges, 1));
    }

    public function test_the_meter_measures_against_the_leader_in_that_figure(): void
    {
        // Measuring a ratio against a points total would draw every bar at
        // nothing -- 500 out of 1220 -- and the table would look broken in
        // exactly the mode this feature adds.
        foreach (['points', 'rank'] as $order) {
            $html = $this->standings($order === 'points' ? null : $order)->getContent();

            $first = strpos($html, 'season-show__meter-cell');
            $this->assertNotFalse($first);

            $this->assertStringContainsString(
                '--meter-fill: 100%',
                substr($html, $first, 600),
                "The leading row's bar should be full in {$order} order."
            );
        }
    }

    public function test_the_control_marks_the_current_order_and_offers_the_other(): void
    {
        $html = $this->standings('rank')->getContent();

        $this->assertStringContainsString('season-show__order-option--current', $html);
        $this->assertMatchesRegularExpression(
            '/order=rank"\s+class="season-show__order-option season-show__order-option--current"/',
            $html,
            'The current option is the one marked.'
        );
        $this->assertStringContainsString('order=points', $html, 'The other order stays reachable.');
        // Counted as whole class attributes. \bseason-show__order-option\b also
        // matches inside season-show__order-option--current, so a word-boundary
        // count reports three options where the control has two.
        $this->assertSame(2, preg_match_all('/class="season-show__order-option[^"]*"/', $html));
    }

    public function test_venue_points_stay_private_in_either_order(): void
    {
        // The row map was rewritten to carry the ratio; the rule it already
        // enforced has to survive that.
        foreach ([null, 'rank'] as $order) {
            $rows = collect($this->standings($order, $this->players['Oneshot'])
                ->viewData('leaderboard'));

            $this->assertNull(
                $rows->firstWhere('player_name', 'Grinder Player')['venue_points'],
                "Another player's tally reached the view in ".($order ?? 'points').' order.'
            );
        }
    }
}
