<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PointsStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PointsStructureController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $structures = PointsStructure::orderBy('place')->paginate(10);
        return view('poker.points-structure.index', compact('structures'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('poker.points-structure.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'place' => 'required|integer|min:1|unique:points_structure,place',
            'points' => 'required|integer|min:0',
        ]);

        PointsStructure::create($validated);

        return redirect()->route('poker.points-structure.index')->with('status', 'Points structure entry created successfully!');
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
        $validated = $request->validate([
            'place' => 'required|integer|min:1|unique:points_structure,place,' . $points_structure->id,
            'points' => 'required|integer|min:0',
        ]);

        $points_structure->update($validated);

        return redirect()->route('poker.points-structure.index')->with('status', 'Points structure entry updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PointsStructure $points_structure): RedirectResponse
    {
        $points_structure->delete();

        return redirect()->route('poker.points-structure.index')->with('status', 'Points structure entry deleted successfully!');
    }
}
