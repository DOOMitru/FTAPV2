<?php

namespace App\Http\Controllers;

use App\Models\PokerSeason;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\VenuePoints;
use Illuminate\View\View;

/**
 * One player, as the league sees them.
 *
 * The dashboard's figures about somebody else: how their season is going and
 * what they have recently won. Not the dashboard's Upcoming Tournaments, which
 * is a to-do list rather than a record -- another player's diary is neither
 * this reader's business nor any use to them.
 *
 * Not users.show either. That is the admin's account view -- approval state,
 * who decided it, the email -- and it answers a different question about a
 * different kind of reader.
 */
class PlayerController extends Controller
{
    public function show(User $player): View
    {
        $currentSeason = PokerSeason::current();
        $season = PokerSeason::figuresFor($currentSeason, $player->id);

        // A player's venue tally is read by that player and by admins. Nobody
        // else, on any page -- so it is removed from the DATA rather than
        // hidden in the template: a gate in Blade stops today's markup, not
        // tomorrow's. The panel renders the row only when this is not null.
        if (! VenuePoints::readableBy(auth()->user(), $player->id)) {
            $season['venuePoints'] = null;
        }

        // Career, matching the dashboard: Recent Results is a history, and a
        // season's worth of it is what the panel above already says.
        $results = PokerTournamentResult::where('user_id', $player->id)
            ->with(['tournament.season'])
            ->latest()
            ->get();

        return view('players.show', [
            'player' => $player,
            'currentSeason' => $currentSeason,
            'season' => $season,
            'results' => $results,
            'isSelf' => auth()->id() === $player->id,
        ]);
    }
}
