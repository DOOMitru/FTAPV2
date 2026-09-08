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
    public function index(Request $request): View
    {
        // The list is the league's whole history in one table. Narrowed to the
        // night an administrator is working on -- nearest to now, behind or
        // ahead -- and changeable from the picker at the top of the page.
        //
        // firstWhere rather than findOrFail: a stale bookmark or a tournament
        // deleted since should show the default, not a 404.
        $tournaments = PokerTournament::orderByDesc('start_time')->get();

        $selected = $tournaments->firstWhere('id', $request->query('tournament'))
            ?? PokerTournament::nearest();

        $results = PokerTournamentResult::with(['user', 'tournament'])
            ->when($selected, fn ($query) => $query->where('tournament_id', $selected->id))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('poker.results.index', compact('results', 'tournaments', 'selected'));
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

        // After validation, because the tournament id has to be known good
        // before it can be looked up.
        if ($refusal = PokerTournament::findOrFail($validated['tournament_id'])->publishedRefusal()) {
            return back()->with('error', $refusal);
        }

        $structure = PointsStructure::findOrFail($validated['points_structure_id']);
        
        PokerTournamentResult::create([
            'tournament_id' => $validated['tournament_id'],
            'place' => $structure->place,
            'points' => $structure->points,
            'user_id' => $validated['user_id'],
            'player_name' => $validated['player_name'],
            // ?? null, because the field is nullable: a request that omits
            // it leaves the key absent from $validated entirely, and reading
            // it raised an ErrorException and a 500. The forms always post an
            // empty string, which is why nothing had hit it.
            'player_nickname' => $validated['player_nickname'] ?? null,
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
        // A published tournament is finished, and its players have been told
        // what they scored. A result may not be changed underneath them.
        if ($refusal = $result->tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }

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
            // ?? null, because the field is nullable: a request that omits
            // it leaves the key absent from $validated entirely, and reading
            // it raised an ErrorException and a 500. The forms always post an
            // empty string, which is why nothing had hit it.
            'player_nickname' => $validated['player_nickname'] ?? null,
        ]);

        return redirect()->route('poker.results.index')->with('status', 'Tournament result updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PokerTournamentResult $result): RedirectResponse
    {
        // A published tournament is finished, and its players have been told
        // what they scored. A result may not be changed underneath them.
        if ($refusal = $result->tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }

        $result->delete();

        return redirect()->route('poker.results.index')->with('status', 'Tournament result deleted successfully!');
    }
}
