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
 * The landing page's season standings, and the rank that leads them.
 *
 * Three boards: rank, wins, points. Rank is first because it is the one that
 * decides the season -- points and wins both reward turning up more often,
 * where a rank is points per tournament ENTERED and does not.
 *
 * The arithmetic is PokerSeason::rankings(), the same board the dashboard puts
 * a player's own position against. That sharing is the point of these tests:
 * two pages showing a "rank" that meant different things would be worse than
 * neither showing one.
 */
class SeasonRankCardTest extends TestCase
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

    private function night(PokerSeason $season, string $name): PokerTournament
    {
        return PokerTournament::create([
            'name' => $name,
            'start_time' => now()->subDays(3),
            'venue_id' => Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St'])->id,
            'season_id' => $season->id,
        ]);
    }

    private function play(PokerTournament $t, User $u, ?int $place = null, int $points = 0): void
    {
        PokerTournamentRegistrant::create([
            'tournament_id' => $t->id, 'user_id' => $u->id,
            'player_name' => $u->first_name.' '.$u->last_name, 'registered_at' => now(),
        ]);

        if ($place !== null) {
            PokerTournamentResult::create([
                'tournament_id' => $t->id, 'user_id' => $u->id,
                'player_name' => $u->first_name.' '.$u->last_name,
                'place' => $place, 'points' => $points,
            ]);
        }
    }

    private function player(string $first, string $last = 'Player'): User
    {
        return User::factory()->create([
            'first_name' => $first, 'last_name' => $last, 'approval_status' => 'approved',
        ]);
    }

    public function test_the_three_boards_are_ordered_rank_wins_points(): void
    {
        $season = $this->season();
        $viewer = $this->player('Viewer');
        $this->play($this->night($season, 'Night 1'), $viewer, 1, 100);

        $this->actingAs($viewer)->get('/')->assertOk()
            ->assertSeeInOrder(['Season Rank', 'Most Wins', 'Most Points']);
    }

    public function test_the_rank_board_is_points_per_event_entered(): void
    {
        // The whole reason this board exists beside Most Points. The grinder
        // scores more in total; the sharp scores more per night and leads.
        $season = $this->season();
        $grinder = $this->player('Grinder');
        $sharp = $this->player('Sharp');

        $nights = collect(range(1, 4))->map(fn ($i) => $this->night($season, 'Night '.$i));

        // Everyone entered before anyone is scored: registering into a
        // tournament that already has finishes is a late entry, and the shift
        // hook reprices every recorded result to match.
        foreach ($nights as $n) {
            $this->play($n, $grinder);
        }

        $this->play($nights[0], $sharp);

        foreach ($nights as $n) {
            PokerTournamentResult::create([
                'tournament_id' => $n->id, 'user_id' => $grinder->id,
                'player_name' => 'Grinder Player', 'place' => 5, 'points' => 50,
            ]);
        }

        PokerTournamentResult::create([
            'tournament_id' => $nights[0]->id, 'user_id' => $sharp->id,
            'player_name' => 'Sharp Player', 'place' => 1, 'points' => 100,
        ]);

        $html = $this->actingAs($grinder)->get('/')->assertOk()->getContent();

        $rank = substr($html, (int) strpos($html, 'Season Rank'), (int) strpos($html, 'Most Wins') - (int) strpos($html, 'Season Rank'));
        $points = substr($html, (int) strpos($html, 'Most Points'));

        // 100.0 a night beats 50.0 a night, even on half the total points.
        $this->assertMatchesRegularExpression('/Sharp Player.*Grinder Player/s', $rank);

        // And Most Points still says the opposite, which is the point of having
        // both: 200 beats 100.
        $this->assertMatchesRegularExpression('/Grinder Player.*Sharp Player/s', $points);
    }

    public function test_the_rank_board_shows_the_rank(): void
    {
        $season = $this->season();
        $player = $this->player('Ann');

        foreach (range(1, 2) as $i) {
            $this->play($this->night($season, 'Night '.$i), $player);
        }

        foreach (PokerTournament::all() as $t) {
            PokerTournamentResult::create([
                'tournament_id' => $t->id, 'user_id' => $player->id,
                'player_name' => 'Ann Player', 'place' => 1, 'points' => 75,
            ]);
        }

        // The position, not the 75.0 pts/event it is computed from.
        $html = $this->actingAs($player)->get('/')->assertOk()->getContent();
        $rank = substr($html, (int) strpos($html, 'Season Rank'), (int) strpos($html, 'Most Wins') - (int) strpos($html, 'Season Rank'));

        $this->assertStringContainsString('#1', $rank);
        $this->assertStringNotContainsString('pts/event', $rank);
    }

    public function test_it_shows_at_most_three(): void
    {
        $season = $this->season();
        $night = $this->night($season, 'Night 1');

        foreach (range(1, 6) as $i) {
            $this->play($night, $this->player('Player'.$i), $i, 100 - $i);
        }

        $html = $this->actingAs(User::factory()->create(['approval_status' => 'approved']))
            ->get('/')->assertOk()->getContent();

        $rank = substr($html, (int) strpos($html, 'Season Rank'), (int) strpos($html, 'Most Wins') - (int) strpos($html, 'Season Rank'));

        $this->assertSame(3, substr_count($rank, 'p-standing__row'));
    }

    public function test_a_player_with_a_result_but_no_entry_is_not_ranked(): void
    {
        // No denominator, so no ratio. The results screen can create a finish
        // without a registration behind it.
        $season = $this->season();
        $entered = $this->player('Entered');
        $night = $this->night($season, 'Night 1');
        $this->play($night, $entered, 2, 50);

        PokerTournamentResult::create([
            'tournament_id' => $night->id, 'user_id' => $this->player('Unentered')->id,
            'player_name' => 'Unentered Player', 'place' => 1, 'points' => 100,
        ]);

        $html = $this->actingAs($entered)->get('/')->assertOk()->getContent();

        $rank = substr($html, (int) strpos($html, 'Season Rank'), (int) strpos($html, 'Most Wins') - (int) strpos($html, 'Season Rank'));

        $this->assertStringContainsString('Entered Player', $rank);
        $this->assertStringNotContainsString('Unentered Player', $rank);

        // Most Points still counts them: that board asks a different question.
        $this->assertStringContainsString('Unentered Player', substr($html, (int) strpos($html, 'Most Points')));
    }

    public function test_a_stranger_sees_no_standings_at_all(): void
    {
        // A leaderboard is for people playing in it; to a stranger it is a list
        // of names.
        $season = $this->season();
        $this->play($this->night($season, 'Night 1'), $this->player('Ann'), 1, 100);

        $this->get('/')->assertOk()
            ->assertDontSee('Season Rank')
            ->assertDontSee('Most Wins');
    }

    public function test_the_landing_page_and_the_dashboard_agree_on_the_rank(): void
    {
        // The reason rankings() lives on the season rather than in either
        // controller. The player the landing page puts first must be the player
        // the dashboard tells "#1".
        $season = $this->season();
        $sharp = $this->player('Sharp');
        $grinder = $this->player('Grinder');

        $nights = collect(range(1, 3))->map(fn ($i) => $this->night($season, 'Night '.$i));

        foreach ($nights as $n) {
            $this->play($n, $grinder);
        }

        $this->play($nights[0], $sharp);

        foreach ($nights as $n) {
            PokerTournamentResult::create([
                'tournament_id' => $n->id, 'user_id' => $grinder->id,
                'player_name' => 'Grinder Player', 'place' => 4, 'points' => 40,
            ]);
        }

        PokerTournamentResult::create([
            'tournament_id' => $nights[0]->id, 'user_id' => $sharp->id,
            'player_name' => 'Sharp Player', 'place' => 1, 'points' => 100,
        ]);

        $html = $this->actingAs($sharp)->get('/')->assertOk()->getContent();
        $rank = substr($html, (int) strpos($html, 'Season Rank'), (int) strpos($html, 'Most Wins') - (int) strpos($html, 'Season Rank'));

        $this->assertMatchesRegularExpression('/Sharp Player.*Grinder Player/s', $rank);

        // And the dashboard says the same of the same player.
        $this->assertSame(1, $this->actingAs($sharp)->get(route('dashboard'))->assertOk()->viewData('season')['rank']);
    }
}
