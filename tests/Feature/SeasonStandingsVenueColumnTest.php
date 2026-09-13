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
 * Who gets to read the venue points column.
 *
 * Venue points are a back-office tally: awarded by hand at the bar, corrected
 * by hand, and a player's own figure is already on their dashboard. What the
 * column adds to a player's view is everybody ELSE's running total, which is
 * admin business.
 *
 * The finale mark stays for everyone. It is derived from venue points, but the
 * thresholds it is measured against are published in the panel above the table
 * -- the verdict is public even where one of its inputs is not.
 */
class SeasonStandingsVenueColumnTest extends TestCase
{
    use RefreshDatabase;

    /** Distinctive on purpose: a number that cannot be confused with a place,
     *  a points total or a count if it turns up in the markup. */
    private const VENUE_TALLY = 137;

    private function season(): PokerSeason
    {
        return PokerSeason::create([
            'name' => 'Season 9',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);
    }

    private function player(string $first, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'first_name' => $first, 'last_name' => 'Player', 'approval_status' => 'approved',
        ], $extra));
    }

    /**
     * One season, one night, one finisher holding a venue tally.
     *
     * @return array{0: PokerSeason, 1: User}
     */
    private function seasonWithAFinisher(): array
    {
        $season = $this->season();

        $tournament = PokerTournament::create([
            'name' => 'Night',
            'start_time' => now()->subDays(3),
            'venue_id' => $venue = Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St'])->id,
            'season_id' => $season->id,
        ]);

        $finisher = $this->player('Wanda');

        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id, 'user_id' => $finisher->id,
            'player_name' => 'Wanda Player', 'registered_at' => now(),
        ]);

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id, 'user_id' => $finisher->id,
            'player_name' => 'Wanda Player', 'place' => 1, 'points' => 500,
        ]);

        VenuePoints::create([
            'user_id' => $finisher->id, 'user_name' => 'Wanda',
            'venue_id' => $venue, 'amount' => self::VENUE_TALLY,
            'event_date' => now()->subDays(3)->toDateString(), 'season_id' => $season->id,
        ]);

        return [$season, $finisher];
    }

    /**
     * The standings table only.
     *
     * Sliced out of the page rather than searched whole: "Venue pts" is also a
     * tile label on the venue pages, and the finale panel above this table
     * carries a "Venue points" threshold that everybody is meant to see. A test
     * that searched the whole document would pass while the column it means to
     * check was still there.
     */
    private function standingsFor(User $viewer, PokerSeason $season): string
    {
        $html = $this->actingAs($viewer)
            ->get(route('seasons.show', $season))->assertOk()->getContent();

        $start = strpos($html, 'season-show__standings');
        $this->assertNotFalse($start, 'The standings table should be on the page.');

        $end = strpos($html, '</table>', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    public function test_an_admin_reads_the_column(): void
    {
        [$season] = $this->seasonWithAFinisher();

        $table = $this->standingsFor($this->player('Boss', ['is_admin' => true]), $season);

        $this->assertStringContainsString('>Venue points</th>', $table);
        $this->assertStringContainsString('season-show__stat--venue', $table);
        $this->assertStringContainsString((string) self::VENUE_TALLY, $table);
    }

    public function test_a_player_does_not_read_the_header(): void
    {
        [$season, $finisher] = $this->seasonWithAFinisher();

        $this->assertStringNotContainsString(
            '>Venue points</th>', $this->standingsFor($finisher, $season)
        );
    }

    public function test_a_player_does_not_read_the_figures(): void
    {
        // Both the cell and the tally, because dropping only the header would
        // leave a column of unlabelled numbers rather than no column at all.
        [$season, $finisher] = $this->seasonWithAFinisher();

        $table = $this->standingsFor($finisher, $season);

        $this->assertStringNotContainsString('season-show__stat--venue', $table);
        $this->assertStringNotContainsString((string) self::VENUE_TALLY, $table);
    }

    public function test_a_player_does_not_read_the_phone_label_either(): void
    {
        // On a phone the column header is clipped and ::after draws the label
        // from data-label instead. Gating only the <th> would hide the column
        // on a desktop and leave it in place on the device most players use.
        [$season, $finisher] = $this->seasonWithAFinisher();

        $this->assertStringNotContainsString(
            'data-label="venue points"', $this->standingsFor($finisher, $season)
        );
    }

    public function test_a_player_still_reads_every_other_column(): void
    {
        // The guard against gating too much: one cell leaves, six stay, and the
        // grid on a phone still has something to place in every area it names.
        [$season, $finisher] = $this->seasonWithAFinisher();

        $table = $this->standingsFor($finisher, $season);

        foreach ([
            'season-show__rank',
            'season-show__player',
            'season-show__meter-cell',
            'season-show__stat--played',
            'season-show__stat--won',
            'season-show__finale',
        ] as $hook) {
            $this->assertStringContainsString($hook, $table, "A player lost {$hook}.");
        }

        foreach (['Rank', 'Player', 'Points', 'Played', 'Wins', 'Finale'] as $header) {
            $this->assertStringContainsString('>'.$header.'</th>', $table);
        }
    }

    public function test_exactly_one_column_is_withheld(): void
    {
        [$season, $finisher] = $this->seasonWithAFinisher();

        $admin = $this->player('Boss', ['is_admin' => true]);

        $this->assertSame(
            preg_match_all('/<th\b/', $this->standingsFor($admin, $season)) - 1,
            preg_match_all('/<th\b/', $this->standingsFor($finisher, $season)),
            'A player should see the admin table minus one column, no more and no less.'
        );
    }

    public function test_the_finale_verdict_survives_for_a_player(): void
    {
        // Derived from venue points, and still public: the thresholds are
        // printed above the table for everyone, so the verdict is not a leak.
        // Withholding it would also empty a column for no stated reason.
        [$season, $finisher] = $this->seasonWithAFinisher();

        $season->forceFill([
            'finale_points_required' => 100,
            'finale_wins_required' => 1,
            'finale_venue_points_required' => 50,
        ])->save();

        $table = $this->standingsFor($finisher, $season);

        $this->assertStringContainsString('season-show__qualified', $table);
        $this->assertStringNotContainsString((string) self::VENUE_TALLY, $table);
    }
}
