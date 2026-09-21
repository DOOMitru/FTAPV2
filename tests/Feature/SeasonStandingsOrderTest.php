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

    public function test_the_default_is_the_season_total(): void
    {
        $response = $this->standings();

        $this->assertSame('points', $response->viewData('page')['props']['order']);
        $this->assertSame(
            ['Grinder Player', 'Sharp Player', 'Middling Player', 'Oneshot Player'],
            $this->names($response)
        );
    }

    public function test_rank_orders_by_points_per_event(): void
    {
        // Every position moves, so no partial ordering can pass by accident.
        $response = $this->standings('rank');

        $this->assertSame('rank', $response->viewData('page')['props']['order']);
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

        $actual = $this->rows($this->standings('rank'))->pluck('player_id')->all();

        $this->assertSame($expected, $actual);
    }

    public function test_the_ratio_matches_the_dashboards_figure(): void
    {
        // The raw figures come from rankings(), which is the one definition
        // the dashboard and the landing page read too. The props carry them
        // rounded to one decimal, and the division is what is under test here
        // rather than the rounding.
        $this->standings('rank');

        $rankings = $this->season->rankings()->keyBy('user_id');
        $id = fn (string $name) => $this->players[$name]->id;

        $this->assertEqualsWithDelta(500.0, $rankings[$id('Sharp')]['ratio'], 0.001);
        $this->assertEqualsWithDelta(1220 / 3, $rankings[$id('Grinder')]['ratio'], 0.001);
        $this->assertSame(3, $rankings[$id('Grinder')]['events']);

        // The divisor is ENTRIES, not finishes. Middling entered three nights
        // and finished two, so 580/3 and not 580/2 -- dividing by the results
        // already in hand would read 290.0 and put them two places higher.
        $this->assertSame(3, $rankings[$id('Middling')]['events']);
        $this->assertEqualsWithDelta(580 / 3, $rankings[$id('Middling')]['ratio'], 0.001);

        // And the table says the same, to the decimal it shows.
        $rows = $this->rows($this->standings('rank'))->keyBy('shown_name');

        $this->assertSame('2', $rows['Middling Player']['played_label']);
        $this->assertSame('193.3', $rows['Middling Player']['meter_value_label']);
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

            $this->assertSame('points', $response->viewData('page')['props']['order']);
            $this->assertSame('Grinder Player', $this->names($response)[0]);
        }
    }

    public function test_the_column_header_names_the_figure_on_show(): void
    {
        $heading = fn (?string $order) => $this->standings($order)
            ->viewData('page')['props']['figure_heading'];

        $this->assertSame('Points', $heading(null));
        $this->assertSame('Per event', $heading('rank'));

        // Drawn from that prop rather than from a second condition in the
        // template, which is what would let the two disagree.
        $this->assertStringContainsString('{{ figure_heading }}', $this->page());
    }

    /**
     * What each meter reads, in table order.
     *
     * These were parsed out of the rendered table -- the standings' meters
     * only, because the Venues panel draws meters of its own. The props carry
     * the standings alone, so there is nothing to cut.
     *
     * @return array<int, string>
     */
    private function meterValues($response): array
    {
        return $this->rows($response)->pluck('meter_value_label')->all();
    }

    /**
     * What each rank badge reads, in table order, as [label, title].
     *
     * @return array<int, array{0: string, 1: ?string}>
     */
    private function badges($response): array
    {
        return $this->rows($response)
            ->map(fn (array $row) => [$row['rank_label'] ?? (string) $row['rank'], $row['rank_title']])
            ->all();
    }

    public function test_rank_order_puts_the_position_in_the_badge(): void
    {
        // "#2" is the thing a reader is looking for; 406.7 is the working out.
        $badges = $this->badges($this->standings('rank'));

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
        $badges = $this->badges($this->standings('rank'));

        $this->assertSame('500.0 points per event', $badges[0][1]);
        $this->assertSame('406.7 points per event', $badges[1][1]);
    }

    public function test_the_position_is_not_printed_twice_on_a_row(): void
    {
        // The badge carries the position. The meter beside it carries the
        // average -- never the position again, which would say nothing twice
        // and cost the bar the width to say it in.
        $this->assertSame(
            ['500.0', '406.7', '400.0', '193.3'],
            $this->meterValues($this->standings('rank'))
        );

        // The badge carries the position and the meter carries the average, so
        // the two cells never print the same thing.
        $this->assertSame(
            ['#1', '#2', '#3', '#4'],
            array_column($this->badges($this->standings('rank')), 0)
        );
    }

    public function test_the_average_is_readable_without_hovering(): void
    {
        // A touch device fires no hover, so the badge's tooltip is unreachable
        // there -- and a phone is most of this league. The figure is therefore
        // rendered beside the bar and hidden only where the tooltip works.
        $this->assertStringContainsString('season-show__ratio', $this->page());
        $this->assertSame('406.7', $this->meterValues($this->standings('rank'))[1]);

        $css = file_get_contents(resource_path('css/4-pages/_season-show.css'));

        $this->assertStringContainsString(
            '.season-show__ratio .meter__value {'."\n".'    display: none;',
            $css,
            'The figure is hidden where the badge can be hovered for it.'
        );

        $this->assertStringContainsString(
            '@media (hover: none), (max-width: 48rem) {',
            $css,
            'Either condition brings it back: no pointer at any width, or the phone layout.'
        );
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

    public function test_the_average_is_reachable_without_a_pointer(): void
    {
        // A title attribute is not reachable by keyboard and is announced
        // inconsistently, so the figure is in the meter's accessible name as
        // well. The tooltip is the sighted-pointer copy of it, not the only one.
        $this->assertSame(
            'Rank #2, 406.7 points per event, for Grinder Player',
            $this->rows($this->standings('rank'))[1]['meter_label']
        );

        // And the meter is given it as its accessible name.
        $this->assertStringContainsString(':label="row.meter_label"', $this->page());
    }

    public function test_points_order_still_shows_the_total_and_a_plain_badge(): void
    {
        $response = $this->standings();

        $this->assertSame(['1,220', '1,000', '580', '400'], $this->meterValues($response));

        // No hash and nothing to reveal on hover: in this order the badge is a
        // row number beside the figure, not the figure itself.
        $badges = $this->badges($response);

        $this->assertSame(['1', '2', '3', '4'], array_column($badges, 0));
        $this->assertSame([null, null, null, null], array_column($badges, 1));
    }

    public function test_the_meter_measures_against_the_leader_in_that_figure(): void
    {
        // Measuring a ratio against a points total would draw every bar at
        // nothing -- 500 out of 1220 -- and the table would look broken in
        // exactly the mode this feature adds.
        foreach (['points', 'rank'] as $order) {
            $rows = $this->rows($this->standings($order === 'points' ? null : $order));

            // The bar's length is value over max, and Meter does that division.
            // What matters here is that the max is the leader's figure IN THIS
            // ORDER: measuring a ratio against a points total would draw every
            // bar at nothing -- 500 out of 1220 -- and the table would look
            // broken in exactly the mode this feature adds.
            $this->assertSame(
                $rows[0]['meter_value'],
                $rows[0]['meter_max'],
                "The leading row's bar should be full in {$order} order."
            );
        }
    }

    public function test_the_control_marks_the_current_order_and_offers_the_other(): void
    {
        $options = collect(
            $this->standings('rank')->viewData('page')['props']['order_options']
        );

        // Two options, one marked, and the other still reachable by its own
        // address -- the order is part of the URL so it survives a reload.
        $this->assertCount(2, $options);

        $current = $options->firstWhere('current', true);

        $this->assertNotNull($current);
        $this->assertSame('rank', $current['value']);
        $this->assertStringContainsString('order=rank', $current['href']);

        $other = $options->firstWhere('current', false);

        $this->assertSame('points', $other['value']);
        $this->assertStringContainsString('order=points', $other['href']);

        // And the marker class is applied from that flag.
        $this->assertStringContainsString(
            "'season-show__order-option--current': option.current",
            $this->page()
        );
    }

    public function test_venue_points_stay_private_in_either_order(): void
    {
        // The row map was rewritten to carry the ratio; the rule it already
        // enforced has to survive that.
        foreach ([null, 'rank'] as $order) {
            $rows = $this->rows($this->standings($order, $this->players['Oneshot']));

            $this->assertNull(
                $rows->firstWhere('shown_name', 'Grinder Player')['venue_points_label'],
                "Another player's tally reached the page in ".($order ?? 'points').' order.'
            );
        }
    }
}
