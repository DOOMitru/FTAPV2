<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use App\Support\SeasonFigures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * The season panel's props, built once.
 *
 * The panel is rendered by the dashboard and by a player's profile, about
 * different people. Its props are labels rather than numbers -- thousands
 * separators, two pluralisations and the dash an unranked player gets -- and building them in two controllers would be two definitions of one
 * thing, which is how PokerSeason::current() and initials() came to exist.
 */
class SeasonFiguresTest extends TestCase
{
    // enter() and score() from the concern, because registrants and results
    // both carry a player_name and a registered_at that are easy to forget and
    // fail as a database error rather than as an assertion. Its tournament()
    // is NOT used: it firstOrCreate()s a season of its own, and these tests
    // need the season they made.
    use BuildsTournaments, RefreshDatabase;

    private function season(): PokerSeason
    {
        return PokerSeason::create([
            'name' => 'Season 12',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);
    }

    public function test_with_no_season_the_panel_is_told_there_is_none(): void
    {
        $player = User::factory()->create();

        $props = SeasonFigures::props(null, $player->id, $player, self: true);

        // Null season, not a zeroed one: the panel draws its empty state from
        // this, and a row of zeros would be a claim about the player.
        $this->assertNull($props['season']);
        $this->assertNull($props['season_href']);
        $this->assertSame('Current Season', $props['season_name']);
    }

    public function test_an_unranked_player_has_no_rank(): void
    {
        $season = $this->season();
        $player = User::factory()->create();

        $props = SeasonFigures::props($season, $player->id, $player, self: true);

        $this->assertSame('Season 12', $props['season_name']);
        $this->assertSame(route('seasons.show', $season), $props['season_href']);
        $this->assertSame('—', $props['season']['rank_label']);
        $this->assertFalse($props['season']['is_ranked']);
        $this->assertNull($props['season']['ranked_label']);
        $this->assertArrayNotHasKey('per_event_label', $props['season'],
            'The average is gone from the panel, not blanked.');
    }

    public function test_a_ranked_player_shows_totals_and_the_field(): void
    {
        $season = $this->season();
        $leader = User::factory()->create();
        $player = User::factory()->create();
        $third = User::factory()->create();

        $this->scored($season, $leader, points: 300, events: 6);
        $this->scored($season, $player, points: 120, events: 6);
        $this->scored($season, $third, points: 40, events: 2);

        $figures = SeasonFigures::props($season, $player->id, $player, self: true)['season'];

        $this->assertTrue($figures['is_ranked']);
        $this->assertSame('#2', $figures['rank_label']);
        $this->assertSame('120 pts over 6 events', $figures['totals_label']);
        $this->assertSame('of 3 ranked players', $figures['ranked_label']);
    }

    public function test_a_shared_place_is_shown_as_that_place(): void
    {
        $season = $this->season();
        $leader = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->scored($season, $leader, points: 300, events: 2);
        $this->scored($season, $a, points: 120, events: 2);
        $this->scored($season, $b, points: 120, events: 3);

        foreach ([$a, $b] as $tied) {
            $this->assertSame('#2',
                SeasonFigures::props($season, $tied->id, $tied, self: true)['season']['rank_label']);
        }
    }

    public function test_one_event_is_said_in_the_singular(): void
    {
        $season = $this->season();
        $player = User::factory()->create();

        $this->scored($season, $player, points: 10, events: 1);

        $figures = SeasonFigures::props($season, $player->id, $player, self: true)['season'];

        $this->assertStringContainsString('over 1 event', $figures['totals_label']);
        $this->assertStringNotContainsString('1 events', $figures['totals_label']);
        $this->assertSame('of 1 ranked player', $figures['ranked_label']);
    }

    public function test_a_stranger_is_not_told_the_venue_points(): void
    {
        // The owner's rule, for the whole app: a player's venue tally is read
        // by that player and by admins, nobody else. Withheld from the PROPS,
        // because a gate in a template stops today's markup and not tomorrow's.
        $season = $this->season();
        $player = User::factory()->create();
        $stranger = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->scored($season, $player, points: 10, events: 1);

        $this->assertNull(
            SeasonFigures::props($season, $player->id, $stranger, self: false)['season']['venue_points_label'],
            "A stranger must not be sent another player's venue points."
        );

        $this->assertNotNull(
            SeasonFigures::props($season, $player->id, $player, self: true)['season']['venue_points_label']
        );

        $this->assertNotNull(
            SeasonFigures::props($season, $player->id, $admin, self: false)['season']['venue_points_label']
        );
    }

    /** A player with results and registrations in a season. */
    private function scored(PokerSeason $season, User $player, int $points, int $events): void
    {
        $venue = Venue::create(['name' => 'Fixture Room']);

        for ($i = 0; $i < $events; $i++) {
            $tournament = PokerTournament::create([
                'name' => 'Night '.$i,
                'start_time' => $season->start_date->copy()->addDays($i),
                'season_id' => $season->id,
                'venue_id' => $venue->id,
            ]);

            $this->enter($tournament, $player);

            // The whole total on the first night, so the arithmetic in the
            // labels is a figure this test chose rather than one it derived.
            $this->score($tournament, $player, place: $i === 0 ? 1 : 9, points: $i === 0 ? $points : 0);
        }

        VenuePoints::create([
            'venue_id' => $venue->id,
            'user_id' => $player->id,
            'user_name' => trim($player->first_name.' '.$player->last_name),
            'event_date' => $season->start_date->copy()->addDay(),
            'season_id' => $season->id,
            'amount' => 40,
        ]);
    }
}
