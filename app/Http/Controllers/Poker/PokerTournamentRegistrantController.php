<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PokerTournamentRegistrantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $registrants = PokerTournamentRegistrant::with(['user', 'tournament'])->latest()->paginate(10);
        return view('poker.registrants.index', compact('registrants'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $users = User::all();
        $tournaments = PokerTournament::latest()->get();
        return view('poker.registrants.create', compact('users', 'tournaments'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'user_id' => 'required|exists:users,id',
            'player_name' => 'required|string|max:255',
            'player_nickname' => 'nullable|string|max:255',
            'registered_at' => 'required|date',
        ]);

        $validated['registered_by'] = $request->user()?->id;

        $tournament = PokerTournament::findOrFail($validated['tournament_id']);
        $validated['is_late_entry'] = strtotime($validated['registered_at']) > strtotime($tournament->scheduled_at);

        PokerTournamentRegistrant::create($validated);

        return redirect()->route('poker.registrants.index')->with('status', 'Tournament registrant added successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PokerTournamentRegistrant $registrant): View
    {
        $users = User::all();
        $tournaments = PokerTournament::latest()->get();
        return view('poker.registrants.edit', compact('registrant', 'users', 'tournaments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PokerTournamentRegistrant $registrant): RedirectResponse
    {
        $validated = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'user_id' => 'required|exists:users,id',
            'player_name' => 'required|string|max:255',
            'player_nickname' => 'nullable|string|max:255',
            'registered_at' => 'required|date',
        ]);

        $registrant->update($validated);

        return redirect()->route('poker.registrants.index')->with('status', 'Tournament registrant updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PokerTournamentRegistrant $registrant): RedirectResponse
    {
        $registrant->delete();

        return redirect()->route('poker.registrants.index')->with('status', 'Tournament registrant removed successfully!');
    }
}
