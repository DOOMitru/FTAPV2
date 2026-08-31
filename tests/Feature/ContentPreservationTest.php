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

        $response = $this->actingAs($admin)->get(route('tournaments.show', $tournament));

        $response->assertOk();
        $response->assertSee('Ironclad Invitational'); // Tournament name.
        $response->assertSee('Ironclad Poker Hall');    // Venue name.
        $response->assertSee('Registrant Wanjiru Otieno'); // Registrant name.
        $response->assertSee('Podium Perpetua Souza');  // Result player name.
        $response->assertSee('525');                    // Result points.
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
        $response->assertSee('645'); // Career points total.
        $response->assertSee('4');   // Events (tournaments) played.
        $response->assertSee('2');   // Tournament wins.
    }
}
