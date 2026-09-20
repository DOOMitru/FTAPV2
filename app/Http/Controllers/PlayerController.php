<?php

namespace App\Http\Controllers;

use App\Models\PokerSeason;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Support\SeasonFigures;
use Inertia\Inertia;
use Inertia\Response;

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
    public function show(User $player): Response
    {
        $isSelf = auth()->id() === $player->id;

        // Career, matching the dashboard: Recent Results is a history, and a
        // season's worth of it is what the panel above already says.
        $results = PokerTournamentResult::where('user_id', $player->id)
            ->with(['tournament.season'])
            ->latest()
            ->get();

        return Inertia::render('Players/Show', [
            // The nickname leads where a name has one. The full name titles
            // the page -- display_name prefers a nickname and falls back to
            // the first name alone, which is right beside an avatar and wrong
            // at the head of a profile.
            'eyebrow' => filled($player->nickname) ? $player->nickname : __('Player'),
            'title' => trim($player->first_name.' '.$player->last_name),
            'is_self' => $isSelf,

            // Five, chosen here. The Blade took ->take(5) in the template and
            // asked ->count() of the whole collection for the note; both facts
            // come over as props so the page places rather than decides.
            'results' => $results->take(5)->map(fn (PokerTournamentResult $result) => [
                'id' => $result->id,
                'tournament_name' => $result->tournament->name,
                'tournament_href' => route('tournaments.show', $result->tournament),
                'date_label' => $result->tournament->start_time->format('M d, Y'),
                'place' => $result->place,
                'points_label' => number_format($result->points),
            ])->values()->all(),
            'has_more_results' => $results->count() > 5,

            // The venue-points rule is inside SeasonFigures, which is why the
            // readableBy() call that used to stand here is gone: the dashboard
            // draws the same panel, and two copies of that rule would be free
            // to disagree about who may read a tally.
            ...SeasonFigures::props(PokerSeason::current(), $player->id, auth()->user(), self: $isSelf),
        ]);
    }
}
