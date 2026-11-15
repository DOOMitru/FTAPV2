<?php

namespace Tests\Feature;

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
 * The season's board: PokerSeason::rankings(), the one definition of a rank.
 *
 * Owner's rule, 2026-11-14 (docs/superpowers/specs/2026-11-14-points-rank-design.md):
 * a player is ranked on season POINTS, highest first. A tie on points goes to
 * whoever holds more venue points this season; a tie on both shares the place,
 * counted 1, 2, 2, 4. Only players on at least one point are ranked.
 *
 * It replaced points per event entered. Every page that shows a rank reads
 * this board, so these tests are the rule and the pages' tests are only
 * checking that they read it.
 */
class SeasonRankingsTest extends TestCase
{
    use RefreshDatabase;

    private PokerSeason $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = PokerSeason::create([
            'name' => 'Season 9',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);
    }

    /**
     * A player who entered a tournament this season, scored $points, and holds
     * $venue venue points in $season (this season unless said otherwise).
     *
     * Each player gets a NIGHT OF THEIR OWN. Registering for a tournament
     * shifts and reprices every result already recorded in it (see
     * PokerTournamentRegistrant::booted), so a second player entering a shared
     * night would quietly reprice the first player's points to whatever the
     * empty points structure pays -- nothing.
     */
    private function player(string $first, int $points, int $venue = 0, ?PokerSeason $season = null, bool $entered = true): User
    {
        $user = User::factory()->create([
            'first_name' => $first, 'last_name' => 'Reeve', 'approval_status' => 'approved',
        ]);
        $name = $first.' Reeve';

        $night = PokerTournament::create([
            'name' => $first."'s night",
            'start_time' => now()->subDays(3),
            'venue_id' => Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St'])->id,
            'season_id' => $this->season->id,
        ]);

        if ($entered) {
            PokerTournamentRegistrant::create([
                'tournament_id' => $night->id, 'user_id' => $user->id,
                'player_name' => $name, 'registered_at' => now(),
            ]);
        }

        PokerTournamentResult::create([
            'tournament_id' => $night->id, 'user_id' => $user->id,
            'player_name' => $name, 'place' => 1, 'points' => $points,
        ]);

        if ($venue > 0) {
            VenuePoints::create([
                'venue_id' => $night->venue_id, 'user_id' => $user->id, 'user_name' => $name,
                'event_date' => now()->subWeek()->toDateString(), 'amount' => $venue,
                'season_id' => ($season ?? $this->season)->id,
            ]);
        }

        return $user;
    }

    /** @return list<string> */
    private function order(): array
    {
        return $this->season->rankings()->pluck('user_id')->all();
    }

    /** @return list<int> */
    private function places(): array
    {
        return $this->season->rankings()->pluck('place')->all();
    }

    public function test_players_are_ordered_by_points(): void
    {
        $b = $this->player('Ben', 120);
        $a = $this->player('Ana', 140);
        $c = $this->player('Cal', 110);

        $this->assertSame([$a->id, $b->id, $c->id], $this->order());
        $this->assertSame([1, 2, 3], $this->places());
    }

    public function test_a_tie_on_points_goes_to_more_venue_points(): void
    {
        $fewer = $this->player('Ana', 120, venue: 4);
        $more = $this->player('Ben', 120, venue: 9);

        $this->assertSame([$more->id, $fewer->id], $this->order());
        $this->assertSame([1, 2], $this->places());
    }

    public function test_a_tie_on_both_shares_the_place_and_skips_the_next(): void
    {
        $this->player('Ana', 140);
        $this->player('Ben', 110, venue: 5);
        $this->player('Cal', 110, venue: 5);
        $this->player('Dev', 95);

        $this->assertSame([1, 2, 2, 4], $this->places());
    }

    public function test_tied_players_are_listed_by_name(): void
    {
        $zed = $this->player('Zed', 110, venue: 5);
        $ada = $this->player('Ada', 110, venue: 5);

        $this->assertSame([$ada->id, $zed->id], $this->order());
    }

    public function test_tied_players_are_listed_by_name_ignoring_case(): void
    {
        // A name typed in lower case on the night is still the same name: a
        // byte-order sort would put every lower-case name after every capital.
        $zed = $this->player('Zed', 110, venue: 5);
        $alice = $this->player('alice', 110, venue: 5);

        $this->assertSame([$alice->id, $zed->id], $this->order());
    }

    public function test_a_player_on_zero_points_is_not_ranked(): void
    {
        $scorer = $this->player('Ana', 50);
        $this->player('Ben', 0);

        $this->assertSame([$scorer->id], $this->order());
        $this->assertSame(1, PokerSeason::figuresFor($this->season, $scorer->id)['ranked']);
    }

    public function test_points_without_an_entry_are_not_ranked(): void
    {
        $entered = $this->player('Ana', 50);
        $this->player('Ben', 90, entered: false);

        $this->assertSame([$entered->id], $this->order());
    }

    public function test_venue_points_from_another_season_do_not_break_the_tie(): void
    {
        $other = PokerSeason::create([
            'name' => 'Season 8',
            'start_date' => now()->subYear(),
            'end_date' => now()->subMonths(6),
            'is_current' => false,
        ]);

        $this->player('Ana', 110);
        $this->player('Ben', 110, venue: 30, season: $other);

        $this->assertSame([1, 1], $this->places());
    }

    public function test_the_board_carries_no_venue_figure(): void
    {
        $this->player('Ana', 110, venue: 7);

        $this->assertSame(
            ['user_id', 'name', 'points', 'place'],
            array_keys($this->season->rankings()->first()),
        );
    }

    public function test_the_name_on_the_board_is_the_same_on_every_request(): void
    {
        // A player recorded under two spellings on two nights. The board must
        // not take whichever row the database returns last: on MySQL, with no
        // ORDER BY, that can change between requests, and so can where a tie
        // lists them.
        $ana = $this->player('Ana', 50);

        $later = PokerTournament::create([
            'name' => 'Later night', 'start_time' => now()->subDay(),
            'venue_id' => Venue::first()->id, 'season_id' => $this->season->id,
        ]);
        PokerTournamentRegistrant::create([
            'tournament_id' => $later->id, 'user_id' => $ana->id,
            'player_name' => 'Zed Alias', 'registered_at' => now(),
        ]);

        // The alphabetically first spelling, whatever order the rows come in.
        $this->assertSame('Ana Reeve', $this->season->rankings()->first()['name']);
    }
}
