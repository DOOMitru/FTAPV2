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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_current' => 'sometimes|boolean',
        ]);

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

        // Calculate Leaderboard
        $leaderboard = $season->results
            ->groupBy('user_id')
            ->map(function ($results) {
                return [
                    'user' => $results->first()->user,
                    'player_name' => $results->first()->player_name,
                    'points' => $results->sum('points'),
                    'wins' => $results->where('place', 1)->count(),
                    'top3' => $results->where('place', '<=', 3)->count(),
                    'played' => $results->count(),
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_current' => 'sometimes|boolean',
        ]);

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
