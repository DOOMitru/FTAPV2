<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * Filtering venue points to a tournament they are not attached to.
 *
 * venue_points records event_date, amount, a player and a VENUE. There is no
 * tournament_id and no relation -- a venue point says somebody earned points at
 * a place on a day, with no game named. So this filter INFERS the link: points
 * earned at the tournament's venue, on the tournament's date.
 *
 * That inference is the owner's decision and it can be wrong in two ways worth
 * writing down: points awarded on a night with no tournament will never appear
 * under any of them, and a venue running two events on one day will show both
 * under either. The page says what it matched on so the reader can tell.
 */
class VenuePointsTournamentFilterTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private function award(Venue $venue, string $date, string $who, int $amount = 40): VenuePoints
    {
        $player = User::factory()->create([
            'first_name' => $who, 'last_name' => 'Player', 'approval_status' => 'approved',
        ]);

        return VenuePoints::create([
            'user_id' => $player->id,
            'user_name' => $who.' Player',
            'venue_id' => $venue->id,
            'amount' => $amount,
            'event_date' => $date,
            'season_id' => PokerSeason::first()->id,
        ]);
    }

    /** A tournament at a known venue on a known day, plus a second venue. */
    private function stage(): array
    {
        $tournament = $this->tournament('Autumn Showdown');
        $tournament->forceFill(['start_time' => Carbon::parse('2026-09-09 19:00')])->save();

        $other = Venue::create(['name' => 'Bushwakker', 'address' => '2 Dewdney Ave']);

        return [$tournament->fresh(), Venue::first(), $other];
    }

    public function test_points_at_the_same_venue_on_the_same_day_are_shown(): void
    {
        [$tournament, $venue] = $this->stage();
        $this->award($venue, '2026-09-09', 'Matching');

        $this->actingAs($this->admin())
            ->get(route('poker.venue-points.index', ['tournament' => $tournament->id]))->assertOk()
            ->assertSee('Matching');
    }

    public function test_points_at_the_same_venue_on_another_day_are_not(): void
    {
        [$tournament, $venue] = $this->stage();
        $this->award($venue, '2026-09-02', 'WrongDay');

        $this->actingAs($this->admin())
            ->get(route('poker.venue-points.index', ['tournament' => $tournament->id]))->assertOk()
            ->assertDontSee('WrongDay');
    }

    public function test_points_on_the_same_day_at_another_venue_are_not(): void
    {
        [$tournament, , $other] = $this->stage();
        $this->award($other, '2026-09-09', 'WrongPlace');

        $this->actingAs($this->admin())
            ->get(route('poker.venue-points.index', ['tournament' => $tournament->id]))->assertOk()
            ->assertDontSee('WrongPlace');
    }

    public function test_the_date_is_the_tournaments_local_day_not_its_timestamp(): void
    {
        // start_time is 7pm; event_date is a plain Y-m-d. Comparing the two
        // without reducing the tournament to a date matches nothing at all.
        [$tournament, $venue] = $this->stage();
        $this->award($venue, '2026-09-09', 'Evening');

        $this->actingAs($this->admin())
            ->get(route('poker.venue-points.index', ['tournament' => $tournament->id]))->assertOk()
            ->assertSee('Evening');
    }

    public function test_it_defaults_to_the_nearest_tournament(): void
    {
        $near = $this->tournament('Near Cup');
        $near->forceFill(['start_time' => now()->subDay()])->save();
        $venue = Venue::first();

        $far = PokerTournament::create([
            'name' => 'Far Cup', 'start_time' => now()->subMonths(3),
            'venue_id' => $venue->id, 'season_id' => PokerSeason::first()->id,
        ]);

        $this->award($venue, now()->subDay()->toDateString(), 'Nearby');
        $this->award($venue, $far->start_time->toDateString(), 'Ancient');

        $this->actingAs($this->admin())->get(route('poker.venue-points.index'))->assertOk()
            ->assertSee('Nearby')
            ->assertDontSee('Ancient');
    }

    public function test_the_page_says_what_it_matched_on(): void
    {
        // The inference is invisible otherwise, and a reader who does not know
        // it is happening will read an empty list as "no points awarded".
        [$tournament, $venue] = $this->stage();
        $this->award($venue, '2026-09-09', 'Matching');

        $this->actingAs($this->admin())
            ->get(route('poker.venue-points.index', ['tournament' => $tournament->id]))->assertOk()
            ->assertSee($venue->name)
            ->assertSee('Sep 09, 2026');
    }

    public function test_an_unknown_tournament_falls_back_rather_than_erroring(): void
    {
        $this->stage();

        $this->actingAs($this->admin())
            ->get(route('poker.venue-points.index', ['tournament' => 'not-a-real-id']))->assertOk();
    }

    public function test_a_league_with_no_tournaments_still_renders(): void
    {
        $this->actingAs($this->admin())->get(route('poker.venue-points.index'))->assertOk();
    }
}
