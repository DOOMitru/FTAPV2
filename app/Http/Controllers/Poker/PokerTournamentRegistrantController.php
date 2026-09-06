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
        // withCount on the tournament, because the view asks every row whether
        // its tournament has results yet -- countOf() reads the alias, so this
        // is one query rather than one per row.
        $registrants = PokerTournamentRegistrant::with(['user', 'tournament' => fn ($q) => $q->withCount('results')])
            ->latest()
            ->paginate(10);
        return view('poker.registrants.index', compact('registrants'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        // Approved only. The store above refuses anyone else, and a picker
        // that offers a choice the store will reject is a worse failure than
        // one that never offers it.
        $users = User::approved()->orderBy('first_name')->get();
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
            // Approval is a validation concern here rather than an abort:
            // this arrives from a form, so a field-level error puts the message
            // beside the field that caused it. Applied to update as well as
            // store -- an edit must not be able to reassign a registration to
            // an account the league has not admitted.
            'user_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('users', 'id')->where('approval_status', 'approved'),
            ],
            'player_name' => 'required|string|max:255',
            'player_nickname' => 'nullable|string|max:255',
            'registered_at' => 'required|date',
        ]);

        $validated['registered_by'] = $request->user()?->id;

        $tournament = PokerTournament::findOrFail($validated['tournament_id']);
        // Measured against start_time now that there is no registration
        // deadline to be late for. "Late" means play had already begun, which
        // is what the flag was read as anyway -- and what its test has always
        // been named after, though the arithmetic said otherwise.
        $validated['is_late_entry'] = strtotime($validated['registered_at']) > strtotime($tournament->start_time);

        PokerTournamentRegistrant::create($validated);

        return redirect()->route('poker.registrants.index')->with('status', 'Tournament registrant added successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PokerTournamentRegistrant $registrant): View
    {
        // Approved only. The store above refuses anyone else, and a picker
        // that offers a choice the store will reject is a worse failure than
        // one that never offers it.
        $users = User::approved()->orderBy('first_name')->get();
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
            // Approval is a validation concern here rather than an abort:
            // this arrives from a form, so a field-level error puts the message
            // beside the field that caused it. Applied to update as well as
            // store -- an edit must not be able to reassign a registration to
            // an account the league has not admitted.
            'user_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('users', 'id')->where('approval_status', 'approved'),
            ],
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
        // Not even for an administrator. Once finishes are recorded, the field
        // they describe is settled, and removing someone from it silently makes
        // every one of those places wrong.
        if ($registrant->tournament->hasRecordedResults()) {
            return back()->with('error', __(
                ':name cannot be removed from :tournament: results have been recorded, and every '
                .'finish describes the size of the field. Delete the results first if the entry is wrong.',
                ['name' => $registrant->player_name, 'tournament' => $registrant->tournament->name]
            ));
        }

        $name = $registrant->player_name;
        $tournament = $registrant->tournament->name;

        $registrant->delete();

        // back(), not the registrants index. This is now reached from the
        // tournament page as well, and landing an admin in a list of every
        // entry in the league after removing one is a page they did not ask
        // for. From the index, back() IS the index.
        return back()->with('status', __(':name has been removed from :tournament.', [
            'name' => $name,
            'tournament' => $tournament,
        ]));
    }
}
