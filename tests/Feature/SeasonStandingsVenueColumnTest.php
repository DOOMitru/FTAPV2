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
    /**
     * What the standings are sent for one viewer.
     *
     * The docblock above explains why the old version cut the table out of the
     * page before searching it: "Venue points" is also a tile label on the
     * venue pages and a threshold in the finale panel above this table, so a
     * page-wide search would pass while the column it means to check was still
     * there. There is nothing to cut now -- the column is a flag, and the
     * figure is a field that is either present or absent.
     *
     * @return array{shows: bool, rows: array<int, array<string, mixed>>}
     */
    private function standingsFor(User $viewer, PokerSeason $season): array
    {
        $props = $this->actingAs($viewer)
            ->get(route('seasons.show', $season))->assertOk()
            ->viewData('page')['props'];

        return ['shows' => $props['shows_venue_points'], 'rows' => $props['standings']];
    }

    /** The page's own markup, for the questions that are about the template. */
    private function page(): string
    {
        return (string) file_get_contents(resource_path('js/Pages/Poker/Seasons/Show.vue'));
    }

    public function test_an_admin_reads_the_column(): void
    {
        [$season] = $this->seasonWithAFinisher();

        $seen = $this->standingsFor($this->player('Boss', ['is_admin' => true]), $season);

        $this->assertTrue($seen['shows']);
        $this->assertSame((string) self::VENUE_TALLY, $seen['rows'][0]['venue_points_label']);

        // The column, the cell and the phone label are all drawn from the one
        // flag, so none of the three can be left behind when it is false.
        $page = $this->page();

        $this->assertSame(2, substr_count($page, 'v-if="shows_venue_points"'));
        $this->assertStringContainsString('season-show__stat--venue', $page);
        $this->assertStringContainsString('data-label="venue points"', $page);
    }

    public function test_a_player_does_not_read_the_header(): void
    {
        [$season, $finisher] = $this->seasonWithAFinisher();

        $this->assertFalse($this->standingsFor($finisher, $season)['shows']);
    }

    public function test_a_player_does_not_read_the_figures(): void
    {
        // Both the cell and the tally, because dropping only the header would
        // leave a column of unlabelled numbers rather than no column at all.
        [$season, $finisher] = $this->seasonWithAFinisher();

        // A second finisher, because the viewer reads their OWN tally by the
        // rule -- what they must not read is somebody else's. The fixture's
        // one row is the viewer's, so on its own it can only prove the column
        // is undrawn, which is the assertion above.
        $other = $this->player('Someone');
        $night = PokerTournament::create([
            'name' => 'Second Night', 'start_time' => now()->subDays(2),
            'venue_id' => Venue::firstOrFail()->id, 'season_id' => $season->id,
        ]);

        PokerTournamentRegistrant::create([
            'tournament_id' => $night->id, 'user_id' => $other->id,
            'player_name' => 'Someone Player', 'registered_at' => now(),
        ]);
        PokerTournamentResult::create([
            'tournament_id' => $night->id, 'user_id' => $other->id,
            'player_name' => 'Someone Player', 'place' => 1, 'points' => 400,
        ]);
        VenuePoints::create([
            'user_id' => $other->id, 'user_name' => 'Someone',
            'venue_id' => Venue::firstOrFail()->id, 'amount' => self::VENUE_TALLY,
            'event_date' => now()->subDays(2)->toDateString(), 'season_id' => $season->id,
        ]);

        $seen = $this->standingsFor($finisher, $season);

        // No column for a player either way, and the other player's figure is
        // absent from the DATA rather than merely undrawn -- it never reaches
        // the browser, so no future column, dump or partial can show it.
        $this->assertFalse($seen['shows']);

        $theirs = collect($seen['rows'])->firstWhere('shown_name', 'Someone Player');

        $this->assertNotNull($theirs);
        $this->assertNull($theirs['venue_points_label'],
            "Another player's tally reached the page.");
    }

    public function test_a_player_does_not_read_the_phone_label_either(): void
    {
        // On a phone the column header is clipped and ::after draws the label
        // from data-label instead. Gating only the <th> would hide the column
        // on a desktop and leave it in place on the device most players use --
        // so both are inside the same v-if, which the admin test counts.
        [$season, $finisher] = $this->seasonWithAFinisher();

        $this->assertFalse($this->standingsFor($finisher, $season)['shows']);

        $this->assertMatchesRegularExpression(
            '/<td v-if="shows_venue_points"[^>]*data-label="venue points"/s',
            preg_replace('/\s+/', ' ', $this->page()),
            'The phone label is drawn outside the flag that hides the column.'
        );
    }

    public function test_a_player_still_reads_every_other_column(): void
    {
        // The guard against gating too much: one cell leaves, six stay, and the
        // grid on a phone still has something to place in every area it names.
        [$season, $finisher] = $this->seasonWithAFinisher();

        $seen = $this->standingsFor($finisher, $season);
        $page = $this->page();

        // Every other cell is unconditional in the template, so a player loses
        // exactly the one column.
        foreach ([
            'season-show__rank',
            'season-show__player',
            'season-show__meter-cell',
            'season-show__stat--played',
            'season-show__stat--won',
            'season-show__finale',
        ] as $hook) {
            $this->assertStringContainsString($hook, $page, "The table lost {$hook}.");
        }

        // And the row still carries every other figure.
        foreach (['rank', 'shown_name', 'meter_value_label', 'played_label', 'wins_label', 'finale_state'] as $field) {
            $this->assertArrayHasKey($field, $seen['rows'][0]);
            $this->assertNotNull($seen['rows'][0][$field], "A player lost {$field}.");
        }
    }

    public function test_exactly_one_column_is_withheld(): void
    {
        [$season, $finisher] = $this->seasonWithAFinisher();

        $admin = $this->player('Boss', ['is_admin' => true]);

        // Read as a THIRD party, so the row under comparison is nobody's own:
        // a player reads their own tally by the rule, and the difference this
        // test is about is the one the column makes.
        $bystander = $this->player('Bystander');

        $adminRow = $this->standingsFor($admin, $season)['rows'][0];
        $playerRow = $this->standingsFor($bystander, $season)['rows'][0];

        // One field differs, and it is the tally. Everything else a player is
        // sent is what an administrator is sent.
        $this->assertSame(
            ['venue_points_label'],
            array_keys(array_diff_assoc(
                array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $adminRow),
                array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $playerRow)
            )),
            'A player should see the admin row minus one figure, no more and no less.'
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

        $seen = $this->standingsFor($finisher, $season);

        // The verdict is derived from venue points and is still public: the
        // thresholds are printed above the table for everyone, so it is not a
        // leak -- and withholding it would empty a column for no stated reason.
        $this->assertSame('qualified', $seen['rows'][0]['finale_state']);

        // And the column itself is still not drawn for a player.
        $this->assertFalse($seen['shows']);
    }
}
