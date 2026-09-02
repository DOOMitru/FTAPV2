<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PointsStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\View\View;

/**
 * The points structure, kept as a ladder from first place down.
 *
 * Places are not the administrator's to choose. A structure is a contiguous
 * run of places from 1 to N, so a new entry is always one deeper than the
 * deepest and only the deepest can be removed. Left free, a place typed by
 * hand opens a hole in the ladder -- and a hole is not visible on this screen,
 * it shows up later as a finish that scores nothing.
 */
class PointsStructureController extends Controller
{
    /** Validation for the one field an administrator actually supplies. */
    private const RULES = ['points' => ['required', 'integer', 'min:1']];

    /** The next place on the ladder, which is one below the deepest. */
    private function nextPlace(): int
    {
        return (PointsStructure::max('place') ?? 0) + 1;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $structures = PointsStructure::orderBy('place')->paginate(10);

        // Only the deepest place may be removed, and it may not be on the page
        // being looked at -- so the view is told which one it is rather than
        // guessing from the rows in front of it.
        $lastPlace = PointsStructure::max('place');

        return view('poker.points-structure.index', compact('structures', 'lastPlace'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('poker.points-structure.create', ['nextPlace' => $this->nextPlace()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(self::RULES);

        // Computed, never taken from the request. A place posted by hand could
        // collide with a row that exists or leave a gap between this one and
        // the last.
        $validated['place'] = $this->nextPlace();

        PointsStructure::create($validated);

        return redirect()->route('poker.points-structure.index')->with('status', __(
            ':place place added, worth :points points.',
            ['place' => Number::ordinal($validated['place']), 'points' => number_format($validated['points'])]
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PointsStructure $points_structure): View
    {
        return view('poker.points-structure.edit', compact('points_structure'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PointsStructure $points_structure): RedirectResponse
    {
        // Points only. Moving a place would either collide with another or
        // leave a hole; the ladder keeps its shape by growing and shrinking at
        // the bottom, never in the middle.
        $validated = $request->validate(self::RULES);

        $points_structure->update($validated);

        return redirect()->route('poker.points-structure.index')->with('status', __(
            ':place place is now worth :points points.',
            ['place' => Number::ordinal($points_structure->place), 'points' => number_format($validated['points'])]
        ));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PointsStructure $points_structure): RedirectResponse
    {
        // The deepest place only. Taking one out of the middle leaves a hole
        // that nothing on this screen shows, and which surfaces later as a
        // finish that scores nothing.
        if ($points_structure->place !== PointsStructure::max('place')) {
            return back()->with('error', __(
                'Only the last place can be removed. :place place is not the last one.',
                ['place' => Number::ordinal($points_structure->place)]
            ));
        }

        $points_structure->delete();

        return redirect()->route('poker.points-structure.index')->with('status', __(
            ':place place removed.',
            ['place' => Number::ordinal($points_structure->place)]
        ));
    }
}
