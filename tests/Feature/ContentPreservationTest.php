<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The upcoming rewrite replaces 100% of the markup on these pages (every
 * Tailwind class, every element), so these tests intentionally assert on
 * DATA ONLY — the literal names, numbers and figures a page must keep
 * showing the user — and never on CSS classes, tag names, or element
 * nesting. If these tests still pass after the rewrite, the page kept
 * telling the truth even though every pixel of it changed.
 *
 * Fixture values are deliberately kept under 1000 (so number_format()
 * cannot introduce a thousands separator that breaks a literal string
 * match) and chosen to be distinctive enough that they can't accidentally
 * collide with an unrelated rank, count, or date fragment elsewhere on
 * the page.
 */
class ContentPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_season_show_preserves_leaderboard_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $venue = Venue::create([
            'name' => 'Frostbite Card Lounge',
            'address' => '9 Glacier Way',
        ]);

        $season = PokerSeason::create([
            'name' => 'Preservation Season',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $tournamentOne = PokerTournament::create([
            'name' => 'Preservation Opener',
            'scheduled_at' => now()->subWeeks(3),
            'start_time' => now()->subWeeks(3)->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $tournamentTwo = PokerTournament::create([
            'name' => 'Preservation Rematch',
            'scheduled_at' => now()->subWeek(),
            'start_time' => now()->subWeek()->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $playerOne = User::factory()->create(['first_name' => 'Odalys', 'last_name' => 'Ferrante']);
        $playerTwo = User::factory()->create(['first_name' => 'Baltazar', 'last_name' => 'Whitlock']);

        // Odalys Ferrante: two results (500 + 360 = 860 points, played twice).
        PokerTournamentResult::create([
            'tournament_id' => $tournamentOne->id,
            'user_id' => $playerOne->id,
            'player_name' => 'Odalys Ferrante',
            'place' => 1,
            'points' => 500,
        ]);
        PokerTournamentResult::create([
            'tournament_id' => $tournamentTwo->id,
            'user_id' => $playerOne->id,
            'player_name' => 'Odalys Ferrante',
            'place' => 2,
            'points' => 360,
        ]);

        // Baltazar Whitlock: a single result worth 712 points.
        PokerTournamentResult::create([
            'tournament_id' => $tournamentOne->id,
            'user_id' => $playerTwo->id,
            'player_name' => 'Baltazar Whitlock',
            'place' => 2,
            'points' => 712,
        ]);

        $response = $this->actingAs($admin)->get(route('seasons.show', $season));

        $response->assertOk();
        $response->assertSee('Odalys Ferrante');
        $response->assertSee('Baltazar Whitlock');
        $response->assertSee('860'); // Odalys' combined points total.
        $response->assertSee('712'); // Baltazar's points total.
    }

    public function test_tournament_show_preserves_registrant_and_result_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $venue = Venue::create([
            'name' => 'Ironclad Poker Hall',
            'address' => '77 Anvil Street',
        ]);

        $season = PokerSeason::create([
            'name' => 'Ironclad Season',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $tournament = PokerTournament::create([
            'name' => 'Ironclad Invitational',
            'scheduled_at' => now()->subWeeks(2),
            'start_time' => now()->subWeeks(2)->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'player_name' => 'Registrant Wanjiru Otieno',
            'registered_at' => now()->subWeeks(3),
        ]);

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'player_name' => 'Podium Perpetua Souza',
            'place' => 2,
            'points' => 525,
        ]);

        // Two filler results (places 1 and 3) so the podium — which is
        // $orderedResults->sortBy('place')->take(3), i.e. the first 3
        // results after sorting, not "results actually placed 1st-3rd" —
        // is completely full. With only 2 results total, take(3) returns
        // both of them, so a "4th place" result would still land in the
        // podium array and leak its name via the runner-up avatar's
        // `title` attribute (confirmed by rendering a 2-result fixture:
        // the 4th-place name still appeared once even with the whole
        // Final Standings section deleted). Four results total is the
        // minimum that actually excludes one of them from the podium.
        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'player_name' => 'Podium Filler First',
            'place' => 1,
            'points' => 600,
        ]);
        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'player_name' => 'Podium Filler Third',
            'place' => 3,
            'points' => 400,
        ]);

        // A 4th result, placed outside the podium's top-3 slice, whose
        // points also don't coincide with the totalPoints/avg-points stat
        // tiles (which sum/average *all 4* results: 600+525+400+187=1712,
        // avg 428). This keeps its name and points pinned to the Final
        // Standings table row alone. Verified by rendering this exact
        // fixture and counting occurrences: both 'Standings Solo
        // Fernandez' and '187' occur exactly once in the response body,
        // in the standings row — and confirmed by mutation: deleting the
        // Final Standings section from the view makes both disappear
        // entirely (0 occurrences), unlike the single-result fixture this
        // replaced, where '525' alone survived section deletion via the
        // podium avatar title and the stat tiles.
        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'player_name' => 'Standings Solo Fernandez',
            'place' => 4,
            'points' => 187,
        ]);

        $response = $this->actingAs($admin)->get(route('tournaments.show', $tournament));

        $response->assertOk();
        $response->assertSee('Ironclad Invitational'); // Tournament name.
        $response->assertSee('Ironclad Poker Hall');    // Venue name.
        $response->assertSee('Registrant Wanjiru Otieno'); // Registrant name.
        $response->assertSee('Podium Perpetua Souza');  // Result player name.
        $response->assertSee('525');                    // Result points.
        $response->assertSee('Standings Solo Fernandez'); // Standings-only result name (place 4, outside the podium).
        $response->assertSee('187');                       // Standings-only result points — pinned to the standings table row only.
    }

    public function test_dashboard_preserves_career_figures(): void
    {
        $player = User::factory()->create(['is_admin' => false]);

        $venue = Venue::create([
            'name' => 'Career Stats Venue',
            'address' => '1 Ledger Lane',
        ]);

        $season = PokerSeason::create([
            'name' => 'Career Stats Season',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        // Four career results: places 1, 1, 3, 4 -> two wins.
        // Points: 200 + 200 + 150 + 95 = 645 total.
        $places = [1, 1, 3, 4];
        $points = [200, 200, 150, 95];

        foreach ($places as $index => $place) {
            $tournament = PokerTournament::create([
                'name' => "Career Stats Event {$index}",
                'scheduled_at' => now()->subWeeks(4 - $index),
                'start_time' => now()->subWeeks(4 - $index)->addMinutes(30),
                'venue_id' => $venue->id,
                'season_id' => $season->id,
            ]);

            PokerTournamentResult::create([
                'tournament_id' => $tournament->id,
                'user_id' => $player->id,
                'player_name' => $player->first_name.' '.$player->last_name,
                'place' => $place,
                'points' => $points[$index],
            ]);
        }

        $response = $this->actingAs($player)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('645'); // Career points total (Career Points stat tile).

        // `assertSee('4')` / `assertSee('2')` were dropped: on this dashboard,
        // "4" and "2" each occur 100+ times in unrelated markup (viewBox="0 0
        // 24 24" on every icon, gap-4/w-4/h-4 utility classes, ULIDs in
        // hrefs, date fragments, etc.), so both assertions passed even on a
        // blank dashboard with no data at all — verified by rendering this
        // exact fixture and counting occurrences. No small, realistic
        // events-played/wins count can be pinned uniquely in the body text
        // here (every value 0-19, and most values up to 99, collide with
        // fixed icon path data or Tailwind spacing classes present on every
        // authenticated page); driving the counts into triple digits to
        // dodge the noise would make the fixture unrealistic, slow, and
        // still non-deterministic (ULIDs embedded in the page are random
        // per run and could coincidentally reintroduce a collision).
        //
        // Real enforcement instead: assertViewHas() checks the actual
        // scalar bound to the view (Illuminate\Testing\TestResponse::
        // assertViewHas() routes a non-null scalar through assertEquals(),
        // a genuine equality check, not a null-key-exists check), so this
        // fails if the controller ever computes the wrong events-played or
        // win count.
        $response->assertViewHas('tournamentsPlayed', 4); // Events (tournaments) played.
        $response->assertViewHas('wins', 2);               // Tournament wins.

        // assertViewHas above proves the CONTROLLER computes the right figures.
        // It would still pass if a rewrite deleted the stat tiles entirely, so
        // it does not, on its own, protect displayed content.
        //
        // These assertions close that gap. Tag-stripping removes the noise that
        // made a bare assertSee('4') useless (Tailwind spacing classes, SVG path
        // data, ULIDs), and pairing each figure with its own label makes the
        // match unique -- "Events Played" and "Tournament Wins" each occur
        // exactly once in the visible text. Whitespace is collapsed first
        // because strip_tags leaves the markup's original line breaks and
        // indentation between the label and its value.
        //
        // If a later phase renames one of these labels, this assertion fails.
        // That is intended: a label is user-facing copy, and changing it should
        // be a deliberate decision recorded here, not a silent side effect of
        // restyling.
        $text = preg_replace('/\s+/', ' ', strip_tags($response->getContent()));

        $this->assertStringContainsString('Career Points 645', $text);
        $this->assertStringContainsString('Events Played 4', $text);
        $this->assertStringContainsString('Tournament Wins 2', $text);
    }

    // -----------------------------------------------------------------
    // Phase 2: the public pages.
    //
    // The rules pages are the sharpest risk in the whole conversion. All
    // four hold their content in inline @php arrays inside the very view
    // being rewritten -- the routes pass them nothing -- so a rewrite that
    // drops a rule, or reorders the numbering, changes the league's
    // published regulations and breaks no other test. RouteSmokeTest sees
    // a 200. EmptyStateSmokeTest sees no Blade artifact. Only an assertion
    // on the text notices.
    //
    // As with the methods above: assert on CONTENT, never on markup, so the
    // conversion is free to change every tag.
    // -----------------------------------------------------------------

    /**
     * 21 numbered rules across 5 sections, plus the 10 hand rankings.
     */
    public function test_texas_holdem_preserves_every_rule(): void
    {
        $response = $this->get('/rules/texas-holdem');
        $response->assertOk();

        foreach ([
            'Structural Standards', 'The Dealing Phase', 'Wagering Intervals',
            'The Showdown', 'Technical Provisions',
        ] as $section) {
            $response->assertSee($section);
        }

        // All 21, not a sample. A dropped rule is the exact failure this
        // method exists to catch, and the titles are distinctive enough to
        // assert individually.
        foreach ([
            'The Deck', 'The Blinds', 'The Shuffle', 'Hole Cards', 'Misdeals',
            'Dead Hands', 'Pre-Flop Betting', 'The Flop', 'The Turn', 'The River',
            'Burn Cards', 'Card Value', 'Order of Show', 'Cards Speak',
            'Splitting Pots', 'Mucking', 'Side Pots', 'Rabbitting',
            'Playing the Board', 'Chip Positioning', 'Director Finality',
        ] as $rule) {
            $response->assertSee($rule);
        }

        // The zero-padded numbering is content too -- rules get cited by
        // number. Only the endpoints are asserted: every intermediate value
        // is a two-digit string that would match somewhere by accident, so
        // asserting all of them would be a check that cannot fail.
        $text = preg_replace('/\s+/', ' ', strip_tags($response->getContent()));
        $this->assertStringContainsString('01', $text);
        $this->assertStringContainsString('21', $text);

        foreach ([
            'Royal Flush', 'Straight Flush', 'Four of a Kind', 'Full House', 'Flush',
            'Straight', 'Three of a Kind', 'Two Pair', 'One Pair', 'High Card',
        ] as $rank) {
            $response->assertSee($rank);
        }
    }

    public function test_conduct_rules_page_preserves_every_rule(): void
    {
        $response = $this->get('/rules/conduct');
        $response->assertOk();

        foreach ([
            'Verbal Declarations', 'String Betting', 'Oversized Chips', 'Raise Limits',
            'Ethical Play', 'Professional Courtesy', 'Table Communication', 'Electronic Devices',
            'First Offense', 'Second Offense', 'Third Offense',
        ] as $item) {
            $response->assertSee($item);
        }
    }

    public function test_regulations_page_preserves_every_rule(): void
    {
        $response = $this->get('/rules/regulations');
        $response->assertOk();

        foreach ([
            'Standard Play', 'Blind Intervals', 'Re-Entry Policy', 'The Clock',
            'Tournament Schedule', 'Points Accumulation', 'Seasonal Standings', 'Qualification',
            'Point Multiplier', 'The Trophy',
        ] as $item) {
            $response->assertSee($item);
        }
    }

    /**
     * Live data rather than an inline array: the points table and the top
     * three of the current season.
     */
    public function test_points_structure_page_preserves_the_table_and_the_leaders(): void
    {
        $season = PokerSeason::factory()->create(['name' => 'Season Verifiable', 'is_current' => true]);
        $venue = Venue::factory()->create();
        $tournament = PokerTournament::factory()->create([
            'season_id' => $season->id,
            'venue_id' => $venue->id,
            'start_time' => now()->subWeek(),
        ]);

        $leader = User::factory()->create(['first_name' => 'Leadfoot', 'last_name' => 'Kowalczyk']);
        PokerTournamentResult::factory()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $leader->id,
            'player_name' => 'Leadfoot Kowalczyk',
            'place' => 1,
            'points' => 391,
        ]);

        // The leaders panel is signed-in only, so a guest must NOT see it --
        // asserting that is the point, not an aside: the panel links into the
        // members' standings.
        $this->get('/rules/points-structure')
            ->assertOk()
            ->assertDontSee('Current Season Leaders')
            ->assertDontSee('Leadfoot');

        $response = $this->actingAs(User::factory()->create())->get('/rules/points-structure');
        $response->assertOk();

        // The season's NAME is never printed here -- it only reaches the page
        // as a route parameter on the standings link. What is visible is the
        // leader's name and their summed points.
        $response->assertSee('Leadfoot');
        $response->assertSee('Kowalczyk');
        $response->assertSee('391');
        $response->assertSee('Current Season Leaders');

        // Whatever the seeded structure holds, every place and point value in
        // it has to survive the rewrite.
        foreach (\App\Models\PointsStructure::orderBy('place')->get() as $row) {
            $response->assertSee((string) $row->points);
        }
    }

    public function test_events_page_preserves_upcoming_and_past_tournaments(): void
    {
        $season = PokerSeason::factory()->create(['is_current' => true]);
        $venue = Venue::factory()->create(['name' => 'The Verifiable Room']);

        PokerTournament::factory()->create([
            'name' => 'Forthcoming Showdown', 'season_id' => $season->id,
            'venue_id' => $venue->id, 'start_time' => now()->addWeek(),
        ]);
        PokerTournament::factory()->create([
            'name' => 'Bygone Shootout', 'season_id' => $season->id,
            'venue_id' => $venue->id, 'start_time' => now()->subWeek(),
        ]);

        $response = $this->get('/events');
        $response->assertOk();

        $response->assertSee('Forthcoming Showdown');
        $response->assertSee('Bygone Shootout');
        $response->assertSee('The Verifiable Room');
    }

    /**
     * The @empty arms. EmptyStateSmokeTest only sweeps these two pages for
     * 5xx and leaked Blade artifacts on an empty database -- a conversion
     * that deleted the @empty arm outright would render a blank section,
     * return 200 and pass. This is the assertion that notices.
     */
    public function test_public_empty_states_still_say_something(): void
    {
        $this->get('/events')
            ->assertOk()
            ->assertSee('No Scheduled Events')
            ->assertSee('Check back soon for our next seasonal announcement.');

        $this->get('/rules/points-structure')
            ->assertOk()
            ->assertSee('Standard league structure is being finalized.');
    }
}
