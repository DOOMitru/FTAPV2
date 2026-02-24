<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenuePointsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $venue_points = VenuePoints::with(['user', 'venue'])->latest()->paginate(10);
        return view('poker.venue-points.index', compact('venue_points'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $users = User::all();
        $venues = Venue::all();
        return view('poker.venue-points.create', compact('users', 'venues'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date' => 'required|date',
            'amount' => 'required|integer',
            'user_id' => 'required|exists:users,id',
            'user_name' => 'required|string|max:255',
            'venue_id' => 'required|exists:venues,id',
        ]);

        VenuePoints::create($validated);

        return redirect()->route('poker.venue-points.index')->with('status', 'Venue points added successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VenuePoints $venue_point): View
    {
        $users = User::all();
        $venues = Venue::all();
        return view('poker.venue-points.edit', compact('venue_point', 'users', 'venues'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VenuePoints $venue_point): RedirectResponse
    {
        $validated = $request->validate([
            'event_date' => 'required|date',
            'amount' => 'required|integer',
            'user_id' => 'required|exists:users,id',
            'user_name' => 'required|string|max:255',
            'venue_id' => 'required|exists:venues,id',
        ]);

        $venue_point->update($validated);

        return redirect()->route('poker.venue-points.index')->with('status', 'Venue points updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VenuePoints $venue_point): RedirectResponse
    {
        $venue_point->delete();

        return redirect()->route('poker.venue-points.index')->with('status', 'Venue points deleted successfully!');
    }
}
