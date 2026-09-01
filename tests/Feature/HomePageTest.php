<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home page's season and next-event sections.
 *
 * Most of this is conditional -- on a season existing, on an event being
 * scheduled, on someone being signed in -- and none of those branches was
 * covered before. A page that quietly renders the wrong branch looks fine in
 * every screenshot taken of the other one.
 */
class HomePageTest extends TestCase
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

    private function tournament(PokerSeason $season, ?string $startsIn = '+3 days'): PokerTournament
    {
        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        return PokerTournament::create([
            'name' => 'Weekly Freezeout',
            'scheduled_at' => now()->modify($startsIn),
            'start_time' => now()->modify($startsIn)->modify('+2 hours'),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);
    }

    public function test_the_season_section_shows_the_active_season_and_its_dates(): void
    {
        $this->season();

        $this->get('/')->assertOk()
            ->assertSee('Season 9')
            ->assertSee(now()->subMonth()->format('M j, Y'));
    }

    public function test_the_finale_criteria_are_named(): void
    {
        // All three, because the page previously said only "the top 20 on the
        // leaderboard" and that is not how a place is decided.
        $this->season();

        $this->get('/')->assertOk()
            ->assertSee('points you accumulate', false)
            ->assertSee('tournaments you win', false)
            ->assertSee('venue points', false);
    }

    public function test_the_next_event_is_shown_with_its_venue(): void
    {
        $this->tournament($this->season());

        $this->get('/')->assertOk()
            ->assertSee('Weekly Freezeout')
            ->assertSee('The Grand Card Room');
    }

    public function test_a_past_event_is_not_offered_as_the_next_one(): void
    {
        $this->tournament($this->season(), '-3 days');

        $this->get('/')->assertOk()
            ->assertDontSee('Weekly Freezeout')
            ->assertSee('No events on the calendar');
    }

    public function test_the_empty_calendar_says_so_kindly(): void
    {
        $this->season();

        $this->get('/')->assertOk()
            ->assertSee('No events on the calendar')
            ->assertSee('Check back in a few days', false);
    }

    public function test_standings_are_hidden_from_a_visitor(): void
    {
        // A leaderboard is for the people playing in it; to a stranger it is a
        // list of names they have no reason to care about.
        $season = $this->season();
        $this->resultFor($season, 'Vernice Hintz', place: 1, points: 100);

        $this->get('/')->assertOk()->assertDontSee('Most Points');
    }

    public function test_standings_are_shown_to_a_signed_in_player(): void
    {
        $season = $this->season();
        $this->resultFor($season, 'Vernice Hintz', place: 1, points: 100);
        $this->resultFor($season, 'Autumn Nikolaus', place: 2, points: 60);

        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertSee('Most Points')
            ->assertSee('Vernice Hintz')
            ->assertSee('Autumn Nikolaus');
    }

    public function test_the_wins_list_is_ordered_by_wins_not_points(): void
    {
        // The whole reason there are two lists. A player can lead on points
        // without leading on wins, and if this ordering silently fell back to
        // points the two cards would be identical and nobody would notice.
        // BOTH players must have wins, or the where('wins', '>', 0) filter
        // removes one and the card contains a single name whatever the sort
        // does. Their points order is the OPPOSITE of their wins order, so a
        // fallback to points is visible.
        $season = $this->season();
        $this->resultFor($season, 'Fewer Wins More Points', place: 1, points: 500);
        $this->resultFor($season, 'More Wins Fewer Points', place: 1, points: 10);
        $this->resultFor($season, 'More Wins Fewer Points', place: 1, points: 10);
        $this->resultFor($season, 'More Wins Fewer Points', place: 1, points: 10);

        $body = $this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent();

        $winsCard = substr($body, strpos($body, 'Most Wins'));

        $this->assertLessThan(
            strpos($winsCard, 'Fewer Wins More Points'),
            strpos($winsCard, 'More Wins Fewer Points'),
            'The wins card must lead with the player who has the most wins, not the most points.'
        );
    }

    public function test_a_player_with_no_wins_is_not_listed_as_leading_on_wins(): void
    {
        $season = $this->season();
        $this->resultFor($season, 'Never Won', place: 4, points: 300);

        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertSee('No wins yet this season.');
    }

    /**
     * Results are grouped by user_id, not by player_name, so repeated calls for
     * the same name must reuse the same account -- otherwise "three wins"
     * becomes three separate players with one win each, and a test about
     * ordering has nothing to order.
     *
     * @var array<string, string>
     */
    private array $players = [];

    private function resultFor(PokerSeason $season, string $name, int $place, int $points): void
    {
        $tournament = PokerTournament::create([
            'name' => 'Heat '.uniqid(),
            'scheduled_at' => now()->subDays(5),
            'start_time' => now()->subDays(5),
            'venue_id' => Venue::create(['name' => 'Room '.uniqid(), 'address' => 'x'])->id,
            'season_id' => $season->id,
        ]);

        $this->players[$name] ??= User::factory()->create()->id;

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->players[$name],
            'player_name' => $name,
            'place' => $place,
            'points' => $points,
        ]);
    }
}
