<?php

namespace App\Http\Controllers;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $now = now();

        // 1. Upcoming Tournaments
        $upcomingTournaments = PokerTournament::with(['venue', 'registrants'])
            ->where('start_time', '>', $now)
            ->orderBy('start_time', 'asc')
            ->take(5)
            ->get();

        // 2. User Personalized Statistics
        $userResults = PokerTournamentResult::where('user_id', $user->id)
            ->with(['tournament.season'])
            ->latest()
            ->get();

        $totalPoints = $userResults->sum('points');
        $tournamentsPlayed = $userResults->count();
        $podiums = $userResults->whereIn('place', [1, 2, 3])->count();
        $wins = $userResults->where('place', 1)->count();

        // 3. Current Season Standing
        $currentSeason = PokerSeason::current();

        $seasonRank = null;
        $seasonPoints = 0;

        if ($currentSeason) {
            // Queried from the results table rather than through
            // $currentSeason->results(), which is a HasManyThrough.
            //
            // That relation silently adds `tournaments`.`season_id` as
            // `laravel_through_key` to the SELECT so it can match rows back to
            // their parent. Harmless normally; fatal beside a GROUP BY, because
            // MySQL's ONLY_FULL_GROUP_BY -- on by default since 8.0 -- rejects a
            // selected column that is neither grouped nor aggregated. SQLite
            // does not enforce that rule, so this ran locally for months and
            // failed on the first query against production's driver.
            $seasonLeaderboard = PokerTournamentResult::query()
                ->whereHas('tournament', fn ($query) => $query->where('season_id', $currentSeason->id))
                ->selectRaw('user_id, SUM(points) as total_points')
                ->groupBy('user_id')
                ->orderByDesc('total_points')
                ->get();

            $seasonPoints = $seasonLeaderboard->where('user_id', $user->id)->first()?->total_points ?? 0;
            
            $rankIndex = $seasonLeaderboard->search(fn($item) => $item->user_id === $user->id);
            $seasonRank = ($rankIndex !== false) ? $rankIndex + 1 : null;
        }

        return view('dashboard', compact(
            'upcomingTournaments',
            'userResults',
            'totalPoints',
            'tournamentsPlayed',
            'podiums',
            'wins',
            'currentSeason',
            'seasonRank',
            'seasonPoints'
        ));
    }
}
