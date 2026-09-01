<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
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
}
