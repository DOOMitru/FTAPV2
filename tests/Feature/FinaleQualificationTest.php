<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who plays the season finale.
 *
 * Three targets — points accumulated, tournaments won, venue points — and a
 * player must meet all of them. The rule lives on the model rather than in a
 * view so that every screen renders the same verdict instead of each deriving
 * its own.
 */
class FinaleQualificationTest extends TestCase
{
    use RefreshDatabase;

    private function season(array $thresholds = []): PokerSeason
    {
        return PokerSeason::create(array_merge([
            'name' => 'Season 9',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ], $thresholds));
    }

    public function test_a_season_starts_with_no_thresholds(): void
    {
        // Null is "not published", never zero: every season that existed before
        // this feature has none, and the home page already tells visitors the
        // thresholds are still being set.
        $season = $this->season();

        $this->assertNull($season->finale_points_required);
        $this->assertFalse($season->hasThresholds());
    }

    public function test_no_thresholds_means_nobody_is_judged(): void
    {
        // Not enforced, rather than trivially satisfied. A page must not show a
        // green tick against a rule nobody has written.
        $this->assertFalse($this->season()->hasThresholds());
    }

    public function test_setting_any_single_threshold_counts_as_published(): void
    {
        // hasThresholds() is ANY, not ALL. Task 5's home page and the season
        // page both branch on it, and a partially configured season -- points
        // decided, the other two still open -- must reach the "published"
        // branch so the numbers that DO exist are shown.
        //
        // Added after breaking hasThresholds() deliberately and finding that
        // every existing test still passed: the all-null and all-set cases were
        // covered, the interesting one in between was not.
        foreach ([
            'finale_points_required',
            'finale_wins_required',
            'finale_venue_points_required',
        ] as $field) {
            $this->assertTrue(
                $this->season([$field => 1])->hasThresholds(),
                "Setting only {$field} should count as published."
            );
        }
    }

    public function test_all_three_thresholds_must_be_met(): void
    {
        $season = $this->season([
            'finale_points_required' => 300,
            'finale_wins_required' => 2,
            'finale_venue_points_required' => 50,
        ]);

        $this->assertTrue($season->qualifies(points: 300, wins: 2, venuePoints: 50));
        $this->assertFalse($season->qualifies(points: 299, wins: 2, venuePoints: 50));
        $this->assertFalse($season->qualifies(points: 300, wins: 1, venuePoints: 50));
        $this->assertFalse($season->qualifies(points: 300, wins: 2, venuePoints: 49));
    }

    public function test_meeting_a_threshold_exactly_qualifies(): void
    {
        // The boundary, stated once so nobody "tidies" >= into >.
        $season = $this->season([
            'finale_points_required' => 100,
            'finale_wins_required' => 1,
            'finale_venue_points_required' => 10,
        ]);

        $this->assertTrue($season->qualifies(points: 100, wins: 1, venuePoints: 10));
    }

    public function test_a_null_threshold_is_not_a_barrier(): void
    {
        // A season may publish a points target before the other two are
        // decided; the undecided ones must not block anybody.
        $season = $this->season(['finale_points_required' => 100]);

        $this->assertTrue($season->qualifies(points: 100, wins: 0, venuePoints: 0));
        $this->assertFalse($season->qualifies(points: 99, wins: 0, venuePoints: 0));
    }

    public function test_the_unmet_criteria_are_named(): void
    {
        // So a screen can say WHICH criterion a player is short on rather than
        // showing a bare cross, which tells them they failed without telling
        // them what to do about it.
        $season = $this->season([
            'finale_points_required' => 300,
            'finale_wins_required' => 2,
            'finale_venue_points_required' => 50,
        ]);

        $this->assertSame([], $season->unmetBy(points: 300, wins: 2, venuePoints: 50));
        $this->assertSame(['wins'], $season->unmetBy(points: 300, wins: 1, venuePoints: 50));
        $this->assertSame(
            ['points', 'venue_points'],
            $season->unmetBy(points: 10, wins: 5, venuePoints: 0)
        );
    }

    public function test_a_season_with_no_thresholds_qualifies_nobody_and_blocks_nobody(): void
    {
        // qualifies() answers "does this player meet the published rules"; with
        // none published the answer is vacuously yes. Callers must gate on
        // hasThresholds() first, which is why both exist.
        $season = $this->season();

        $this->assertSame([], $season->unmetBy(points: 0, wins: 0, venuePoints: 0));
        $this->assertTrue($season->qualifies(points: 0, wins: 0, venuePoints: 0));
        $this->assertFalse($season->hasThresholds());
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_an_admin_can_set_the_thresholds(): void
    {
        $this->actingAs($this->admin())->post(route('poker.seasons.store'), [
            'name' => 'Season 10',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'finale_points_required' => 300,
            'finale_wins_required' => 2,
            'finale_venue_points_required' => 50,
        ])->assertRedirect();

        $season = PokerSeason::where('name', 'Season 10')->first();

        $this->assertNotNull($season);
        $this->assertSame(300, $season->finale_points_required);
        $this->assertSame(2, $season->finale_wins_required);
        $this->assertSame(50, $season->finale_venue_points_required);
        $this->assertTrue($season->hasThresholds());
    }

    public function test_thresholds_are_optional(): void
    {
        // A season can be created before its rules are decided.
        $this->actingAs($this->admin())->post(route('poker.seasons.store'), [
            'name' => 'Season 11',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
        ])->assertRedirect();

        $this->assertFalse(PokerSeason::where('name', 'Season 11')->first()->hasThresholds());
    }

    public function test_a_negative_threshold_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('poker.seasons.store'), [
            'name' => 'Season 12',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'finale_points_required' => -5,
        ])->assertSessionHasErrors('finale_points_required');

        $this->assertNull(PokerSeason::where('name', 'Season 12')->first());
    }

    public function test_an_admin_can_change_the_thresholds_on_an_existing_season(): void
    {
        // The controller carries two identical validate blocks. Covering store
        // alone would let an edit silently drop what create accepts.
        $season = $this->season(['finale_points_required' => 100]);

        $this->actingAs($this->admin())->put(route('poker.seasons.update', $season), [
            'name' => $season->name,
            'start_date' => $season->start_date->toDateString(),
            'end_date' => $season->end_date->toDateString(),
            'finale_points_required' => 400,
            'finale_wins_required' => 3,
        ])->assertRedirect();

        $season->refresh();

        $this->assertSame(400, $season->finale_points_required);
        $this->assertSame(3, $season->finale_wins_required);
    }

    public function test_clearing_a_threshold_sets_it_back_to_null(): void
    {
        // Back to NOT PUBLISHED, not to a target of zero. An empty number input
        // posts '' rather than null, and '' fails an integer rule -- so without
        // normalising it, withdrawing a threshold is impossible.
        $season = $this->season(['finale_points_required' => 300]);

        $this->actingAs($this->admin())->put(route('poker.seasons.update', $season), [
            'name' => $season->name,
            'start_date' => $season->start_date->toDateString(),
            'end_date' => $season->end_date->toDateString(),
            'finale_points_required' => '',
        ])->assertRedirect();

        $this->assertNull($season->fresh()->finale_points_required);
        $this->assertFalse($season->fresh()->hasThresholds());
    }

    public function test_a_player_cannot_set_thresholds(): void
    {
        $season = $this->season();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->put(route('poker.seasons.update', $season), [
                'name' => $season->name,
                'start_date' => $season->start_date->toDateString(),
                'end_date' => $season->end_date->toDateString(),
                'finale_points_required' => 999,
            ])->assertForbidden();

        $this->assertNull($season->fresh()->finale_points_required);
    }

    /**
     * A result for one player inside a season, via a tournament of its own.
     *
     * Results hang off tournaments, not seasons directly -- PokerSeason::results
     * is a HasManyThrough -- so a standings fixture needs a tournament each time.
     */
    private function resultFor(PokerSeason $season, User $user, int $place, int $points): void
    {
        $tournament = PokerTournament::create([
            'name' => 'Heat '.uniqid(),
            'scheduled_at' => now()->subDays(5),
            'start_time' => now()->subDays(5),
            'venue_id' => Venue::create(['name' => 'Room '.uniqid(), 'address' => 'x'])->id,
            'season_id' => $season->id,
        ]);

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'player_name' => $user->first_name.' '.$user->last_name,
            'place' => $place,
            'points' => $points,
        ]);
    }

    private function venuePointsFor(User $user, int $amount, string $when): void
    {
        VenuePoints::create([
            'user_id' => $user->id,
            'user_name' => $user->first_name,
            'venue_id' => Venue::create(['name' => 'VP '.uniqid(), 'address' => 'x'])->id,
            'amount' => $amount,
            'event_date' => now()->modify($when)->toDateString(),
        ]);
    }

    public function test_venue_points_are_counted_only_inside_the_season_dates(): void
    {
        // venue_points carries no season_id -- only an event_date -- so the
        // season's own dates are the only attribution available. A row from
        // before the season must not count toward it.
        $season = $this->season(['finale_venue_points_required' => 10]);
        $player = User::factory()->create();

        $this->venuePointsFor($player, 30, '-3 days');     // inside
        $this->venuePointsFor($player, 999, '-1 year');    // before it started
        $this->venuePointsFor($player, 777, '+1 year');    // after it ends

        $this->resultFor($season, $player, place: 1, points: 500);

        $row = $this->leaderboardRowFor($season, $player);

        $this->assertSame(30, $row['venue_points'], 'Only the in-season row counts.');
    }

    public function test_a_player_with_no_venue_points_scores_zero_not_null(): void
    {
        // The row is fed straight into unmetBy(), which is typed int.
        $season = $this->season(['finale_venue_points_required' => 10]);
        $player = User::factory()->create();

        $this->resultFor($season, $player, place: 1, points: 500);

        $this->assertSame(0, $this->leaderboardRowFor($season, $player)['venue_points']);
    }

    public function test_the_leaderboard_carries_the_verdict(): void
    {
        // Computed once in the controller so the view renders a result rather
        // than re-implementing the comparison.
        $season = $this->season([
            'finale_points_required' => 100,
            'finale_wins_required' => 1,
            'finale_venue_points_required' => 10,
        ]);

        $qualifier = User::factory()->create();
        $this->resultFor($season, $qualifier, place: 1, points: 500);
        $this->venuePointsFor($qualifier, 20, '-3 days');

        $shortOnWins = User::factory()->create();
        $this->resultFor($season, $shortOnWins, place: 4, points: 500);
        $this->venuePointsFor($shortOnWins, 20, '-3 days');

        $this->assertTrue($this->leaderboardRowFor($season, $qualifier)['qualified']);
        $this->assertSame([], $this->leaderboardRowFor($season, $qualifier)['unmet']);

        $short = $this->leaderboardRowFor($season, $shortOnWins);
        $this->assertFalse($short['qualified']);
        $this->assertSame(['wins'], $short['unmet']);
    }

    private function leaderboardRowFor(PokerSeason $season, User $user): array
    {
        $response = $this->actingAs($this->admin())->get(route('seasons.show', $season));
        $response->assertOk();

        $row = collect($response->viewData('leaderboard'))
            ->first(fn (array $r) => $r['user']?->id === $user->id);

        $this->assertNotNull($row, 'The player should appear in the standings.');

        return $row;
    }

    public function test_the_page_states_the_targets(): void
    {
        $season = $this->season([
            'finale_points_required' => 300,
            'finale_wins_required' => 2,
            'finale_venue_points_required' => 50,
        ]);

        $this->actingAs($this->admin())->get(route('seasons.show', $season))
            ->assertOk()
            ->assertSee('Finale Qualification')
            ->assertSee('300');
    }

    public function test_a_season_without_thresholds_says_so(): void
    {
        // Explicitly, rather than by rendering nothing: a reader cannot tell an
        // absent panel from a rendering failure.
        $this->actingAs($this->admin())->get(route('seasons.show', $this->season()))
            ->assertOk()
            ->assertSee('have not been set', false)
            ->assertDontSee('Qualified');
    }

    public function test_a_partially_set_season_shows_only_the_numbers_it_has(): void
    {
        // hasThresholds() is ANY, so this season reaches the published branch.
        // The two undecided figures must not render as 0 -- a target nobody
        // chose and that everybody has already met.
        $season = $this->season(['finale_points_required' => 300]);

        $this->actingAs($this->admin())->get(route('seasons.show', $season))
            ->assertOk()
            ->assertSee('300')
            ->assertSee('Not set');
    }

    public function test_the_standings_leave_a_short_player_unmarked(): void
    {
        // The column used to name what a player was missing. It marks the
        // qualified and says nothing about the rest; the thresholds themselves
        // are published in the panel above on this same page.
        $season = $this->season(['finale_wins_required' => 5]);
        $player = User::factory()->create();
        $this->resultFor($season, $player, place: 2, points: 100);

        $this->actingAs($this->admin())->get(route('seasons.show', $season))
            ->assertOk()
            ->assertDontSee('season-show__qualified', false)
            ->assertDontSee('Needs')
            // Not silent, though: an empty cell reads as "blank", which does
            // not distinguish a failing row from a column that is not in use.
            ->assertSee('Not qualified');
    }

    public function test_a_qualifying_player_is_marked_qualified(): void
    {
        $season = $this->season(['finale_points_required' => 100]);
        $player = User::factory()->create();
        $this->resultFor($season, $player, place: 1, points: 500);

        $this->actingAs($this->admin())->get(route('seasons.show', $season))
            ->assertOk()
            // The mark itself, not just the word: the word is in its title and
            // its visually-hidden label, so asserting only that would pass on a
            // cell that rendered no mark at all.
            ->assertSee('season-show__qualified', false)
            ->assertSee('Qualified');
    }

    public function test_the_standings_show_venue_points(): void
    {
        $season = $this->season(['finale_venue_points_required' => 10]);
        $player = User::factory()->create();
        $this->resultFor($season, $player, place: 1, points: 100);
        $this->venuePointsFor($player, 42, '-3 days');

        $this->actingAs($this->admin())->get(route('seasons.show', $season))
            ->assertOk()
            ->assertSee('42');
    }
}
