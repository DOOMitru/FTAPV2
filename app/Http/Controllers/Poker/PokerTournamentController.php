<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\Venue;
use App\Support\Paginate;
use App\Notifications\TournamentPlacement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PokerTournamentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Inertia\Response
    {
        // By START TIME, not latest(). latest() orders by created_at -- the
        // order the rows were typed in -- which for a league that schedules a
        // season ahead in one sitting is close to arbitrary: a night added
        // last week sat above the one being played tonight.
        //
        // Descending, so the nights nearest now lead and the archive falls
        // away below. That is the same reading the list had before, now
        // against the date that means something.
        // The current season only. This list is worked, not browsed: it is
        // where a night is scheduled and where an administrator goes to open
        // the one being played. Seasons past are read from their own page,
        // which lists the tournaments held in them.
        //
        // tournaments.season_id is NOT NULL, so there is no orphan row for this
        // filter to miss. The case that does need care is the one below: no
        // CURRENT season, where an unguarded filter becomes no filter at all.
        $currentSeason = PokerSeason::current();

        $tournaments = PokerTournament::with('venue')
            ->when($currentSeason, fn ($query) => $query->whereBelongsTo($currentSeason, 'season'))
            // No season at all is no list. Returning everything would be the
            // opposite of what this page now claims to show, and the empty
            // state below says which of the two nothings this is.
            ->unless($currentSeason, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('start_time')
            ->paginate(100);

        return \Inertia\Inertia::render('Poker/Tournaments/Index', [
            'tournaments' => Paginate::props(
                $tournaments,
                fn (PokerTournament $tournament) => [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    // TBD is the venue's empty state, decided here rather than
                    // left as a null the template has an opinion about.
                    'venue_name' => $tournament->venue->name ?? __('TBD'),
                    'start_label' => $tournament->start_time?->format('M d, Y · h:i A') ?? '—',
                ]
            ),

            /*
             * Null when no season is running, and the page says something
             * different in each case.
             *
             * An administrator can act on an unset season and cannot act on an
             * empty one, so "No tournaments in Season 9 yet." and "No season is
             * running." must not read alike. The name is what distinguishes
             * them, so it is sent rather than inferred from an empty list.
             */
            'season_name' => $currentSeason?->name,
            'can_create' => (bool) auth()->user()->is_admin,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        // No $seasons here. The Blade view was handed a list it never read --
        // the season is not chosen on this page, it is assigned by store().
        return Inertia::render('Poker/Tournaments/Create', [
            'venues' => Venue::all()->map(fn (Venue $venue) => [
                'id' => $venue->id,
                'name' => $venue->name,
            ])->all(),

            // The label, not the season: it is read-only here and the fallback
            // wording is a translated string, which belongs on this side of
            // the wire rather than in a template (spec 5a).
            'season_label' => PokerSeason::current()?->name ?? __('No active season'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'venue_id' => 'required|exists:venues,id',
        ]);

        $currentSeason = PokerSeason::current();

        if (!$currentSeason) {
            return back()->with('error', 'No current active season found. Please create or set an active season first.')->withInput();
        }

        $validated['season_id'] = $currentSeason->id;

        $tournament = PokerTournament::create($validated);

        return redirect()->route('poker.tournaments.index')->with('status', __(':tournament created.', [
            'tournament' => emph($tournament->name),
        ]));
    }

    /**
     * The name a row sorts under.
     *
     * The account's surname when there is one. user_id is nullable with
     * nullOnDelete on both registrants and results, so a deleted player leaves
     * only the player_name snapshotted at the time -- and the last word of that
     * is the best surname available. A single-word name sorts under itself.
     *
     * Takes the user and the name rather than a registrant, because the list it
     * sorts now also holds finishes with no registration behind them.
     */
    private function surnameOf(?\App\Models\User $user, ?string $name): string
    {
        if (filled($user?->last_name)) {
            return $user->last_name;
        }

        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $words === [] ? '' : end($words);
    }

    /**
     * Display the specified resource.
     */
    public function show(PokerTournament $tournament): Response
    {
        $tournament->load([
            'venue',
            'season',
            'registrants.user',
            'results.user',
        ]);

        $registrantsCount = $tournament->registrants->count();
        $resultsCount = $tournament->results->count();
        
        $orderedResults = $tournament->results->sortBy('place')->values();
        $isUserRegistered = $tournament->registrants()->where('user_id', auth()->id())->exists();

        // The shared event card reads viewer_registered -- the attribute the
        // events and home pages load with withExists. Set it from the value
        // already computed here so the card and the page around it cannot
        // disagree about whether you are in this tournament.
        $tournament->viewer_registered = $isUserRegistered;
        // "Past" means play has begun -- and the model already answers that.
        // This used to parse start_time and compare it here: a second
        // definition of the rule, over an attribute the model already casts to
        // a Carbon instance, so the parse was re-reading its own output.
        $isPast = $tournament->hasStarted();

        // Read for $nextPlacePoints alone. The Points at Stake panel used to
        // print the whole table beside the tournament; the points on offer now
        // appear where they are acted on, in the Eliminate confirmation.
        $pointsStructure = PointsStructure::orderBy('place')->get();

        // Places are handed out from the bottom of the field: the first player
        // out of ten finishes tenth, and the last one standing takes first. So
        // the place on offer is however many registrants are still without a
        // result -- the same number for whoever goes out next, which is why it
        // is computed once here rather than per row.
        $nextPlace = $registrantsCount - $resultsCount;
        $nextPlacePoints = $pointsStructure->firstWhere('place', $nextPlace)?->points ?? 0;

        // One row per player, whether they are still in, already out, or hold a
        // result with no registration behind them at all.
        //
        // Matched on user_id, and only where BOTH sides have one: results and
        // registrants are both nullable there (nullOnDelete), and keying a
        // collection by null collapses every such row onto one key, so a
        // deleted player's registration would otherwise adopt a stranger's
        // finish.
        $resultsByUser = $tournament->results->whereNotNull('user_id')->keyBy('user_id');

        $rows = $tournament->registrants->map(fn ($registrant) => [
            'registrant' => $registrant,
            'result' => $resultsByUser[$registrant->user_id] ?? null,
            'user' => $registrant->user,
            'name' => $registrant->player_name,
            'nickname' => $registrant->player_nickname,
        ]);

        // A finish with nobody registered behind it. The results screen creates
        // results without requiring a registration, so these exist and used to
        // appear in Final Standings -- which the registrants list never showed.
        // Merging the two on registrants alone would have deleted them from the
        // page.
        $matched = $rows->pluck('result')->filter()->pluck('id')->all();

        $rows = $rows->concat(
            $tournament->results
                ->reject(fn ($result) => in_array($result->id, $matched, true))
                ->map(fn ($result) => [
                    'registrant' => null,
                    'result' => $result,
                    'user' => $result->user,
                    'name' => $result->player_name,
                    'nickname' => $result->player_nickname,
                ])
        );

        // The panel reads as live standings rather than as an address book.
        //
        // Players still in sit at the top, because they are competing for the
        // places above the ones already awarded -- with three of ten out
        // holding 8th, 9th and 10th, the seven still playing will finish
        // somewhere in 1st to 7th. Below them the finishers appear best first,
        // matching the Final Standings table further up the same page.
        //
        // Places count DOWN as players go out, so the first player eliminated
        // holds the highest number and appears last. That is a standings order,
        // not an elimination log.
        // What the one panel is called depends on what is in it. "Final" is a
        // claim -- it is only true once every entered player has a finish, so
        // it waits for isComplete() rather than for the clock. Before any
        // result at all the list is not standings of anything.
        // Asked once and carried, because isComplete() is two queries and this
        // page asked it four times -- here and three times in the view.
        //
        // Passed as a value rather than memoised on the model, deliberately.
        // The method queries through the relation METHODS on purpose so that a
        // caller in a WRITE request sees the result it just recorded (see the
        // note in eliminate()); a cache on the model would take that away
        // silently. A read request has nothing to go stale against.
        $isComplete = $tournament->isComplete();

        $standingsTitle = match (true) {
            $isComplete => __('Final Standings'),
            $resultsCount > 0 => __('Standings'),
            default => __('Registered Players'),
        };

        $standings = $rows->sortBy(fn ($row) => [
            $row['result'] ? 1 : 0,
            $row['result']->place ?? 0,
            Str::lower($this->surnameOf($row['user'], $row['name'])),
        ])->values();

        // Everyone, not everyone available.
        //
        // This used to exclude anybody already in this tournament, on the
        // reasoning that register() refuses them and offering a button that
        // fails is worse than offering nothing. The dialog shows them instead,
        // named and unselectable, because absence is ambiguous: an
        // administrator looking for a player who is not in the list cannot tell
        // whether they are already entered or simply not approved, and the
        // first is the common case. The server rule is unchanged -- register()
        // still refuses -- so this only makes the refusal visible in advance.
        //
        // Players awaiting approval are in the list for the same reason and
        // were left out for the same wrong one. An administrator hunting
        // somebody who joined last week and cannot find them learns nothing
        // from an empty list; "waiting for approval" tells them the next thing
        // to do, and it is a thing they can do -- they are the one who approves
        // accounts.
        //
        // Ordered by how many tournaments a player has entered, most first: a
        // league's regulars are who an administrator is nearly always looking
        // for, and the list is capped at ten. Name breaks the ties.
        $registerCandidates = collect();

        if (auth()->user()->is_admin) {
            $entered = $tournament->registrants->pluck('user_id')->filter()->all();

            $registerCandidates = \App\Models\User::query()
                ->withCount('tournamentRegistrations')
                ->orderByDesc('tournament_registrations_count')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => trim($user->first_name.' '.$user->last_name),
                    'label' => trim($user->first_name.' '.$user->last_name)
                        .(filled($user->nickname) ? ' ('.$user->nickname.')' : ''),
                    // From the full name, not the label: the label carries a
                    // parenthesised nickname, and "W(" is not a monogram.
                    'initials' => initials(trim($user->first_name.' '.$user->last_name)),
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'played' => $user->tournament_registrations_count,

                    // Pluralised in PHP, like everything else the page places.
                    'played_label' => trans_choice(
                        '{1}1 tournament|[2,*]:count tournaments',
                        $user->tournament_registrations_count
                    ),
                    'registered' => in_array($user->id, $entered, true),
                    'approved' => $user->isApproved(),
                    // One lowercase haystack per row, built here rather than in
                    // the filter: the search covers name, nickname and email,
                    // and doing that in the expression would repeat four fields.
                    'search' => mb_strtolower(trim(
                        $user->first_name.' '.$user->last_name.' '.$user->nickname.' '.$user->email
                    )),
                ])
                ->values();
        }

        $user = auth()->user();
        $isAdmin = (bool) $user->is_admin;

        return Inertia::render('Poker/Tournaments/Show', [
            // The card's own props, in the shape PEvent.vue declares. The
            // details page turns the map off and the Details button with it:
            // this IS the details page.
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'description' => $tournament->description,
                'season_label' => $tournament->season->name,
                'month_label' => $tournament->start_time->format('M'),
                'day_label' => $tournament->start_time->format('j'),
                'weekday_label' => $tournament->start_time->format('D'),
                'date_label' => $tournament->start_time->format('D, M j'),
                'time_label' => $tournament->start_time->format('g:i A'),
                'full_date_label' => $tournament->start_time->format('l j F Y, g:i A'),
                'venue_label' => $tournament->venue->name ?? __('Location TBD'),

                // No map on this page: it is reached from inside the dashboard
                // by people who already know where the league plays, and a
                // third of the card spent on one pushes the panels this page
                // exists for below the fold.
                'map_src' => null,
                'map_title' => __('Map of :venue', ['venue' => $tournament->venue->name ?? '']),
                'address' => null,

                'href' => route('tournaments.show', $tournament),
                'season_href' => route('seasons.show', $tournament->season),

                // poker.venues.show is inside the admin-only prefix; offering
                // it to a player is offering a 403.
                'venue_href' => $isAdmin && $tournament->venue
                    ? route('poker.venues.show', $tournament->venue)
                    : null,

                'is_registered' => $isUserRegistered,

                // The controller's own rules, restated once. A player's window
                // closes when the cards go in the air; an administrator
                // registers from the room until the results are published.
                'can_register' => ! $isUserRegistered
                    && $user->isApproved()
                    && ($isAdmin || ! $tournament->hasStarted()),

                // Asymmetric with the above, deliberately: joining a field of
                // ten makes it a field of eleven, while leaving a settled one
                // makes every recorded place describe a tournament that never
                // happened.
                'can_unregister' => $isUserRegistered && ! $tournament->hasRecordedResults(),
                'awaiting_approval' => ! $isUserRegistered && ! $user->isApproved(),
                'unregister_confirm' => __('Unregister from :tournament? You can enter again any time before results are recorded.', [
                    'tournament' => emph($tournament->name),
                ]),

                'is_published' => $tournament->isPublished(),
                'publish_confirm' => __('Publish results for :tournament? Every player who scored points will be notified, and the tournament will be locked.', [
                    'tournament' => emph($tournament->name),
                ]),
                'unpublish_confirm' => __('Unpublish :tournament? The notifications sent to players will be withdrawn.', [
                    'tournament' => emph($tournament->name),
                ]),
                'delete_confirm' => __('Delete :name? Its registrations and every recorded finish go with it. This cannot be undone.', [
                    'name' => emph($tournament->name),
                ]),
            ],

            'can_manage' => $isAdmin,

            // Offered only when it can act, so it is never a click that fails
            // -- the controller refuses an incomplete field too.
            'can_publish' => ! $tournament->isPublished() && $isComplete,

            'standings_title' => $standingsTitle,
            'registrants_label' => number_format($registrantsCount),

            'standings' => $standings->map(function (array $row) use ($tournament, $isAdmin, $nextPlace, $nextPlacePoints) {
                $result = $row['result'];

                return [
                    // A row comes from a registrant OR from a result with no
                    // registration behind it -- the results screen makes those
                    // -- so neither id is on every row. A v-for with a
                    // duplicate or missing key reorders rows silently.
                    'key' => $row['registrant'] ? 'r'.$row['registrant']->id : 'x'.$result->id,

                    'name' => $row['name'],
                    'nickname' => $row['nickname'],
                    'player_id' => $row['user']?->id,
                    'initials' => initials($row['name']),

                    'place' => $result?->place,
                    'points_label' => $result ? number_format($result->points).' '.__('pts') : null,

                    // No timing gate. This once required registration closed,
                    // on the reasoning that a late entry would change how many
                    // places there are -- but the shift hook handles exactly
                    // that.
                    'can_eliminate' => $isAdmin && ! $result,
                    'user_id' => $row['registrant']?->user_id,

                    // The confirmation names the place and the points because
                    // neither is chosen here -- they fall out of how many
                    // players are still in, and an administrator should see
                    // what they are about to award before they award it.
                    'eliminate_confirm' => __('Eliminate :name? They finish in :place place and are awarded :points points. This records a tournament result.', [
                        'name' => emph($row['name']),
                        'place' => Number::ordinal($nextPlace),
                        'points' => number_format($nextPlacePoints),
                    ]),

                    // ! $result is load-bearing rather than decoration:
                    // without it an eliminated player gets their points badge
                    // AND a remove button the controller then refuses. The
                    // registrant check stays because the route needs one, and
                    // a row built from a result alone has none.
                    'can_remove' => $isAdmin && ! $result && ! $tournament->isPublished() && $row['registrant'],
                    'registrant_id' => $row['registrant']?->id,
                    'remove_confirm' => __('Remove :name from :tournament? Any finishes already recorded move up a place and are repriced. An administrator can enter them again until the results are published.', [
                        'name' => emph($row['name']),
                        'tournament' => emph($tournament->name),
                    ]),
                ];
            })->values()->all(),

            // Asked the moment the field is settled, and flashed: it appears
            // once, right after the last elimination, and never again on a
            // reload.
            'offer_publish' => (bool) session('offer_publish'),
            'offer_publish_message' => __('That is the whole field for :tournament. Publish the results now?', [
                'tournament' => $tournament->name,
            ]),

            // Publishing is the only thing that locks the field now, so that
            // is what the note says. It read "Results recorded, entries
            // locked", which was true when the first elimination closed
            // removal for the whole field and is false now.
            'field_locked_note' => $isAdmin && $tournament->isPublished() && $registrantsCount > 0
                ? __('Results published · field locked')
                : null,

            // Everyone, not everyone available. The dialog shows a player who
            // cannot be picked, named and inert, because absence is ambiguous:
            // an administrator looking for somebody who is not in the list
            // cannot tell whether they are already entered or simply not
            // approved, and the first is the common case.
            'register_candidates' => $registerCandidates->all(),

            // Said inside the dialog, where the administrator is looking: the
            // page-level alert is behind the backdrop while it is open.
            'registered_name' => session('registered_name'),
        ]);
    }

    /**
     * Register a user for the tournament (Self or Admin override).
     */
    /**
     * Knock a registered player out, which is to say record their result.
     *
     * Admin-only by its route. The place is not chosen: it falls out of how
     * many players are still in, so an administrator cannot award a place out
     * of order by clicking the wrong row.
     */
    /**
     * Declare the results final: tell the players who scored, and lock it.
     *
     * The two happen together, in one transaction. Sending without locking
     * leaves every message open to a late registration shifting the places
     * underneath it; locking without sending closes a tournament nobody was
     * told about.
     */
    public function publish(PokerTournament $tournament): RedirectResponse
    {
        if ($tournament->isPublished()) {
            return back()->with('error', __('Results for :tournament have already been published.', [
                'tournament' => emph($tournament->name),
            ]));
        }

        if (! $tournament->isComplete()) {
            return back()->with('error', __(
                'Every registered player needs a finish before results can be published.'
            ));
        }

        // points > 0 is the whole recipient rule. A place the structure does
        // not pay scores nothing, and there is nothing to celebrate about
        // nothing.
        $scored = $tournament->results()
            ->where('points', '>', 0)
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        DB::transaction(function () use ($tournament, $scored) {
            foreach ($scored as $result) {
                $result->user?->notify(new TournamentPlacement($result));
            }

            $tournament->forceFill(['published_at' => now()])->save();
        });

        return back()->with('status', trans_choice(
            '{0}Results published. Nobody scored points, so no one was notified.'
            .'|{1}Results published. 1 player was notified.'
            .'|[2,*]Results published. :count players were notified.',
            $scored->count(),
            ['count' => $scored->count()]
        ));
    }

    /**
     * Reopen a tournament, and retract what publishing claimed.
     *
     * The notifications go with it. Unpublishing means the results were not
     * final, so a message still saying "you finished 1st" is a statement the
     * league no longer stands behind -- and deleting them means republishing
     * sends one clean set rather than a duplicate for everybody whose placing
     * did not change.
     */
    public function unpublish(PokerTournament $tournament): RedirectResponse
    {
        if (! $tournament->isPublished()) {
            return back()->with('error', __('Results for :tournament have not been published.', [
                'tournament' => emph($tournament->name),
            ]));
        }

        DB::transaction(function () use ($tournament) {
            // Scoped by type as well as by tournament: a player's unrelated
            // notifications are not this action's to delete.
            DatabaseNotification::where('type', TournamentPlacement::class)
                ->where('data->tournament_id', $tournament->id)
                ->delete();

            $tournament->forceFill(['published_at' => null])->save();
        });

        return back()->with('status', __('Results for :tournament are open again, and the notifications have been withdrawn.', [
            'tournament' => emph($tournament->name),
        ]));
    }

    public function eliminate(PokerTournament $tournament, Request $request): RedirectResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'string']]);

        // A published tournament is finished. Its players have been told where
        // they came, and a place is a position in a field -- so nothing may
        // change the field's size or any finish in it.
        if ($refusal = $tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }

        // No gate on timing. This used to require registration closed, on the
        // reasoning that a late entry would change how many places there are to
        // hand out -- but the shift hook already handles exactly that, moving
        // every recorded finish down when someone registers after the fact. The
        // arithmetic below is a live count either way.
        $registrant = $tournament->registrants()->where('user_id', $validated['user_id'])->first();

        if (! $registrant) {
            return back()->with('error', __('That player is not registered for this tournament.'));
        }

        $place = $tournament->registrants()->count() - $tournament->results()->count();

        if ($place < 1) {
            return back()->with('error', __('Every registered player already has a result for this tournament.'));
        }

        // No row for a place is not an error: a structure that pays the top ten
        // of a field of twenty means eleventh onwards score nothing.
        $points = PointsStructure::where('place', $place)->value('points') ?? 0;

        try {
            $tournament->results()->create([
                'user_id' => $registrant->user_id,
                'player_name' => $registrant->player_name,
                'player_nickname' => $registrant->player_nickname,
                'place' => $place,
                'points' => $points,
            ]);
        } catch (UniqueConstraintViolationException) {
            // tr_tournament_user_unique already forbids two results for one
            // player in one tournament, so that rule is enforced in one place
            // rather than re-stated here as a check that could drift from it.
            // This is the double-click, and the two-administrators-at-once.
            return back()->with('error', __(':name already has a result for this tournament.', [
                'name' => emph($registrant->player_name),
            ]));
        }

        $back = back()->with('status', __(':name is out in :place place and takes :points points.', [
            'name' => emph($registrant->player_name),
            'place' => Number::ordinal($place),
            'points' => number_format($points),
        ]));

        // That was the last one, so ask.
        //
        // Publishing is the end of a tournament and the moment the players are
        // told where they came -- but the button for it lives in the page
        // header, and an administrator who has just worked down a field of
        // twenty is looking at the bottom of a list, not at the top of the
        // page. It was possible to finish a night and simply not notice that
        // anything remained to be done.
        //
        // Asked once, here, rather than nagged: the flag is flashed, so it is
        // gone on the next request whichever way the question is answered.
        //
        // No fresh() needed, though it was written with one at first. Both
        // halves of isComplete() go through the relation METHODS -- registrants()
        // and results() -- so each call is its own query and already sees the
        // result this request just wrote. fresh() bought nothing but a second
        // SELECT of the tournament row; removing it failed no test, which is
        // how it was found.
        if ($tournament->isComplete() && ! $tournament->isPublished()) {
            $back->with('offer_publish', true);
        }

        return $back;
    }

    public function register(PokerTournament $tournament, Request $request): RedirectResponse
    {
        $isAdmin = auth()->user()->is_admin;

        // Registering somebody else means this came from the admin dialog, which
        // is built to add several players in a row. Every exit below carries the
        // flag so the dialog reopens on the way back -- including the refusals,
        // where closing it would hide the list the administrator was working
        // through behind the message explaining what went wrong.
        $reopen = fn (RedirectResponse $back) => $isAdmin && $request->has('user_id')
            ? $back->with('register_open', true)
            : $back;

        // A published tournament is finished. Its players have been told where
        // they came, and a place is a position in a field -- so nothing may
        // change the field's size or any finish in it.
        if ($refusal = $tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }
        $targetUserId = ($isAdmin && $request->has('user_id')) ? $request->user_id : auth()->id();

        // A player's window closes when play begins. Once the cards are in the
        // air the field is whatever is sitting at the tables, and somebody
        // adding themselves from a phone changes how many places there are to
        // hand out for a game already under way.
        //
        // An administrator is not bound by it, and registers right up until the
        // results are published: they are in the room, a late arrival at the
        // table is a real thing, and the shift hook makes it arithmetically
        // safe -- a field of ten becomes a field of eleven and every recorded
        // finish moves down with its points.
        if (! $isAdmin && $tournament->hasStarted()) {
            return back()->with('error', __(
                ':tournament has already started, so you can no longer enter it. '
                .'Ask an administrator if you are at the table.',
                ['tournament' => emph($tournament->name)]
            ));
        }

        // Check if the target user is already registered
        if ($tournament->registrants()->where('user_id', $targetUserId)->exists()) {
            $errorMsg = ($targetUserId === auth()->id())
                ? 'You are already registered for this tournament.'
                : 'That user is already registered for this tournament.';
            return $reopen(back()->with('error', $errorMsg));
        }

        $user = \App\Models\User::findOrFail($targetUserId);

        // The gate reads the TARGET user, not the actor. This one method serves
        // both self-registration and the administrator user_id override, so
        // checking the actor would make an administrator a way around the rule
        // rather than a user of it.
        //
        // The message names WHICH gate refused. A player can now be stopped by
        // approval or by email verification, and an unexplained refusal turns
        // support into guesswork.
        if (! $user->isApproved()) {
            return $reopen(back()->with('error', $targetUserId === auth()->id()
                ? 'Your account is waiting for approval by a league administrator, so you cannot enter tournaments yet.'
                : 'That account has not been approved by a league administrator yet.'));
        }

        $tournament->registrants()->create([
            'user_id' => $user->id,
            'player_name' => $user->first_name . ' ' . $user->last_name,
            'player_nickname' => $user->nickname,
            'registered_at' => now(),
        ]);

        $statusMsg = ($targetUserId === auth()->id())
            ? 'You have successfully registered for '.emph($tournament->name).'!'
            : 'Successfully registered '.emph($user->first_name.' '.$user->last_name).' for the tournament.';

        $back = back()->with('status', $statusMsg);

        // The name on its own, not parsed back out of the sentence: the dialog
        // sets it apart from the rest of the message, and a message is a
        // translatable string whose shape must stay free to change.
        if ($isAdmin && $request->has('user_id')) {
            $back->with('registered_name', trim($user->first_name.' '.$user->last_name));
        }

        return $reopen($back);
    }

    /**
     * Unregister the authenticated user from the tournament.
     */
    public function unregister(PokerTournament $tournament): RedirectResponse
    {
        // A published tournament is finished. Its players have been told where
        // they came, and a place is a position in a field -- so nothing may
        // change the field's size or any finish in it.
        if ($refusal = $tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }

        // The only rule left, and the one that was always doing the work. A
        // place is a position in a field -- tenth of ten -- so once a finish is
        // recorded, taking a player out makes that finish describe a tournament
        // that never happened. The deadline used to sit in front of this and
        // refuse first; it refused plenty of withdrawals this rule has no
        // objection to, from players who simply changed their mind on the day.
        if ($tournament->hasRecordedResults()) {
            return back()->with('error', __(
                'Results have been recorded for this tournament, so entries can no longer be withdrawn.'
            ));
        }

        $registration = $tournament->registrants()->where('user_id', auth()->id())->first();

        if (!$registration) {
            return back()->with('error', 'You are not registered for this tournament.');
        }

        $registration->delete();

        return back()->with('status', 'You have successfully unregistered from '.emph($tournament->name).'.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PokerTournament $tournament): Response
    {
        return Inertia::render('Poker/Tournaments/Edit', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'description' => $tournament->description,
                'venue_id' => $tournament->venue_id,
                'season_id' => $tournament->season_id,

                // Y-m-d\TH:i, the only shape an <input type="datetime-local">
                // will show. The cast Carbon stringifies with a space and
                // seconds, and the control renders that as blank.
                'start_time' => $tournament->start_time?->format('Y-m-d\TH:i'),
            ],
            'venues' => Venue::all()->map(fn (Venue $venue) => [
                'id' => $venue->id,
                'name' => $venue->name,
            ])->all(),
            'seasons' => PokerSeason::all()->map(fn (PokerSeason $season) => [
                'id' => $season->id,
                'name' => $season->name,
            ])->all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PokerTournament $tournament): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'venue_id' => 'required|exists:venues,id',
            'season_id' => 'required|exists:seasons,id',
        ]);

        $tournament->update($validated);

        return redirect()->route('poker.tournaments.index')->with('status', __(':tournament updated.', [
            'tournament' => emph($tournament->name),
        ]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PokerTournament $tournament): RedirectResponse
    {
        $name = $tournament->name;

        $tournament->delete();

        return redirect()->route('poker.tournaments.index')->with('status', __(':tournament deleted.', [
            'tournament' => emph($name),
        ]));
    }
}
