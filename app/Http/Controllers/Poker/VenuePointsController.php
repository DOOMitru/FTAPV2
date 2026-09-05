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
     * Stamp the season the date falls in, or say why it cannot.
     *
     * Venue points only mean anything as part of a season -- the finale
     * threshold is a season's -- so a date outside every season is a data entry
     * mistake worth catching at the form rather than a row that quietly counts
     * toward nothing.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|null  null when no season covers the date
     */
    private function withSeason(array $validated): ?array
    {
        $season = \App\Models\PokerSeason::covering($validated['event_date']);

        if (! $season) {
            return null;
        }

        $validated['season_id'] = $season->id;

        return $validated;
    }

    /** The message a date outside every season gets. */
    private function noSeason(string $date): \Illuminate\Http\RedirectResponse
    {
        return back()->withInput()->withErrors([
            'event_date' => __('No season covers :date, so these points would count toward nothing. Check the date, or set the season\'s dates to include it.', [
                'date' => \Illuminate\Support\Carbon::parse($date)->format('M d, Y'),
            ]),
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

        if (! $stamped = $this->withSeason($validated)) {
            return $this->noSeason($validated['event_date']);
        }

        VenuePoints::create($stamped);

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

        // Re-stamped, because the date may have been what changed.
        if (! $stamped = $this->withSeason($validated)) {
            return $this->noSeason($validated['event_date']);
        }

        $venue_point->update($stamped);

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
