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
    public function create(Request $request): View
    {
        return view('poker.venue-points.create', [
            // Ordered, because this is a list somebody reads and searches
            // rather than a set of options a machine picks from.
            'users' => User::orderBy('first_name')->orderBy('last_name')->get(),
            'venues' => Venue::orderBy('name')->get(),

            // Handed back by store() so the next entry for the same sitting
            // starts where the last one left off. In the query string rather
            // than the session: it survives a refresh, and a link to "tonight
            // at the Diamond Club" is a useful thing to be able to keep.
            'venueId' => $request->query('venue_id'),
            'eventDate' => $request->query('event_date'),
        ]);
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

        // Back to the form, not to the listing. A night at a venue is a dozen
        // players entered one after another, and a round trip through the index
        // between each one is a page load and two clicks per player. The venue
        // and the date come back with it; only the player and the amount change.
        return redirect()->route('poker.venue-points.create', [
            'venue_id' => $validated['venue_id'],
            'event_date' => $validated['event_date'],
        ])->with('status', __(':amount venue points recorded for :name.', [
            'amount' => number_format($validated['amount']),
            'name' => $validated['user_name'],
        ]));
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
