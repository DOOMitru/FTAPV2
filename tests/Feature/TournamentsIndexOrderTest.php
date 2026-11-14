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
        // The page's props, not a paginator object. The old version reached
        // for ->items() because collect() on a paginator hands back nothing
        // and plucking from it returns a column of nulls that no assertion can
        // tell from an empty page. Props have the rows already.
        $props = $this->actingAs($this->admin())
            ->get(route('poker.tournaments.index'))->assertOk()
            ->viewData('page')['props'];

        return collect($props['tournaments']['data'])->pluck('name')->all();
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

    public function test_the_start_time_column_is_right_aligned(): void
    {
        // Both the header and the cells: .table__num on a bare th loses to
        // .table th, which is why the class is scoped under .table -- a header
        // left at the start edge over right-aligned figures is the fault that
        // scoping was written for.
        //
        // Measured in a browser at 1280: header and every cell share a right
        // edge, and the text inside them does too. On a phone the card layout
        // puts it back to the start edge, since a card places its cells rather
        // than columning them.
        $this->night('Opening Night', now()->toDateTimeString());

        // The markup lives in the .vue file now; the CSS half below is
        // unchanged, because the stylesheet is not being rewritten.
        $page = file_get_contents(resource_path('js/Pages/Poker/Tournaments/Index.vue'));

        $this->assertStringContainsString('<th scope="col" class="table__num">Start Time</th>', $page);
        $this->assertStringContainsString('class="table__num tournaments-index__start"', $page);

        $css = file_get_contents(resource_path('css/3-components/_table.css'));

        $this->assertMatchesRegularExpression(
            '/\.table--cards \.table__num \{\s*text-align: start;/',
            $css,
            'A card has no column to align against, so the phone layout must win.'
        );

        $this->assertGreaterThan(
            strpos($css, '.table .table__num'),
            strpos($css, '.table--cards .table__num'),
            'Equal specificity: declared first, the card rule loses and a date floats right in a card.'
        );
    }

    public function test_a_hundred_fit_on_one_page(): void
    {
        // Ten was a page of a fortnight's play. A season is dozens of nights,
        // and an administrator scanning for one was paging through them.
        foreach (range(1, 100) as $i) {
            $this->night("Night {$i}", now()->subDays($i)->toDateTimeString());
        }

        $props = $this->actingAs($this->admin())
            ->get(route('poker.tournaments.index'))->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(100, $props['tournaments']['data']);
        $this->assertNull($props['tournaments']['status_label'],
            'A hundred nights should be one page, and one page has no status line.');
    }

    public function test_it_still_pages_beyond_a_hundred(): void
    {
        // Raised, not removed: the listing is still paginated, so a league
        // with years of history does not render every night it ever held.
        foreach (range(1, 101) as $i) {
            $this->night("Night {$i}", now()->subDays($i)->toDateTimeString());
        }

        $props = $this->actingAs($this->admin())
            ->get(route('poker.tournaments.index'))->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(100, $props['tournaments']['data']);
        $this->assertNotNull($props['tournaments']['status_label'],
            'Beyond a hundred the list pages, and a paged list carries its status line.');
    }
}
