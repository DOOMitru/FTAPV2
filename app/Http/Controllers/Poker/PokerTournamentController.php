<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $currentSeason = PokerSeason::where('is_current', true)->first();
        
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

        $currentSeason = PokerSeason::where('is_current', true)->first();

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
        $podium = $orderedResults->take(3);

        $isUserRegistered = $tournament->registrants()->where('user_id', auth()->id())->exists();
        $isPast = \Illuminate\Support\Carbon::parse($tournament->start_time)->isPast();

        $pointsStructure = \App\Models\PointsStructure::orderBy('place')->get();

        $availableUsers = collect();
        if (auth()->user()->is_admin) {
            $registeredUserIds = $tournament->registrants()->pluck('user_id')->toArray();
            $availableUsers = \App\Models\User::whereNotIn('id', $registeredUserIds)
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
            'availableUsers'
        ));
    }

    /**
     * Register a user for the tournament (Self or Admin override).
     */
    public function register(PokerTournament $tournament, Request $request): RedirectResponse
    {
        $isAdmin = auth()->user()->is_admin;
        $targetUserId = ($isAdmin && $request->has('user_id')) ? $request->user_id : auth()->id();
        
        // Only enforce "past" check for non-admins
        if (!$isAdmin && \Illuminate\Support\Carbon::parse($tournament->start_time)->isPast()) {
            return back()->with('error', 'Cannot register for a tournament that has already started or passed.');
        }

        // Check if the target user is already registered
        if ($tournament->registrants()->where('user_id', $targetUserId)->exists()) {
            $errorMsg = ($targetUserId === auth()->id()) 
                ? 'You are already registered for this tournament.' 
                : 'That user is already registered for this tournament.';
            return back()->with('error', $errorMsg);
        }

        $user = \App\Models\User::findOrFail($targetUserId);

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
        if (\Illuminate\Support\Carbon::parse($tournament->start_time)->isPast()) {
            return back()->with('error', 'Cannot unregister from a tournament that has already started or passed.');
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
