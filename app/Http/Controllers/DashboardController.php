<?php

namespace App\Http\Controllers;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\VenuePoints;
use Illuminate\Support\Collection;
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
        $season = $this->seasonFigures($currentSeason, $user->id);

        return view('dashboard', compact(
            'upcomingTournaments',
            'upcomingCount',
            'userResults',
            'currentSeason',
            'season'
        ));
    }

    /**
     * One player's standing in the season now being played.
     *
     * Every figure here is scoped to $currentSeason. A career total answers a
     * question nobody on this page is asking: the league runs in seasons, the
     * standings reset with them, and "how am I doing" means "this season".
     *
     * @return array<string, mixed>
     */
    private function seasonFigures(?PokerSeason $currentSeason, string $userId): array
    {
        $empty = [
            'events' => 0, 'points' => 0, 'venuePoints' => 0,
            'first' => 0, 'second' => 0, 'third' => 0,
            'rank' => null, 'ranked' => 0, 'perEvent' => null,
        ];

        if (! $currentSeason) {
            return $empty;
        }

        $inSeason = fn ($query) => $query->where('season_id', $currentSeason->id);

        $results = PokerTournamentResult::where('user_id', $userId)
            ->whereHas('tournament', $inSeason)
            ->get(['place', 'points']);

        $events = PokerTournamentRegistrant::where('user_id', $userId)
            ->whereHas('tournament', $inSeason)
            ->count();

        $points = (int) $results->sum('points');

        return [
            'events' => $events,
            'points' => $points,
            // season_id, not the season's date range. Venue points carry the
            // season they were earned in precisely so that editing a season's
            // dates cannot move them between seasons -- see the note on
            // VenuePoints::$fillable, where inferring it was the bug.
            'venuePoints' => (int) VenuePoints::where('user_id', $userId)
                ->where('season_id', $currentSeason->id)
                ->sum('amount'),
            'first' => $results->where('place', 1)->count(),
            'second' => $results->where('place', 2)->count(),
            'third' => $results->where('place', 3)->count(),
            // Points per event rather than points: a player who enters half the
            // nights is not behind somebody who entered all of them and scored
            // the same. Null rather than zero when nothing has been entered --
            // there is no average of no events, and 0.0 reads as a bad one.
            'perEvent' => $events > 0 ? (float) $points / $events : null,
            ...$this->rank($currentSeason, $userId),
        ];
    }

    /**
     * Where this player sits on the season's board.
     *
     * The board itself is PokerSeason::rankings(), not a second copy of the
     * arithmetic here: the landing page shows its top three, and a rank that
     * meant one thing there and something else here would be worse than no rank
     * at all.
     *
     * @return array{rank: int|null, ranked: int}
     */
    private function rank(PokerSeason $currentSeason, string $userId): array
    {
        $board = $currentSeason->rankings();

        $index = $board->search(fn (array $row) => $row['user_id'] === $userId);

        return [
            'rank' => $index === false ? null : $index + 1,
            'ranked' => $board->count(),
        ];
    }
}
