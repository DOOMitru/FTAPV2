<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\Venue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
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
        $totalPoints = $tournament->results->sum('points');
        
        $orderedResults = $tournament->results->sortBy('place')->values();
        // The settled places only -- see PokerTournament::podium(). take(3) on
        // the sorted results was the current best three, which mid-tournament
        // are not the podium at all: places count down from the bottom, so the
        // lowest numbers on record are simply the last few knocked out.
        $podium = $tournament->podium();

        $isUserRegistered = $tournament->registrants()->where('user_id', auth()->id())->exists();

        // The shared event card reads viewer_registered -- the attribute the
        // events and home pages load with withExists. Set it from the value
        // already computed here so the card and the page around it cannot
        // disagree about whether you are in this tournament.
        $tournament->viewer_registered = $isUserRegistered;
        // "Past" means play has begun.
        $isPast = \Illuminate\Support\Carbon::parse($tournament->start_time)->isPast();

        $pointsStructure = PointsStructure::orderBy('place')->get();

        // Places are handed out from the bottom of the field: the first player
        // out of ten finishes tenth, and the last one standing takes first. So
        // the place on offer is however many registrants are still without a
        // result -- the same number for whoever goes out next, which is why it
        // is computed once here rather than per row.
        $nextPlace = $registrantsCount - $resultsCount;
        $nextPlacePoints = $pointsStructure->firstWhere('place', $nextPlace)?->points ?? 0;

        // Keyed so a registrant row can find its own result without a query
        // each. user_id, because that is what a registrant carries.
        $resultsByUser = $tournament->results->keyBy('user_id');

        $availableUsers = collect();
        if (auth()->user()->is_admin) {
            $registeredUserIds = $tournament->registrants()->pluck('user_id')->toArray();
            // approved(), for the same reason the registrant pickers filter:
            // register() refuses an unapproved target, so offering one here
            // would let an administrator pick a player the very next request
            // rejects.
            $availableUsers = \App\Models\User::approved()
                ->whereNotIn('id', $registeredUserIds)
                ->orderBy('first_name')
                ->get();
        }

        return view('poker.tournaments.show', compact(
            'tournament',
            'registrantsCount',
            'resultsCount',
            'totalPoints',
            'orderedResults',
            'podium',
            'isUserRegistered',
            'isPast',
            'pointsStructure',
            'availableUsers',
            'nextPlace',
            'nextPlacePoints',
            'resultsByUser'
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
    public function eliminate(PokerTournament $tournament, Request $request): RedirectResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'string']]);

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
                'name' => $registrant->player_name,
            ]));
        }

        return back()->with('status', __(':name is out in :place place and takes :points points.', [
            'name' => $registrant->player_name,
            'place' => Number::ordinal($place),
            'points' => number_format($points),
        ]));
    }

    public function register(PokerTournament $tournament, Request $request): RedirectResponse
    {
        $isAdmin = auth()->user()->is_admin;
        $targetUserId = ($isAdmin && $request->has('user_id')) ? $request->user_id : auth()->id();

        // No deadline check any more, for anyone. Entering a tournament that
        // already has results is deliberately still allowed: a late entry
        // changes the size of the field, and PokerTournamentRegistrant's shift
        // hook moves every recorded finish down to match. That is why joining
        // late is safe where leaving late is not -- adding a player to a field
        // of ten makes it a field of eleven, unambiguously, while removing one
        // leaves the question of whether they played at all.

        // Check if the target user is already registered
        if ($tournament->registrants()->where('user_id', $targetUserId)->exists()) {
            $errorMsg = ($targetUserId === auth()->id()) 
                ? 'You are already registered for this tournament.' 
                : 'That user is already registered for this tournament.';
            return back()->with('error', $errorMsg);
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
            return back()->with('error', $targetUserId === auth()->id()
                ? 'Your account is waiting for approval by a league administrator, so you cannot enter tournaments yet.'
                : 'That account has not been approved by a league administrator yet.');
        }

        $tournament->registrants()->create([
            'user_id' => $user->id,
            'player_name' => $user->first_name . ' ' . $user->last_name,
            'player_nickname' => $user->nickname,
            'registered_at' => now(),
        ]);

        $statusMsg = ($targetUserId === auth()->id())
            ? 'You have successfully registered for ' . $tournament->name . '!'
            : 'Successfully registered ' . $user->first_name . ' ' . $user->last_name . ' for the tournament.';

        return back()->with('status', $statusMsg);
    }

    /**
     * Unregister the authenticated user from the tournament.
     */
    public function unregister(PokerTournament $tournament): RedirectResponse
    {
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

        return back()->with('status', 'You have successfully unregistered from ' . $tournament->name . '.');
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
