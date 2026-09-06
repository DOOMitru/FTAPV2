<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Venue points belong to a season, and the season is stored.
 *
 * It used to be worked out at read time by asking which season's date range
 * contained the row's event_date. That is not a fact, it is a coincidence of
 * two other numbers -- so editing a season's start or end date moved venue
 * points between seasons and changed who qualified for the finale, silently,
 * because a coincidence cannot report that it has changed its mind.
 */
class VenuePointsSeasonTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    private function season(string $start = '2026-06-01', string $end = '2026-12-31', string $name = 'Season 40'): PokerSeason
    {
        return PokerSeason::create([
            'name' => $name,
            'start_date' => $start,
            'end_date' => $end,
            'is_current' => true,
        ]);
    }

    private function record(string $date, ?Venue $venue = null, ?User $player = null): \Illuminate\Testing\TestResponse
    {
        $venue ??= Venue::create(['name' => 'Diamond Club', 'address' => '1 Card Street']);
        $player ??= User::factory()->create();

        return $this->actingAs($this->admin())->post(route('poker.venue-points.store'), [
            'venue_id' => $venue->id,
            'event_date' => $date,
            'user_id' => $player->id,
            'user_name' => 'Ada Lovelace',
            'amount' => 5,
        ]);
    }

    /** A finish in the season, which is what puts a player on its leaderboard. */
    private function finishIn(PokerSeason $season, User $player): void
    {
        $tournament = PokerTournament::create([
            'name' => 'Wednesday Night Poker',
            'start_time' => now()->subDays(3),
            'venue_id' => Venue::create(['name' => 'Hall '.uniqid(), 'address' => 'x'])->id,
            'season_id' => $season->id,
        ]);

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => 'Ada Lovelace',
            'place' => 1,
            'points' => 100,
        ]);
    }

    public function test_recording_points_stamps_the_season_the_date_falls_in(): void
    {
        $season = $this->season();

        $this->record('2026-08-14')->assertSessionHasNoErrors();

        $this->assertSame($season->id, VenuePoints::firstOrFail()->season_id);
    }

    public function test_a_date_no_season_covers_is_refused(): void
    {
        // Points outside every season count toward nothing, so this is a data
        // entry mistake worth catching at the form rather than a row that
        // quietly does nothing for the rest of the year.
        $this->season();

        $this->record('2025-01-05')->assertSessionHasErrors('event_date');

        $this->assertSame(0, VenuePoints::count());
    }

    public function test_moving_the_season_dates_no_longer_moves_the_points(): void
    {
        // The whole reason for the column, checked where it matters: the
        // leaderboard figure that decides who plays the finale.
        //
        // The player needs a finish as well as venue points, because the
        // leaderboard is built from results -- a first version of this test
        // gave them only venue points, so they never appeared on it and the
        // assertion could not have failed.
        $season = $this->season();
        $player = User::factory()->create();

        $this->finishIn($season, $player);
        $this->record('2026-08-14', player: $player)->assertSessionHasNoErrors();

        // The date now falls outside the season it was recorded in. Attributed
        // by date, this figure would drop to zero.
        $season->update(['start_date' => '2026-09-01', 'end_date' => '2026-12-31']);

        $this->actingAs($this->admin())
            ->get(route('seasons.show', $season))
            ->assertOk()
            ->assertViewHas('leaderboard', function ($leaderboard) use ($player) {
                $entry = collect($leaderboard)->firstWhere('user.id', $player->id);

                return $entry !== null && $entry['venue_points'] === 5;
            });

        $this->assertSame($season->id, VenuePoints::firstOrFail()->season_id);
    }

    public function test_editing_a_row_restamps_it_when_the_date_changes(): void
    {
        // The date is what decides the season, so changing the date has to
        // reconsider it. Left alone, a row edited from August to next season
        // would keep counting toward this one.
        $first = $this->season('2026-01-01', '2026-06-30', 'Season 39');
        $second = $this->season('2026-07-01', '2026-12-31', 'Season 40');

        $this->record('2026-03-10');
        $point = VenuePoints::firstOrFail();
        $this->assertSame($first->id, $point->season_id);

        $this->actingAs($this->admin())->put(route('poker.venue-points.update', $point), [
            'venue_id' => $point->venue_id,
            'event_date' => '2026-08-14',
            'user_id' => $point->user_id,
            'user_name' => $point->user_name,
            'amount' => $point->amount,
        ])->assertSessionHasNoErrors();

        $this->assertSame($second->id, $point->fresh()->season_id);
    }

    public function test_an_edit_onto_a_date_no_season_covers_is_refused(): void
    {
        $this->season();
        $this->record('2026-08-14');
        $point = VenuePoints::firstOrFail();

        $this->actingAs($this->admin())->put(route('poker.venue-points.update', $point), [
            'venue_id' => $point->venue_id,
            'event_date' => '2025-01-05',
            'user_id' => $point->user_id,
            'user_name' => $point->user_name,
            'amount' => $point->amount,
        ])->assertSessionHasErrors('event_date');

        // event_date is not cast on the model, so it comes back as the string
        // it was stored as. The index view parses it with Carbon for the same
        // reason.
        $this->assertSame('2026-08-14', $point->fresh()->event_date);
    }

    public function test_overlapping_seasons_resolve_to_the_earlier_one(): void
    {
        // Nothing stops two seasons overlapping, so the answer has to be
        // deterministic rather than whatever the database returns first. The
        // backfill migration orders the same way.
        $early = $this->season('2026-01-01', '2026-12-31', 'Season 39');
        $late = $this->season('2026-08-01', '2027-01-31', 'Season 40');

        $this->record('2026-08-14');

        $this->assertSame($early->id, VenuePoints::firstOrFail()->season_id);
        $this->assertNotSame($late->id, VenuePoints::firstOrFail()->season_id);
    }

    public function test_deleting_a_season_leaves_the_points_rather_than_the_history(): void
    {
        // nullOnDelete: a removed season should not take a venue's contribution
        // record with it. The row survives, unattributed.
        $season = $this->season();
        $this->record('2026-08-14');

        $season->delete();

        $this->assertSame(1, VenuePoints::count());
        $this->assertNull(VenuePoints::firstOrFail()->season_id);
    }
}
