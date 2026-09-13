<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\Venue;
use App\Notifications\TournamentPlacement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PokerTournamentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $tournaments = PokerTournament::with(['venue', 'season'])->latest()->paginate(10);
        return view('poker.tournaments.index', compact('tournaments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $venues = Venue::all();
        $seasons = PokerSeason::orderBy('name', 'desc')->get();
        $currentSeason = PokerSeason::current();
        
        return view('poker.tournaments.create', compact('venues', 'seasons', 'currentSeason'));
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

        PokerTournament::create($validated);

        return redirect()->route('poker.tournaments.index')->with('status', 'Tournament created successfully!');
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
    public function show(PokerTournament $tournament): View
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
        // "Past" means play has begun.
        $isPast = \Illuminate\Support\Carbon::parse($tournament->start_time)->isPast();

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
        $standingsTitle = match (true) {
            $tournament->isComplete() => __('Final Standings'),
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
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'played' => $user->tournament_registrations_count,
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

        return view('poker.tournaments.show', compact(
            'tournament',
            'registrantsCount',
            'isUserRegistered',
            'isPast',
            'registerCandidates',
            'nextPlace',
            'nextPlacePoints',
            'standings',
            'standingsTitle'
        ));
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

        return back()->with('status', __(':name is out in :place place and takes :points points.', [
            'name' => emph($registrant->player_name),
            'place' => Number::ordinal($place),
            'points' => number_format($points),
        ]));
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
    public function edit(PokerTournament $tournament): View
    {
        $venues = Venue::all();
        $seasons = PokerSeason::all();
        return view('poker.tournaments.edit', compact('tournament', 'venues', 'seasons'));
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

        return redirect()->route('poker.tournaments.index')->with('status', 'Tournament updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PokerTournament $tournament): RedirectResponse
    {
        $tournament->delete();

        return redirect()->route('poker.tournaments.index')->with('status', 'Tournament deleted successfully!');
    }
}
