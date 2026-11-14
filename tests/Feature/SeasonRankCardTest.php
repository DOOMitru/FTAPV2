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
 * Two boards: Season Rank, then Most Wins. There were three until 2026-11-14,
 * with Most Points beside the rank; once the owner made rank mean season
 * points the two were the same list, and they merged into one card that shows
 * each player's place and points.
 *
 * The board is PokerSeason::rankings(), the same one the dashboard puts a
 * player's own position against. That sharing is the point of these tests:
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

    /**
     * A player who entered a night of their own and scored $points there.
     *
     * Their own night, because entering a night that already has results
     * reprices them -- to nil, with no points structure -- and a player on nil
     * is not ranked.
     */
    private function scorer(PokerSeason $season, string $first, int $points, int $venuePoints = 0): User
    {
        $user = $this->player($first);
        $this->play($this->night($season, "{$first}'s night"), $user, 1, $points);

        if ($venuePoints > 0) {
            \App\Models\VenuePoints::create([
                'venue_id' => Venue::first()->id, 'user_id' => $user->id, 'user_name' => $first.' Player',
                'event_date' => now()->subDays(3)->toDateString(), 'amount' => $venuePoints,
                'season_id' => $season->id,
            ]);
        }

        return $user;
    }

    /** The Season Rank card's markup, cut from the landing page. */
    private function rankCard(User $viewer): string
    {
        $html = $this->actingAs($viewer)->get('/')->assertOk()->getContent();
        $start = (int) strpos($html, 'Season Rank');

        return substr($html, $start, (int) strpos($html, 'Most Wins') - $start);
    }

    public function test_the_boards_are_rank_then_wins(): void
    {
        $season = $this->season();
        $viewer = $this->scorer($season, 'Viewer', 100);

        $this->actingAs($viewer)->get('/')->assertOk()
            ->assertSeeInOrder(['Season Rank', 'Most Wins'])
            ->assertDontSee('Most Points');
    }

    public function test_the_rank_card_shows_places_and_points(): void
    {
        $season = $this->season();
        $this->scorer($season, 'Ben', 120);
        $ana = $this->scorer($season, 'Ana', 1400);

        $card = $this->rankCard($ana);

        $this->assertMatchesRegularExpression('/Ana Player.*1,400 pts.*Ben Player.*120 pts/s', $card);
        $this->assertStringNotContainsString('#1', $card, 'The badge is the place; the figure is the points.');
    }

    public function test_a_tie_at_third_shows_everyone_tied(): void
    {
        // Four rows, not an arbitrary three: Cal and Dev share third, and
        // cutting one of them would rank them differently by omission.
        $season = $this->season();
        $viewer = $this->scorer($season, 'Ana', 300);
        $this->scorer($season, 'Ben', 200);
        $this->scorer($season, 'Cal', 100);
        $this->scorer($season, 'Dev', 100);
        $this->scorer($season, 'Eve', 50);

        $card = $this->rankCard($viewer);

        $this->assertSame(4, substr_count($card, 'p-standing__row'));
        $this->assertStringNotContainsString('Eve Player', $card);
    }

    public function test_venue_points_break_a_tie_without_being_shown(): void
    {
        $season = $this->season();
        $viewer = $this->scorer($season, 'Ana', 100);
        $this->scorer($season, 'Ben', 100, venuePoints: 7);

        $card = $this->rankCard($viewer);

        $this->assertMatchesRegularExpression('/Ben Player.*Ana Player/s', $card);
        $this->assertStringNotContainsString('7 ', strip_tags($card));
    }

    public function test_it_shows_at_most_three(): void
    {
        $season = $this->season();

        foreach (range(1, 6) as $i) {
            $this->scorer($season, 'Player'.$i, 100 - $i);
        }

        $card = $this->rankCard(User::factory()->create(['approval_status' => 'approved']));

        $this->assertSame(3, substr_count($card, 'p-standing__row'));
    }

    public function test_a_player_with_a_result_but_no_entry_is_not_ranked(): void
    {
        // The results screen can create a finish without a registration behind
        // it; entering is what puts a player on the board.
        $season = $this->season();
        $entered = $this->scorer($season, 'Entered', 50);

        PokerTournamentResult::create([
            'tournament_id' => $this->night($season, 'Unentered night')->id,
            'user_id' => $this->player('Unentered')->id,
            'player_name' => 'Unentered Player', 'place' => 1, 'points' => 100,
        ]);

        $card = $this->rankCard($entered);

        $this->assertStringContainsString('Entered Player', $card);
        $this->assertStringNotContainsString('Unentered Player', $card);
    }

    public function test_wins_without_points_still_show_the_wins_card(): void
    {
        // A win worth nothing -- no points structure yet, or one that pays
        // only the places below. Nobody is ranked, but somebody HAS won, and
        // hiding the whole grid would hide that too.
        $season = $this->season();
        $viewer = $this->scorer($season, 'Ana', 0);

        $this->actingAs($viewer)->get('/')->assertOk()
            ->assertSeeInOrder(['Season Rank', 'No points scored yet this season.', 'Most Wins', 'Ana Player']);
    }

    public function test_with_nothing_won_or_scored_there_are_no_boards(): void
    {
        $season = $this->season();
        $viewer = $this->player('Ana');
        $this->play($this->night($season, 'Night'), $viewer, 4, 0);

        $this->actingAs($viewer)->get('/')->assertOk()
            ->assertDontSee('Season Rank')
            ->assertDontSee('Most Wins');
    }

    public function test_a_stranger_sees_no_standings_at_all(): void
    {
        // A leaderboard is for people playing in it; to a stranger it is a list
        // of names.
        $season = $this->season();
        $this->scorer($season, 'Ann', 100);

        $this->get('/')->assertOk()
            ->assertDontSee('Season Rank')
            ->assertDontSee('Most Wins');
    }

    public function test_the_landing_page_and_the_dashboard_agree_on_the_rank(): void
    {
        // The reason rankings() lives on the season rather than in either
        // controller. The player the landing page puts first must be the player
        // the dashboard tells "#1" -- and a shared place must read the same on
        // both.
        $season = $this->season();
        $leader = $this->scorer($season, 'Leader', 300);
        $a = $this->scorer($season, 'Ada', 120);
        $b = $this->scorer($season, 'Bo', 120);

        $this->assertMatchesRegularExpression('/Leader Player.*Ada Player.*Bo Player/s', $this->rankCard($leader));

        foreach ([$leader->id => '#1', $a->id => '#2', $b->id => '#2'] as $id => $label) {
            $this->assertSame($label, $this->actingAs(User::find($id))->get(route('dashboard'))
                ->assertOk()->viewData('page')['props']['season']['rank_label']);
        }
    }
}
