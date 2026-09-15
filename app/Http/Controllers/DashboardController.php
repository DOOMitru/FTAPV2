<?php

namespace App\Http\Controllers;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        // The next five, and how many there are altogether.
        //
        // take(5) on the QUERY, not on the collection it returns. Slicing after
        // the fact loaded every future tournament in the league to display five
        // of them, and a league that schedules a year ahead pays for all of it
        // on every dashboard load.
        $upcoming = PokerTournament::where('start_time', '>', now());
        $upcomingCount = (clone $upcoming)->count();

        $upcomingTournaments = $upcoming
            ->with(['venue', 'registrants'])
            // The row offers a withdrawal only while nothing is recorded, and
            // hasRecordedResults() queries per tournament without a count.
            ->withCount('results')
            ->orderBy('start_time', 'asc')
            ->take(5)
            ->get();

        // Career, and deliberately so: Recent Results is a history, not a
        // season. Everything else on this page is the season in front of you.
        $userResults = PokerTournamentResult::where('user_id', $user->id)
            ->with(['tournament.season'])
            ->latest()
            ->get();

        $currentSeason = PokerSeason::current();
        $season = PokerSeason::figuresFor($currentSeason, $user->id);

        return view('dashboard', compact(
            'upcomingTournaments',
            'upcomingCount',
            'userResults',
            'currentSeason',
            'season'
        ));
    }
}
