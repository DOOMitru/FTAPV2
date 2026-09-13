<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PokerSeason;
use App\Models\PokerTournamentResult;
use App\Models\VenuePoints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PokerSeasonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $seasons = PokerSeason::latest()->paginate(10);
        return view('poker.seasons.index', compact('seasons'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('poker.seasons.create');
    }

    /** The finale thresholds, named once so both writes and the reset below agree. */
    private const THRESHOLDS = [
        'finale_points_required',
        'finale_wins_required',
        'finale_venue_points_required',
    ];

    /**
     * One rule set for both writes.
     *
     * store() and update() carried byte-identical validate blocks, which is
     * how an edit comes to silently drop what create accepts: a rule added to
     * one and forgotten in the other fails only for the person editing.
     */
    private function validated(Request $request): array
    {
        // An empty number input posts '' rather than null, and '' fails an
        // integer rule -- so without this, WITHDRAWING a threshold is
        // impossible. '' means cleared, and cleared is null, not zero: a
        // season whose target is 0 would read as one everybody has met.
        foreach (self::THRESHOLDS as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_current' => 'sometimes|boolean',
            'finale_points_required' => 'nullable|integer|min:0',
            'finale_wins_required' => 'nullable|integer|min:0',
            'finale_venue_points_required' => 'nullable|integer|min:0',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        // If is_current is not provided, the model's booted method will handle it
        $season = PokerSeason::create($validated);

        return redirect()->route('poker.seasons.index')->with('status', __('Season :name created.', [
            'name' => emph($season->name),
        ]));
    }

    /**
     * Display the specified resource.
     */
    /**
     * The standings, ordered one of two ways.
     *
     * 'points' is the season total. 'rank' is points per tournament entered --
     * the figure the player's dashboard calls Season Rank -- which does not
     * punish a player who missed half the nights for missing them.
     */
    public const ORDERS = ['points', 'rank'];

    public function show(PokerSeason $season, Request $request): View
    {
        // Anything unrecognised falls back to the season total rather than
        // erroring: this arrives from a query string, where a stale link or a
        // typo is ordinary and a 500 is not.
        $order = in_array($request->query('order'), self::ORDERS, true)
            ? $request->query('order')
            : 'points';

        $season->load([
            'tournaments.venue',
            'results.user',
        ]);

        $totalTournaments = $season->tournaments->count();
        $totalPoints = $season->results->sum('points');

        // By the season stored on the row, not by whether its date happens to
        // fall between two others. It used to be the latter, which meant
        // editing a season's dates moved venue points between seasons and
        // changed who qualified for the finale -- with nothing to report,
        // because a coincidence of two numbers cannot announce that it has
        // changed its mind.
        //
        // One grouped query rather than a lookup per player.
        $venuePoints = \App\Models\VenuePoints::query()
            ->where('season_id', $season->id)
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(amount) as total')
            ->pluck('total', 'user_id');

        // Who actually entered a tournament this season.
        //
        // The standings are built from RESULTS, and a result can exist without
        // a registration behind it -- the results screen creates one without
        // requiring an entry, where Eliminate refuses. Such a row is a finish
        // in a field nobody joined, and it has no business in a table of how
        // the season is going.
        //
        // whereIn over the season's tournaments, which are already loaded for
        // the count above, rather than a whereHas subquery.
        //
        // flip(), so the filter below is a hash lookup rather than a scan of
        // eighty ids per player.
        $entered = \App\Models\PokerTournamentRegistrant::query()
            ->whereIn('tournament_id', $season->tournaments->pluck('id'))
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->flip();

        // A player's venue points belong to that player and to the admins who
        // award them. Everyone else gets the VERDICT -- qualified or not --
        // computed here from a figure that never leaves this closure, because
        // the thresholds it is measured against are published on this page for
        // everybody to read.
        //
        // Withheld from the DATA, not just from the template. The column was
        // already gated in the view, which stopped it being rendered; this is
        // what stops the next column, debug dump or partial from rendering it
        // by accident. A figure that is not in the array cannot leak from it.
        $readsVenuePoints = fn (?PokerTournamentResult $result) => VenuePoints::readableBy(
            auth()->user(), $result?->user_id
        );

        // Calculate Leaderboard
        $leaderboard = $season->results
            ->groupBy('user_id')
            // A result with no user_id at all groups under '' and is caught by
            // the same rule: nothing that never entered appears here.
            ->filter(fn ($results, $userId) => $entered->has($userId))
            ->map(function ($results) use ($venuePoints, $season, $readsVenuePoints) {
                $points = $results->sum('points');
                $wins = $results->where('place', 1)->count();
                // Defensive, and deliberately untested: SQLite returns an int
                // from SUM() so no assertion here can fail without it. Other
                // drivers return a numeric STRING, and this project has
                // already shipped one bug that existed only on MySQL (the
                // is_active double-quote misfeature in Phase 0). unmetBy() is
                // typed int, so the cast is what keeps that difference from
                // becoming a TypeError on a driver nobody tested.
                $venue = (int) ($venuePoints[$results->first()->user_id] ?? 0);

                // The rule is evaluated HERE, once. A template that
                // re-implements the comparison is a second definition waiting
                // to drift from the model's.
                $unmet = $season->unmetBy(points: $points, wins: $wins, venuePoints: $venue);

                return [
                    'user' => $results->first()->user,
                    'player_name' => $results->first()->player_name,
                    'points' => $points,
                    'wins' => $wins,
                    'top3' => $results->where('place', '<=', 3)->count(),
                    'played' => $results->count(),
                    'venue_points' => $readsVenuePoints($results->first()) ? $venue : null,
                    'unmet' => $unmet,
                    'qualified' => $unmet === [],
                ];
            })
            ->values();

        // Points per entry, taken from PokerSeason::rankings() rather than
        // divided here. The dashboard, the landing page's rank card and this
        // table must agree to the decimal about what a player's rank is, and
        // they agree by reading one method instead of three divisions.
        //
        // Loaded only for the order that needs it: it costs three queries, and
        // the season total is the view most people open.
        $rankings = $order === 'rank'
            ? $season->rankings()->keyBy('user_id')
            : collect();

        $leaderboard = $leaderboard
            ->map(fn (array $row) => [
                ...$row,
                'ratio' => $rankings[$row['user']?->id ?? '']['ratio'] ?? null,
                'events' => $rankings[$row['user']?->id ?? '']['events'] ?? null,
            ])
            ->sortByDesc(fn (array $row) => $order === 'rank'
                // The same tie-break rankings() uses, so two players on one
                // average are ordered by who scored more rather than by
                // whatever the database returned first.
                ? [$row['ratio'] ?? -1, $row['points']]
                : $row['points'])
            ->values();

        // The meter measures each row against the leader in whichever figure
        // is on show. Measuring a ratio against a points total would draw
        // every bar at nothing.
        $leaderValue = $order === 'rank'
            ? (float) ($leaderboard->first()['ratio'] ?? 0)
            : (int) ($leaderboard->first()['points'] ?? 0);

        // Counted from the standings rather than from the results, so the tile
        // and the table cannot disagree about who played: both are now "people
        // who entered a tournament and have a finish". Read the leaderboard,
        // and there is one definition of that instead of two.
        $uniquePlayersCount = $leaderboard->count();

        // Venue stats
        $venueStats = $season->tournaments
            ->groupBy('venue_id')
            ->map(function ($tournaments) {
                return [
                    'name' => $tournaments->first()->venue->name ?? 'TBD',
                    'count' => $tournaments->count(),
                ];
            })
            ->sortByDesc('count')
            ->values();

        return view('poker.seasons.show', compact(
            'season', 
            'totalTournaments', 
            'totalPoints', 
            'uniquePlayersCount', 
            'leaderboard',
            'leaderValue',
            'order',
            'venueStats'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PokerSeason $season): View
    {
        return view('poker.seasons.edit', compact('season'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PokerSeason $season): RedirectResponse
    {
        $validated = $this->validated($request);

        if (!$request->has('is_current')) {
            $validated['is_current'] = false;
        }

        $season->update($validated);

        return redirect()->route('poker.seasons.index')->with('status', __('Season :name updated.', [
            'name' => emph($season->name),
        ]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PokerSeason $season): RedirectResponse
    {
        $name = $season->name;

        $season->delete();

        return redirect()->route('poker.seasons.index')->with('status', __('Season :name deleted.', [
            'name' => emph($name),
        ]));
    }
}
