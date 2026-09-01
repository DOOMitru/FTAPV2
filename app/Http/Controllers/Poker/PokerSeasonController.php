<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PokerSeason;
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
        PokerSeason::create($validated);

        return redirect()->route('poker.seasons.index')->with('status', 'Season created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(PokerSeason $season): View
    {
        $season->load([
            'tournaments.venue',
            'results.user',
        ]);

        $totalTournaments = $season->tournaments->count();
        $totalPoints = $season->results->sum('points');
        $uniquePlayersCount = $season->results->pluck('user_id')->unique()->count();

        // venue_points carries no season_id, only an event_date, so the
        // season's own dates are the only attribution available.
        //
        // A consequence worth knowing rather than discovering: EDITING A
        // SEASON'S DATES MOVES THIS FIGURE, and with it who qualifies. That is
        // inherent to the schema, not a defect in this query.
        //
        // One grouped query rather than a lookup per player.
        $venuePoints = \App\Models\VenuePoints::query()
            ->whereBetween('event_date', [$season->start_date, $season->end_date])
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(amount) as total')
            ->pluck('total', 'user_id');

        // Calculate Leaderboard
        $leaderboard = $season->results
            ->groupBy('user_id')
            ->map(function ($results) use ($venuePoints, $season) {
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
                    'venue_points' => $venue,
                    'unmet' => $unmet,
                    'qualified' => $unmet === [],
                ];
            })
            ->sortByDesc('points')
            ->values();

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

        return redirect()->route('poker.seasons.index')->with('status', 'Season updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PokerSeason $season): RedirectResponse
    {
        $season->delete();

        return redirect()->route('poker.seasons.index')->with('status', 'Season deleted successfully!');
    }
}
