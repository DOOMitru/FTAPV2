<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $venues = Venue::latest()->paginate(10);
        return view('poker.venues.index', compact('venues'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('poker.venues.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
        ]);

        Venue::create($validated);

        return redirect()->route('poker.venues.index')->with('status', 'Venue created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Venue $venue): View
    {
        $venue->load([
            'tournaments.season',
            'tournaments.results',
            'venuePoints.user',
        ]);

        $totalTournaments = $venue->tournaments->count();
        $totalTournamentPoints = $venue->tournaments->flatMap->results->sum('points');
        $totalVenuePoints = $venue->venuePoints->sum('amount');
        $uniqueVenuePointPlayers = $venue->venuePoints->pluck('user_id')->unique()->count();

        // Calculate Venue Points Leaderboard
        $venueLeaderboard = $venue->venuePoints
            ->groupBy('user_id')
            ->map(function ($points) {
                return [
                    'user' => $points->first()->user,
                    'user_name' => $points->first()->user_name,
                    'total_amount' => $points->sum('amount'),
                    'last_earned' => $points->max('event_date'),
                    'count' => $points->count(),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        return view('poker.venues.show', compact(
            'venue',
            'totalTournaments',
            'totalTournamentPoints',
            'totalVenuePoints',
            'uniqueVenuePointPlayers',
            'venueLeaderboard'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Venue $venue): View
    {
        return view('poker.venues.edit', compact('venue'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Venue $venue): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
        ]);

        $venue->update($validated);

        return redirect()->route('poker.venues.index')->with('status', 'Venue updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Venue $venue): RedirectResponse
    {
        $venue->delete();

        return redirect()->route('poker.venues.index')->with('status', 'Venue deleted successfully!');
    }
}
