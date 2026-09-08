<?php

namespace Tests\Concerns;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;

/**
 * Fixtures for the three test classes that build a tournament and score it.
 *
 * A trait rather than a shared base class. PHPUnit runs a parent's test methods
 * again inside every subclass, so extending one test case to borrow its helpers
 * silently duplicates its whole suite -- the assertions still pass, the counts
 * quietly stop meaning anything, and a failure is reported twice under two
 * class names.
 */
trait BuildsTournaments
{
    protected function tournament(string $name = 'Autumn Showdown'): PokerTournament
    {
        $season = PokerSeason::firstOrCreate(['name' => 'Season 40'], [
            'start_date' => '2026-08-01', 'end_date' => '2026-10-31', 'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => $name,
            'start_time' => now()->subHour(),
            'venue_id' => Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St'])->id,
            'season_id' => $season->id,
        ]);
    }

    protected function enter(PokerTournament $tournament, User $player): PokerTournamentRegistrant
    {
        return PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'registered_at' => now(),
        ]);
    }

    protected function score(PokerTournament $tournament, User $player, int $place, int $points): PokerTournamentResult
    {
        return PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'place' => $place,
            'points' => $points,
        ]);
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }
}
