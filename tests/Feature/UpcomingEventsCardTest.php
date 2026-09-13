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

    private function card(User $as): string
    {
        $html = $this->actingAs($as)->get(route('dashboard'))->assertOk()->getContent();

        $at = strpos($html, 'Upcoming Tournaments');
        $this->assertNotFalse($at, 'The upcoming card is not on the dashboard.');

        return substr($html, $at, (int) (strpos($html, 'Recent Results', $at) ?: strlen($html)) - $at);
    }

    public function test_only_the_next_five_are_drawn(): void
    {
        // Zero-padded names, so "Night 01" is not a substring of "Night 12" and
        // assertDontSee means what it says.
        $this->schedule(9);

        $card = $this->card($this->player());

        foreach (['Night 01', 'Night 02', 'Night 03', 'Night 04', 'Night 05'] as $shown) {
            $this->assertStringContainsString($shown, $card);
        }

        foreach (['Night 06', 'Night 07', 'Night 08', 'Night 09'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $card);
        }
    }

    public function test_they_are_the_NEXT_five_rather_than_any_five(): void
    {
        // The cap is on the query now, so the ordering has to be there too. A
        // limit applied before an order returns whichever five the database
        // felt like.
        $this->schedule(9);

        $this->actingAs($this->player())->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Night 01', 'Night 02', 'Night 03', 'Night 04', 'Night 05']);
    }

    public function test_the_badge_counts_every_scheduled_event_not_the_five_shown(): void
    {
        // Counting the rows made this read "5 Events" on a league with nine in
        // the diary -- a fact about the card rather than about the league.
        $this->schedule(9);

        $this->assertStringContainsString('9 Events', $this->card($this->player()));
    }

    public function test_the_card_says_how_many_are_hidden_and_where_to_find_them(): void
    {
        $this->schedule(9);

        $card = $this->card($this->player());

        $this->assertStringContainsString('The next 5 of 9 scheduled.', $card);
        $this->assertStringContainsString('See the full schedule', $card);
        $this->assertStringContainsString(route('events'), $card);
    }

    public function test_nothing_is_claimed_to_be_hidden_when_nothing_is(): void
    {
        // "The next 5 of 3" is worse than saying nothing, and a standing note
        // on a complete list is noise.
        $this->schedule(3);

        $card = $this->card($this->player());

        $this->assertStringContainsString('3 Events', $card);
        $this->assertStringNotContainsString('scheduled.', $card);
        $this->assertStringNotContainsString('See the full schedule', $card);
    }

    public function test_exactly_five_scheduled_is_not_a_hidden_one(): void
    {
        // The boundary. A `>=` here would announce a full list as a partial one.
        $this->schedule(5);

        $this->assertStringNotContainsString('See the full schedule', $this->card($this->player()));
    }

    public function test_the_full_schedule_is_always_one_click_away(): void
    {
        // The note comes and goes with the cap; the button does not. Somebody
        // wanting the whole diary should not have to have nine events booked
        // before the link appears.
        $this->schedule(2);

        $card = $this->card($this->player());

        $this->assertStringContainsString('All events', $card);
        $this->assertStringContainsString(route('events'), $card);
    }

    public function test_an_empty_diary_says_so(): void
    {
        $card = $this->card($this->player());

        $this->assertStringContainsString('No upcoming tournaments scheduled.', $card);
        $this->assertStringContainsString('0 Events', $card);
        $this->assertStringNotContainsString('See the full schedule', $card);
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

        $card = $this->card($this->player());

        $this->assertStringNotContainsString('Already Started', $card);
        $this->assertStringContainsString('2 Events', $card);
    }
}
