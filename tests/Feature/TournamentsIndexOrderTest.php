<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order and size of the tournaments listing.
 *
 * It was ordered by latest(), which is created_at -- the order the rows were
 * typed in. A league that schedules a season ahead in one sitting types them
 * in an order that has nothing to do with when they are played, so a night
 * added last week sat above the one being played tonight.
 */
class TournamentsIndexOrderTest extends TestCase
{
    use RefreshDatabase;

    private PokerSeason $season;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = PokerSeason::create([
            'name' => 'Season 9', 'start_date' => now()->subYear(),
            'end_date' => now()->addYear(), 'is_current' => true,
        ]);

        $this->venue = Venue::create(['name' => 'Hall', 'address' => '1 St']);
    }

    private function night(string $name, string $when): PokerTournament
    {
        return PokerTournament::create([
            'name' => $name, 'start_time' => $when,
            'venue_id' => $this->venue->id, 'season_id' => $this->season->id,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    /** @return array<int, string> the names, in the order the page lists them */
    private function listed(): array
    {
        // ->items(), not the paginator itself: collect() on a paginator does
        // not hand back the records, and plucking from it returns a column of
        // nulls that no assertion can tell from an empty page.
        $paginator = $this->actingAs($this->admin())
            ->get(route('poker.tournaments.index'))->assertOk()->viewData('tournaments');

        return collect($paginator->items())->pluck('name')->all();
    }

    public function test_the_newest_night_leads(): void
    {
        // Created in an order that has nothing to do with their dates, which is
        // the whole point: created_at would put "Typed first" at the bottom.
        $this->night('Typed first', now()->addWeek()->toDateTimeString());
        $this->night('Typed second', now()->subMonth()->toDateTimeString());
        $this->night('Typed third', now()->subDay()->toDateTimeString());

        $this->assertSame(
            ['Typed first', 'Typed third', 'Typed second'],
            $this->listed()
        );
    }

    public function test_the_order_does_not_follow_the_order_they_were_entered(): void
    {
        // The control for the test above: if this list were still ordered by
        // created_at, these two would come back the other way round.
        $this->night('Entered first, played later', now()->addDays(10)->toDateTimeString());
        $this->night('Entered second, played earlier', now()->addDays(2)->toDateTimeString());

        $this->assertSame(
            ['Entered first, played later', 'Entered second, played earlier'],
            $this->listed()
        );
    }

    public function test_a_hundred_fit_on_one_page(): void
    {
        // Ten was a page of a fortnight's play. A season is dozens of nights,
        // and an administrator scanning for one was paging through them.
        foreach (range(1, 100) as $i) {
            $this->night("Night {$i}", now()->subDays($i)->toDateTimeString());
        }

        $paginator = $this->actingAs($this->admin())
            ->get(route('poker.tournaments.index'))->assertOk()->viewData('tournaments');

        $this->assertSame(100, $paginator->perPage());
        $this->assertCount(100, $paginator->items());
        $this->assertFalse($paginator->hasMorePages(), 'A hundred nights should be one page.');
    }

    public function test_it_still_pages_beyond_a_hundred(): void
    {
        // Raised, not removed: the listing is still paginated, so a league
        // with years of history does not render every night it ever held.
        foreach (range(1, 101) as $i) {
            $this->night("Night {$i}", now()->subDays($i)->toDateTimeString());
        }

        $paginator = $this->actingAs($this->admin())
            ->get(route('poker.tournaments.index'))->assertOk()->viewData('tournaments');

        $this->assertCount(100, $paginator->items());
        $this->assertTrue($paginator->hasMorePages());
    }
}
