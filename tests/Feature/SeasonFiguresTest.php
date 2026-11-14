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
 * different people. Its props are labels rather than numbers -- one decimal,
 * thousands separators, two pluralisations and the dash an unranked player
 * gets -- and building them in two controllers would be two definitions of one
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

    public function test_an_unentered_player_has_no_average_and_no_rank(): void
    {
        $season = $this->season();
        $player = User::factory()->create();

        $props = SeasonFigures::props($season, $player->id, $player, self: true);

        $this->assertSame('Season 12', $props['season_name']);
        $this->assertSame(route('seasons.show', $season), $props['season_href']);
        $this->assertSame('—', $props['season']['rank_label']);
        $this->assertNull($props['season']['per_event_label'],
            'No events means no average; 0.0 reads as a bad one.');
        $this->assertNull($props['season']['ranked_label']);
    }

    public function test_the_labels_are_finished_sentences(): void
    {
        $season = $this->season();
        $player = User::factory()->create();

        $this->scored($season, $player, points: 218, events: 26);

        $figures = SeasonFigures::props($season, $player->id, $player, self: true)['season'];

        // One decimal. These run to a couple of hundred points over a couple
        // of dozen events, so the second decimal is noise that changes nightly.
        $this->assertSame('8.4 pts per event', $figures['per_event_label']);
        $this->assertStringContainsString('218 pts over 26 events', $figures['totals_label']);
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
