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

        $upcomingTournaments = PokerTournament::with(['venue', 'registrants'])
            // The row offers a withdrawal only while nothing is recorded, and
            // hasRecordedResults() queries per tournament without a count.
            ->withCount('results')
            ->where('start_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->get()
            ->take(5);

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
     * Where this player sits, by points per event entered.
     *
     * Two grouped queries rather than one join: results and registrations
     * answer different questions -- what you scored, and how often you turned
     * up -- and a player can have either without the other.
     *
     * Queried from the results table rather than through $season->results(),
     * which is a HasManyThrough. That relation silently adds
     * `tournaments`.`season_id` as `laravel_through_key` to the SELECT so it can
     * match rows back to their parent. Harmless normally; fatal beside a GROUP
     * BY, because MySQL's ONLY_FULL_GROUP_BY -- on by default since 8.0 --
     * rejects a selected column that is neither grouped nor aggregated. SQLite
     * does not enforce that rule, so this ran locally for months and failed on
     * the first query against production's driver.
     *
     * @return array{rank: int|null, ranked: int}
     */
    private function rank(PokerSeason $currentSeason, string $userId): array
    {
        $inSeason = fn ($query) => $query->where('season_id', $currentSeason->id);

        $points = PokerTournamentResult::query()
            ->whereHas('tournament', $inSeason)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, SUM(points) as total_points')
            ->groupBy('user_id')
            ->pluck('total_points', 'user_id');

        $entries = PokerTournamentRegistrant::query()
            ->whereHas('tournament', $inSeason)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as entries')
            ->groupBy('user_id')
            ->pluck('entries', 'user_id');

        // Entering is what puts you on the board. A player with a result but no
        // registration -- which the results screen can create -- has no
        // denominator, and dividing by nothing is not a rank. They are absent
        // from $entries entirely, which is what leaves them unranked.
        //
        // The filter below therefore cannot fire: a GROUP BY ... COUNT(*) never
        // returns a zero, so every row here is at least one. It is kept as the
        // guard on the division rather than on the data -- removing it does not
        // fail a test, and that is recorded here rather than left for somebody
        // to rediscover by dividing by zero.
        $board = $entries
            ->filter(fn ($count) => (int) $count > 0)
            ->map(fn ($count, $id) => [
                'user_id' => $id,
                'ratio' => (int) ($points[$id] ?? 0) / (int) $count,
                // The tie-break, so two players on the same average are ordered
                // by who scored more rather than by whatever the database
                // happened to return first.
                'points' => (int) ($points[$id] ?? 0),
            ])
            ->sortByDesc(fn (array $row) => [$row['ratio'], $row['points']])
            ->values();

        $index = $board->search(fn (array $row) => $row['user_id'] === $userId);

        return [
            'rank' => $index === false ? null : $index + 1,
            'ranked' => $board->count(),
        ];
    }
}
