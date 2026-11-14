<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Support\Paginate;
use App\Models\PokerTournamentResult;
use App\Models\VenuePoints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PokerSeasonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('Poker/Seasons/Index', [
            'seasons' => Paginate::props(
                PokerSeason::latest()->paginate(10),
                fn (PokerSeason $season) => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'is_current' => (bool) $season->is_current,
                    // Formatted here, per spec §5a. The em dash is the empty
                    // state for a date, not a null for Vue to decide about.
                    'start_label' => $season->start_date?->format('M d, Y') ?? '—',
                    'end_label' => $season->end_date?->format('M d, Y') ?? '—',
                ]
            ),

            // A server decision, not auth.user.is_admin read in the template:
            // what a reader may do is decided where the gate is.
            'can_create' => (bool) auth()->user()->is_admin,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Poker/Seasons/Create');
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
     * The season, with its standings in rank order.
     *
     * One order since 2026-11-14. There was a Points/Rank toggle, and once the
     * owner made rank mean season points the two were the same order. A stale
     * ?order= in a bookmark is simply ignored.
     */
    public function show(PokerSeason $season): Response
    {
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

        // Order and place both come from PokerSeason::rankings(). The
        // dashboard, the landing page's card and this table must agree about
        // what a player's rank is, and they agree by reading one board -- not
        // by three sorts that would have to break a tie the same way.
        //
        // The board holds only players on at least one point, so a row it
        // does not know is a player on nil, and is not listed (owner's
        // decision, 2026-11-14). It also places rows by venue points without
        // carrying the figure, so the order can reflect a tally this page may
        // not print -- the one exception the owner made to that rule.
        $board = $season->rankings()->values();
        $position = $board->pluck('user_id')->flip();
        $places = $board->pluck('place', 'user_id');

        $leaderboard = $leaderboard
            ->filter(fn (array $row) => $position->has($row['user']?->id ?? ''))
            ->sortBy(fn (array $row) => $position[$row['user']->id])
            ->map(fn (array $row) => [...$row, 'place' => $places[$row['user']->id]])
            ->values();

        $leaderValue = (int) ($leaderboard->first()['points'] ?? 0);

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

        $isAdmin = (bool) auth()->user()->is_admin;
        $viewerId = auth()->id();
        $venueMax = (int) ($venueStats->max('count') ?? 0);

        return Inertia::render('Poker/Seasons/Show', [
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'delete_confirm' => __('Delete :name? This cannot be undone.', [
                    'name' => emph($season->name),
                ]),
            ],

            'can_manage' => $isAdmin,

            'tournaments_label' => number_format($totalTournaments),
            'points_label' => number_format($totalPoints),
            'players_label' => number_format($uniquePlayersCount),

            // Null when nothing is decided, so the panel can say so. Each
            // figure is guarded on its own inside it: hasThresholds() is true
            // when ANY one is set, and number_format(null) renders 0 -- a
            // target nobody chose and everybody has already met.
            'finale' => $season->hasThresholds()
                ? [
                    'wins_label' => $season->finale_wins_required !== null
                        ? (string) $season->finale_wins_required
                        : __('Not set'),
                    'points_label' => $season->finale_points_required !== null
                        ? number_format($season->finale_points_required)
                        : __('Not set'),
                    'venue_points_label' => $season->finale_venue_points_required !== null
                        ? number_format($season->finale_venue_points_required)
                        : __('Not set'),
                ]
                : null,

            // Whether a COLUMN is drawn. What may be read is decided above, in
            // $readsVenuePoints, and a figure that is not in the array cannot
            // leak from it -- this flag only stops an empty column being drawn
            // for a reader who would see nothing in it.
            'shows_venue_points' => $isAdmin,

            'standings' => $leaderboard->map(function (array $row) use ($leaderValue, $season, $viewerId) {

                // Standings name a player by nickname when they have one,
                // otherwise by their full name -- deliberately NOT
                // display_name, which falls back to the first name alone and
                // would drop surnames here. player_name is the full name
                // snapshotted onto the result, and is the only name available
                // when a result has no linked account.
                $nickname = $row['user']?->nickname;
                $shownName = filled($nickname) ? $nickname : $row['player_name'];

                return [
                    // Registrations and results are both nullable on user_id,
                    // so a row can have no account at all. The name is the
                    // fallback key, and a v-for with a duplicate key reorders
                    // rows silently.
                    'key' => $row['user']?->id ?? 'name:'.$row['player_name'],

                    // The viewer's own row. A result can carry no linked
                    // account, so both sides must have one before the ids are
                    // compared -- without that guard every accountless result
                    // would be marked "you", for everyone.
                    'is_self' => $row['user'] !== null && $row['user']->id === $viewerId,

                    // The PLACE, shared on a tie: two rows can both read 3.
                    'rank' => $row['place'],

                    'shown_name' => $shownName,
                    'full_name' => filled($nickname) ? $row['player_name'] : null,
                    'player_id' => $row['user']?->id,
                    'initials' => initials($row['player_name']),

                    'meter_value' => (int) $row['points'],
                    'meter_max' => $leaderValue,

                    // The meter's accessible name, read aloud rather than
                    // seen: "PTS for Wanda Reeve" is worse to hear than
                    // "Points for Wanda Reeve", which is why this is the whole
                    // word.
                    'meter_label' => __('Points for :name', ['name' => $shownName]),

                    'meter_value_label' => number_format($row['points']),

                    'played_label' => (string) $row['played'],
                    'wins_label' => (string) $row['wins'],
                    'venue_points_label' => $row['venue_points'] === null
                        ? null
                        : (string) $row['venue_points'],

                    // Three states, not two. An empty cell reads as "blank" to
                    // a screen reader, which does not say whether the row
                    // failed or the column is not in use.
                    'finale_state' => match (true) {
                        ! $season->hasThresholds() => 'none',
                        $row['qualified'] => 'qualified',
                        default => 'not-qualified',
                    },
                ];
            })->values()->all(),

            'venues' => $venueStats->map(fn (array $venue) => [
                'name' => $venue['name'],
                'count' => $venue['count'],
                'count_label' => number_format($venue['count']),
                'max' => $venueMax,
                'label' => __('Tournaments at :venue', ['venue' => $venue['name']]),
            ])->values()->all(),

            'tournaments' => $season->tournaments->map(fn (PokerTournament $tournament) => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'date_label' => $tournament->start_time?->format('d M Y'),
                'href' => route('tournaments.show', $tournament),
            ])->values()->all(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PokerSeason $season): Response
    {
        return Inertia::render('Poker/Seasons/Edit', [
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'description' => $season->description,

                // Y-m-d, not the cast Carbon. An <input type="date"> renders
                // anything else BLANK, so editing a season silently offered to
                // wipe its dates -- the fault the Blade view's own comment
                // records, and which arrives again through any prop that
                // hands the date over as it is stored.
                'start_date' => $season->start_date?->format('Y-m-d'),
                'end_date' => $season->end_date?->format('Y-m-d'),

                // A real boolean: v-model on a checkbox given 0 or 1 renders
                // unchecked and posts the wrong value back.
                'is_current' => (bool) $season->is_current,

                // Null means NOT PUBLISHED, which is not zero -- a target of 0
                // is one everybody has met. Sent as null so the number input
                // is empty.
                'finale_points_required' => $season->finale_points_required,
                'finale_wins_required' => $season->finale_wins_required,
                'finale_venue_points_required' => $season->finale_venue_points_required,
            ],
        ]);
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
