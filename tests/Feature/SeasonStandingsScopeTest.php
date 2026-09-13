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
 * Who appears in a season's standings.
 *
 * The table is built from RESULTS, and a result can exist with no registration
 * behind it: the results screen creates one without requiring an entry, where
 * Eliminate refuses. That row is a finish in a field nobody joined, and it has
 * no business in a table of how the season is going.
 *
 * On the league's current data this excludes nobody -- every one of the eighty
 * scorers had entered -- so it is a guard rather than a repair. It is testable
 * precisely because the state is reachable.
 */
class SeasonStandingsScopeTest extends TestCase
{
    use RefreshDatabase;

    private function season(): PokerSeason
    {
        return PokerSeason::create([
            'name' => 'Season 9',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);
    }

    private function night(PokerSeason $season, string $name = 'Night'): PokerTournament
    {
        return PokerTournament::create([
            'name' => $name,
            'start_time' => now()->subDays(3),
            'venue_id' => Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St'])->id,
            'season_id' => $season->id,
        ]);
    }

    private function player(string $first): User
    {
        return User::factory()->create([
            'first_name' => $first, 'last_name' => 'Player', 'approval_status' => 'approved',
        ]);
    }

    private function enter(PokerTournament $t, User $u): void
    {
        PokerTournamentRegistrant::create([
            'tournament_id' => $t->id, 'user_id' => $u->id,
            'player_name' => $u->first_name.' '.$u->last_name, 'registered_at' => now(),
        ]);
    }

    private function score(PokerTournament $t, ?User $u, int $place, int $points, string $name = 'Ghost Player'): void
    {
        PokerTournamentResult::create([
            'tournament_id' => $t->id,
            'user_id' => $u?->id,
            'player_name' => $u ? $u->first_name.' '.$u->last_name : $name,
            'place' => $place, 'points' => $points,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function standings(PokerSeason $season)
    {
        return collect($this->actingAs($this->admin())
            ->get(route('seasons.show', $season))->assertOk()->viewData('leaderboard'));
    }

    public function test_a_player_who_entered_is_in_the_standings(): void
    {
        $season = $this->season();
        $night = $this->night($season);
        $player = $this->player('Entered');

        $this->enter($night, $player);
        $this->score($night, $player, 1, 100);

        $this->assertSame(['Entered Player'], $this->standings($season)->pluck('player_name')->all());
    }

    public function test_a_scorer_who_never_entered_is_left_out(): void
    {
        // Reachable: the results screen writes a result without requiring a
        // registration.
        $season = $this->season();
        $night = $this->night($season);

        $entered = $this->player('Entered');
        $this->enter($night, $entered);
        $this->score($night, $entered, 2, 85);

        $this->score($night, $this->player('Unentered'), 1, 100);

        $names = $this->standings($season)->pluck('player_name');

        $this->assertContains('Entered Player', $names);
        $this->assertNotContains('Unentered Player', $names);
    }

    public function test_a_result_with_no_account_at_all_is_left_out(): void
    {
        // user_id is nullable, so such rows group under '' -- caught by the
        // same rule rather than by a special case.
        $season = $this->season();
        $night = $this->night($season);

        $entered = $this->player('Entered');
        $this->enter($night, $entered);
        $this->score($night, $entered, 2, 85);

        $this->score($night, null, 1, 100, name: 'Nobody At All');

        $this->assertNotContains('Nobody At All', $this->standings($season)->pluck('player_name'));
    }

    public function test_entering_another_season_does_not_let_you_in(): void
    {
        // The registrations are scoped to THIS season's tournaments. Reading
        // them globally would admit anyone who has ever entered anything.
        $season = $this->season();
        $other = PokerSeason::create([
            'name' => 'Season 8', 'start_date' => now()->subYear(),
            'end_date' => now()->subMonths(2), 'is_current' => false,
        ]);

        $night = $this->night($season);
        $player = $this->player('Elsewhere');

        // Entered last season, scored in this one without entering it.
        $this->enter($this->night($other, 'Old Night'), $player);
        $this->score($night, $player, 1, 100);

        $this->assertNotContains('Elsewhere Player', $this->standings($season)->pluck('player_name'));
    }

    public function test_entering_without_scoring_does_not_invent_a_row(): void
    {
        // The rule narrows the table; it does not widen it. The standings are
        // still a table of finishes, so somebody yet to be eliminated has
        // nothing to show in it.
        $season = $this->season();
        $night = $this->night($season);

        $scored = $this->player('Scored');
        $waiting = $this->player('Waiting');

        foreach ([$scored, $waiting] as $p) {
            $this->enter($night, $p);
        }

        $this->score($night, $scored, 1, 100);

        $this->assertSame(['Scored Player'], $this->standings($season)->pluck('player_name')->all());
    }

    public function test_the_players_tile_counts_the_same_people(): void
    {
        // Two numbers on one page that disagree is worse than either being
        // wrong: the tile is read off the standings now.
        $season = $this->season();
        $night = $this->night($season);

        $entered = $this->player('Entered');
        $this->enter($night, $entered);
        $this->score($night, $entered, 2, 85);

        $this->score($night, $this->player('Unentered'), 1, 100);

        $response = $this->actingAs($this->admin())
            ->get(route('seasons.show', $season))->assertOk();

        $this->assertSame(1, $response->viewData('uniquePlayersCount'));
        $this->assertCount(1, $response->viewData('leaderboard'));
    }
}
