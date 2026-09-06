<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A tournament has one date: when play starts.
 *
 * It used to have two. scheduled_at was a registration deadline, an hour or so
 * before start_time, and it decided three separate things -- whether a player
 * could enter, whether they could withdraw, and whether an administrator could
 * begin recording finishes. The league does not work that way: people turn up
 * and play, and someone who cannot make it says so on the night.
 *
 * All three now hang on the tournament's results, which is the question that
 * was doing the real work anyway. A place is a position in a field, so a
 * recorded finish describes a field of a particular size; that is what must not
 * change underneath it, and a clock has nothing to do with it.
 *
 * Most of this file used to assert the deadline. What is left asserts that it
 * is gone, and that the window the dashboard shows is drawn on start_time.
 */
class TournamentScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_deadline_column_is_gone_from_the_schema(): void
    {
        // Dropped rather than abandoned in place. A column nobody writes and
        // nobody reads is a question for whoever finds it next, and this one is
        // worse than most: the name suggests it still governs something.
        $this->assertFalse(Schema::hasColumn('tournaments', 'scheduled_at'));
    }

    public function test_a_player_can_enter_after_play_has_begun(): void
    {
        // The case the deadline existed to refuse, and the one this league
        // actually has: somebody arrives at half past seven. It is safe because
        // the shift hook moves any recorded finish down to match the bigger
        // field -- joining a field of ten makes it a field of eleven.
        $player = User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);
        $tournament = $this->makeTournament(startTime: now()->subHour());

        $this->actingAs($player)->post(route('tournaments.register', $tournament))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('tournament_registrants', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_a_player_can_withdraw_after_play_has_begun(): void
    {
        // Symmetric, and the change the owner asked for: the only thing that
        // shuts the door is a recorded finish, not the hour.
        $player = User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);
        $tournament = $this->makeTournament(startTime: now()->subHour());
        $this->enter($tournament, $player);

        $this->actingAs($player)->delete(route('tournaments.unregister', $tournament))
            ->assertSessionHas('status');

        $this->assertSame(0, $tournament->registrants()->count());
    }

    public function test_a_recorded_finish_is_what_closes_the_door(): void
    {
        // The other half. Without this, the two tests above pass just as well
        // against a controller that never refuses a withdrawal at all.
        $player = User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);
        $tournament = $this->makeTournament(startTime: now()->subHour());
        $this->enter($tournament, $player);

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'place' => 1,
            'points' => 0,
        ]);

        $this->actingAs($player)->delete(route('tournaments.unregister', $tournament))
            ->assertSessionHas('error');

        $this->assertSame(1, $tournament->registrants()->count());
    }

    public function test_a_tournament_can_be_created_with_only_a_start_time(): void
    {
        // start_time used to be validated after_or_equal:scheduled_at, so a
        // form posting one date was rejected.
        $admin = User::factory()->create(['is_admin' => true]);
        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        PokerSeason::create(['name' => 'Season 1', 'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(), 'is_current' => true]);

        $this->actingAs($admin)->post(route('poker.tournaments.store'), [
            'name' => 'Weekly Freezeout',
            'start_time' => now()->addDays(6)->format('Y-m-d H:i:s'),
            'venue_id' => $venue->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, PokerTournament::count());
    }

    public function test_a_start_time_can_be_moved_earlier(): void
    {
        // Refused before, because it would have crossed the deadline. There is
        // nothing left for it to cross.
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = $this->makeTournament(startTime: now()->addDays(7));

        $this->actingAs($admin)->put(route('poker.tournaments.update', $tournament), [
            'name' => $tournament->name,
            'start_time' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'venue_id' => $tournament->venue_id,
            'season_id' => $tournament->season_id,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($tournament->fresh()->start_time->lessThan(now()->addDays(6)));
    }

    public function test_the_dashboard_window_is_drawn_on_start_time(): void
    {
        // Both directions in one test, because the boundary is the point: a
        // tournament an hour from now is upcoming, and one that began two hours
        // ago is not. Under the old rule the first of these had "closed".
        $user = User::factory()->create(['is_admin' => false]);

        $soon = $this->makeTournament(startTime: now()->addHour());
        $started = $this->makeTournament(startTime: now()->subHours(2));

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('upcomingTournaments', fn ($tournaments) => $tournaments->contains('id', $soon->id)
                && ! $tournaments->contains('id', $started->id));
    }

    private function enter(PokerTournament $tournament, User $player): void
    {
        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'registered_at' => now(),
        ]);
    }

    private function makeTournament(\DateTimeInterface $startTime): PokerTournament
    {
        $venue = Venue::firstOrCreate(['name' => 'The Grand Card Room'], ['address' => '100 Casino Blvd']);

        $season = PokerSeason::firstOrCreate(['name' => 'Season 1'], [
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Weekly Freezeout',
            'start_time' => $startTime,
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);
    }
}
