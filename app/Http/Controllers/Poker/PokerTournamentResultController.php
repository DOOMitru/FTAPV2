<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use App\Models\PointsStructure;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class PokerTournamentResultController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $results = PokerTournamentResult::with(['user', 'tournament'])->latest()->paginate(10);
        return view('poker.results.index', compact('results'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $tournaments = PokerTournament::with(['registrants.user', 'results'])->latest()->get();
        $pointsStructures = PointsStructure::orderBy('place')->get();
        return view('poker.results.create', compact('tournaments', 'pointsStructures'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'points_structure_id' => 'required|exists:points_structure,id',
            'user_id' => [
                'required',
                'exists:users,id',
                Rule::unique('tournament_results')->where(fn ($query) => $query->where('tournament_id', $request->tournament_id)),
            ],
            'player_name' => 'required|string|max:255',
            'player_nickname' => 'nullable|string|max:255',
        ]);

        $structure = PointsStructure::findOrFail($validated['points_structure_id']);
        
        PokerTournamentResult::create([
            'tournament_id' => $validated['tournament_id'],
            'place' => $structure->place,
            'points' => $structure->points,
            'user_id' => $validated['user_id'],
            'player_name' => $validated['player_name'],
            'player_nickname' => $validated['player_nickname'],
        ]);

        return redirect()->route('poker.results.index')->with('status', 'Tournament result added successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PokerTournamentResult $result): View
    {
        $tournaments = PokerTournament::with(['registrants.user', 'results'])->latest()->get();
        $pointsStructures = PointsStructure::orderBy('place')->get();
        return view('poker.results.edit', compact('result', 'tournaments', 'pointsStructures'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PokerTournamentResult $result): RedirectResponse
    {
        $validated = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'points_structure_id' => 'required|exists:points_structure,id',
            'user_id' => [
                'required',
                'exists:users,id',
                Rule::unique('tournament_results')->where(fn ($query) => $query->where('tournament_id', $request->tournament_id))->ignore($result->id),
            ],
            'player_name' => 'required|string|max:255',
            'player_nickname' => 'nullable|string|max:255',
        ]);

        $structure = PointsStructure::findOrFail($validated['points_structure_id']);

        $result->update([
            'tournament_id' => $validated['tournament_id'],
            'place' => $structure->place,
            'points' => $structure->points,
            'user_id' => $validated['user_id'],
            'player_name' => $validated['player_name'],
            'player_nickname' => $validated['player_nickname'],
        ]);

        return redirect()->route('poker.results.index')->with('status', 'Tournament result updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PokerTournamentResult $result): RedirectResponse
    {
        $result->delete();

        return redirect()->route('poker.results.index')->with('status', 'Tournament result deleted successfully!');
    }
}
