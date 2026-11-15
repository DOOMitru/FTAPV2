<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Support\Paginate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function show(Venue $venue): Response
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

        return Inertia::render('Poker/Venues/Show', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'description' => $venue->description,
                'address' => $venue->address,

                // Built here, null when there is no address. The URL encodes
                // the address, and an encoding decision in a template is one
                // an apostrophe eventually breaks.
                'map_src' => $venue->address
                    ? 'https://maps.google.com/maps?q='.urlencode($venue->address).'&t=&z=13&ie=UTF8&iwloc=&output=embed'
                    : null,

                'map_title' => __('Map of :venue', ['venue' => $venue->name]),
                'delete_confirm' => __('Delete :name? This cannot be undone.', [
                    'name' => emph($venue->name),
                ]),
            ],

            // Two figures, not four. Point Earners and Tournament pts both
            // answered questions the panels below answer better: the
            // leaderboard IS the point earners, named and counted.
            'tournaments_label' => (string) $totalTournaments,
            'venue_points_label' => number_format($totalVenuePoints),

            // The whole venue's leaderboard. This page is admin-only, so the
            // venue-points privacy rule is satisfied by the route rather than
            // by withholding rows -- see the admin group in routes/web.php and
            // PlayerLeagueAccessTest, which holds it shut.
            'leaderboard' => $venueLeaderboard->map(fn (array $entry, int $index) => [
                'place' => $index + 1,

                // Nullable: user_id is nullOnDelete, so a departed player
                // leaves a name with no account behind it. PlayerLink renders
                // plain text rather than a link to a 404.
                'player_id' => $entry['user']?->id,
                'player_name' => $entry['user_name'],
                'last_earned_label' => $entry['last_earned']
                    ? __('Last earned').': '.\Illuminate\Support\Carbon::parse($entry['last_earned'])->format('M d, Y')
                    : null,
                'total_label' => number_format($entry['total_amount']),
            ])->values()->all(),

            'tournaments' => $venue->tournaments->sortByDesc('start_time')->take(10)
                ->map(fn (\App\Models\PokerTournament $tournament) => [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'href' => route('tournaments.show', $tournament),
                    'date_label' => $tournament->start_time->format('M d, Y'),
                    'season_label' => $tournament->season->name ?? '',
                    // Upcoming, awaiting results or published: the badge.
                    'status' => $tournament->status(),
                ])->values()->all(),
            'has_more_tournaments' => $totalTournaments > 10,

            // Carrying the venue, which is the whole reason the action lives
            // here: an administrator entering a night's points is standing on
            // the venue they were earned at. venue_id is the same query
            // parameter store() hands back between entries.
            'add_points_href' => route('poker.venue-points.create', ['venue_id' => $venue->id]),
        ]);
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
