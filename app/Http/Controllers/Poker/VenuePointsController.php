<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class VenuePointsController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        // Handed back by store() so the next entry for the same sitting starts
        // where the last one left off. In the query string rather than the
        // session: it survives a refresh, and a link to "tonight at the
        // Diamond Club" is a useful thing to be able to keep.
        $venueId = $request->query('venue_id');
        $eventDate = $request->query('event_date');

        return Inertia::render('Poker/VenuePoints/Create', [
            'venues' => Venue::orderBy('name')->get()->map(fn (Venue $venue) => [
                'id' => $venue->id,
                'name' => $venue->name,
            ])->all(),

            'venue_id' => $venueId,
            'event_date' => $eventDate,

            // A return trip. The sitting came back in the query string, so the
            // venue and the date are already answered and the only thing left
            // to do is name the next player -- which is where the caret should
            // be, rather than on a venue select that is going to be tabbed
            // straight past a dozen times a night.
            'returning' => filled($venueId) && filled($eventDate),

            // Ordered, because this is a list somebody reads and searches
            // rather than a set of options a machine picks from.
            'players' => User::orderBy('first_name')->orderBy('last_name')->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,

                    // What gets POSTed as user_name, and what the chosen row
                    // shows: "First Last", nothing else. It is stored beside
                    // user_id so a record still says who it was for after an
                    // account is gone.
                    'name' => trim($user->first_name.' '.$user->last_name),

                    // What the row reads, nickname included. Composed here
                    // rather than in the template: where the brackets go is a
                    // wording decision (spec 5a).
                    'label' => trim($user->first_name.' '.$user->last_name)
                        .(filled($user->nickname) ? ' ('.$user->nickname.')' : ''),

                    'email' => $user->email,

                    // initials() is one definition, in PHP. The register
                    // dialog builds its player list in the browser from
                    // letters PHP computed, for the same reason.
                    'initials' => initials(trim($user->first_name.' '.$user->last_name)),

                    // One lowercase haystack per row, built once here rather
                    // than in the filter: the search has to cover name,
                    // nickname and email, and doing that in the expression
                    // would repeat four fields in two places.
                    'search' => mb_strtolower(trim(
                        $user->first_name.' '.$user->last_name.' '.$user->nickname.' '.$user->email
                    )),
                ])->all(),
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
                'date' => Carbon::parse($date)->format('M d, Y'),
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
            'name' => emph($validated['user_name']),
        ]));
    }

}
