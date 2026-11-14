<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's Upcoming Tournaments card.
 *
 * Five rows, because the dashboard is a summary and a league that schedules a
 * season ahead would otherwise turn this card into the page. What the cap costs
 * is the answer to "is that all of them?", so the card says how many there are
 * and where the rest live -- the public events page, which is the real
 * schedule.
 */
class UpcomingEventsCardTest extends TestCase
{
    use RefreshDatabase;

    private function player(): User
    {
        return User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);
    }

    private function schedule(int $count): void
    {
        $season = PokerSeason::firstOrCreate(['name' => 'Season 9'], [
            'start_date' => now()->subMonth(), 'end_date' => now()->addYear(), 'is_current' => true,
        ]);

        $venue = Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St']);

        // Inserted LAST first, so insertion order is the reverse of
        // chronological order.
        //
        // This is load-bearing. With the rows inserted in date order, a query
        // that forgot to sort still returned them in date order on SQLite --
        // storage order -- and every assertion below passed against a LIMIT
        // with no ORDER BY, which is whichever five the database feels like and
        // would differ on MySQL. Scrambling the insert is what makes the
        // ordering actually tested rather than coincidentally satisfied.
        foreach (array_reverse(range(1, $count)) as $i) {
            PokerTournament::create([
                'name' => sprintf('Night %02d', $i),
                'start_time' => now()->addDays($i),
                'venue_id' => $venue->id,
                'season_id' => $season->id,
            ]);
        }
    }

    /**
     * The names the card is sent, in the order it is sent them.
     *
     * The card is Vue: which rows it draws is the `upcoming` prop, and the
     * order is the order of that array. The old version cut the rendered HTML
     * between two card titles and searched it -- the cut is unnecessary now
     * because the rows are their own prop.
     *
     * @return list<string>
     */
    private function names(User $as): array
    {
        return collect($this->props($as)['upcoming'])->pluck('name')->all();
    }

    /**
     * Everything the dashboard is sent.
     *
     * @return array<string, mixed>
     */
    private function props(User $as): array
    {
        return $this->actingAs($as)->get(route('dashboard'))->assertOk()
            ->viewData('page')['props'];
    }

    /** The card's markup, cut from the page at its own title. */
    private function card(): string
    {
        $page = (string) file_get_contents(resource_path('js/Pages/Dashboard.vue'));

        $at = strpos($page, 'title="Upcoming Tournaments"');
        $this->assertNotFalse($at, 'The upcoming card is not on the dashboard.');

        return substr($page, $at, (int) (strpos($page, 'title="Recent Results"', $at) ?: strlen($page)) - $at);
    }

    public function test_only_the_next_five_are_drawn(): void
    {
        // Zero-padded names, so "Night 01" is not a substring of "Night 12" and
        // assertDontSee means what it says.
        $this->schedule(9);

        $names = $this->names($this->player());

        $this->assertSame(['Night 01', 'Night 02', 'Night 03', 'Night 04', 'Night 05'], $names);
    }

    public function test_they_are_the_NEXT_five_rather_than_any_five(): void
    {
        // The cap is on the query now, so the ordering has to be there too. A
        // limit applied before an order returns whichever five the database
        // felt like.
        $this->schedule(9);

        // assertSame on the list, which is assertSeeInOrder with no gaps
        // allowed: a sixth name between two of these would fail it.
        $this->assertSame(
            ['Night 01', 'Night 02', 'Night 03', 'Night 04', 'Night 05'],
            $this->names($this->player())
        );
    }

    public function test_the_header_carries_no_count_and_no_link(): void
    {
        // Both were a second copy of what the note under the list already says,
        // sitting in the corner furthest from the list they described.
        $this->schedule(9);

        $card = $this->card();

        $this->assertStringNotContainsString('9 Events', $card);
        $this->assertStringNotContainsString('All events', $card);
    }

    public function test_the_card_says_how_many_are_hidden_and_where_to_find_them(): void
    {
        $this->schedule(9);

        // "of 9", not "of 5": the figure is every event scheduled, not the
        // rows drawn. This is what the header badge used to hold, and the only
        // place that distinction is now visible.
        $this->assertSame('The next 5 of 9 scheduled.', $this->props($this->player())['upcoming_note']);

        $card = $this->card();

        $this->assertStringContainsString('See the full schedule', $card);
        $this->assertStringContainsString('href="/events"', $card);
    }

    public function test_nothing_is_claimed_to_be_hidden_when_nothing_is(): void
    {
        // "The next 5 of 3" is worse than saying nothing, and a standing note
        // on a complete list is noise.
        $this->schedule(3);

        $props = $this->props($this->player());

        $this->assertContains('Night 03', collect($props['upcoming'])->pluck('name')->all());

        // Null, not a sentence: the note and its link are both drawn from it,
        // so nothing is claimed and nothing is offered.
        $this->assertNull($props['upcoming_note']);
        $this->assertStringContainsString('v-if="upcoming_note"', $this->card());
    }

    public function test_exactly_five_scheduled_is_not_a_hidden_one(): void
    {
        // The boundary. A `>=` here would announce a full list as a partial one.
        $this->schedule(5);

        $this->assertNull($this->props($this->player())['upcoming_note']);
    }

    public function test_a_complete_list_offers_no_route_to_the_schedule(): void
    {
        // The consequence of dropping the header button, recorded rather than
        // discovered later: with five or fewer events booked, this card no
        // longer links to the public schedule at all. Nothing is hidden, so
        // there is nothing the note needs to say -- but somebody wanting the
        // whole diary has to reach it from the public site.
        $this->schedule(2);

        $this->assertNull($this->props($this->player())['upcoming_note']);
    }

    public function test_an_empty_diary_says_so(): void
    {
        $props = $this->props($this->player());

        $this->assertSame([], $props['upcoming']);
        $this->assertNull($props['upcoming_note']);

        $this->assertStringContainsString('No upcoming tournaments scheduled.', $this->card());
    }

    public function test_a_tournament_already_under_way_is_not_upcoming(): void
    {
        $this->schedule(2);

        PokerTournament::create([
            'name' => 'Already Started',
            'start_time' => now()->subHour(),
            'venue_id' => Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St'])->id,
            'season_id' => PokerSeason::firstOrCreate(['name' => 'Season 9'], [
                'start_date' => now()->subMonth(), 'end_date' => now()->addYear(), 'is_current' => true,
            ])->id,
        ]);

        $this->assertSame(['Night 01', 'Night 02'], $this->names($this->player()));
    }

    public function test_the_note_is_two_elements_so_it_can_stack(): void
    {
        // Whether they share a line is CSS and cannot be asserted here; it was
        // measured in the browser instead -- one row at 1440 and 800, two rows
        // at 500, with the link on a single unbroken line at all three.
        //
        // What IS assertable is the markup that makes it possible. As plain
        // inline content the link wrapped mid-phrase on a phone, "See the full"
        // ending one line and "schedule" starting the next with the underline
        // broken across both, and no stylesheet can fix that from the outside.
        $this->schedule(9);

        $card = $this->card();

        $this->assertStringContainsString('card__note--split', $card);

        // The sentence is its own element. It is a prop now, so the element
        // holds the binding rather than the words -- and the words themselves
        // are checked above, where the controller builds them.
        $this->assertMatchesRegularExpression(
            '/<span>\{\{ upcoming_note \}\}<\/span>/',
            $card,
            'The sentence must be its own element.'
        );

        // And no stray space left inside the translatable string now that the
        // gap between them is the flex gap.
        $this->assertStringNotContainsString(
            'scheduled. ',
            $this->props($this->player())['upcoming_note'] ?? ''
        );
    }
}
