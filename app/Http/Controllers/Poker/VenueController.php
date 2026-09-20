<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Support\Paginate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $isAdmin = (bool) auth()->user()->is_admin;

        return Inertia::render('Poker/Venues/Index', [
            'venues' => Paginate::props(
                Venue::latest()->paginate(10),
                fn (Venue $venue) => [
                    'id' => $venue->id,
                    'name' => $venue->name,
                    'description' => $venue->description,
                ]
            ),

            /*
             * Whether a row is a link at all.
             *
             * poker.venues.show is admin-only -- it carries takings,
             * leaderboards and per-player point histories -- so a row a player
             * could follow is a row that leads to a 403. The Blade decided
             * this with auth()->user()->is_admin inline; as a prop it is
             * decided where the gate is, and the template has no opinion about
             * who may read what.
             */
            'rows_link' => $isAdmin,
            'can_create' => $isAdmin,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Poker/Venues/Create');
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

        $venue = Venue::create($validated);

        return redirect()->route('poker.venues.index')->with('status', __('Venue :name created.', [
            'name' => emph($venue->name),
        ]));
    }

    /**
     * Display the specified resource.
     */
    public function show(Venue $venue): View
    {
        // Not tournaments.results: it was loaded for a Tournament pts tile that
        // no longer exists, and nothing else on the page reads a result. On a
        // venue that has hosted a season it was every result of every night,
        // fetched to be summed once and thrown away.
        $venue->load([
            'tournaments.season',
            'venuePoints.user',
        ]);

        $totalTournaments = $venue->tournaments->count();
        $totalVenuePoints = $venue->venuePoints->sum('amount');

        // Calculate Venue Points Leaderboard
        $venueLeaderboard = $venue->venuePoints
            ->groupBy('user_id')
            ->map(function ($points) {
                return [
                    'user' => $points->first()->user,
                    'user_name' => $points->first()->user_name,
                    'total_amount' => $points->sum('amount'),
                    'last_earned' => $points->max('event_date'),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        return view('poker.venues.show', compact(
            'venue',
            'totalTournaments',
            'totalVenuePoints',
            'venueLeaderboard'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Venue $venue): Response
    {
        // The record's values, once. No old(): useForm holds what was typed
        // and a failed submission returns errors without re-rendering, so
        // there is nothing to restore -- and re-seeding from the session would
        // overwrite what is on screen.
        return Inertia::render('Poker/Venues/Edit', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'description' => $venue->description,
                'address' => $venue->address,
            ],
        ]);
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

        return redirect()->route('poker.venues.index')->with('status', __('Venue :name updated.', [
            'name' => emph($venue->name),
        ]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Venue $venue): RedirectResponse
    {
        // Read before the row goes, so the message does not depend on what
        // an already-deleted model still happens to hold in memory.
        $name = $venue->name;

        $venue->delete();

        return redirect()->route('poker.venues.index')->with('status', __('Venue :name deleted.', [
            'name' => emph($name),
        ]));
    }
}
