<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $users = User::orderBy('first_name')->paginate(15);

        // Not paginated. A queue that needs paging is a queue nobody is
        // working; if it ever grows that large that is the signal, not a
        // pagination bug. Oldest first, because the person who has waited
        // longest is the one to deal with.
        $pending = User::awaitingApproval()->orderBy('created_at')->get();

        return view('users.index', compact('users', 'pending'));
    }

    /**
     * Admit a player to the league.
     *
     * Also the route back for a REJECTED account. Once rejected it has left the
     * pending queue, so without this the reversibility that justified keeping
     * the row would be a claim the interface does not support.
     */
    public function approve(User $user): RedirectResponse
    {
        $user->forceFill([
            'approval_status' => 'approved',
            'approval_decided_at' => now(),
            'approval_decided_by' => auth()->id(),
        ])->save();

        return back()->with('status', $user->first_name.' '.$user->last_name.' can now enter tournaments.');
    }

    /**
     * Refuse a player, keeping the account.
     *
     * Deleting would make the decision unrecoverable, lose any record that it
     * was made, and let the same person re-register into a clean slate the next
     * minute.
     */
    public function reject(User $user): RedirectResponse
    {
        $user->forceFill([
            'approval_status' => 'rejected',
            'approval_decided_at' => now(),
            'approval_decided_by' => auth()->id(),
        ])->save();

        return back()->with('status', $user->first_name.' '.$user->last_name.' was not approved.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): View
    {
        // Resolved here rather than via a relation: approval_decided_by is a
        // plain string column, not a foreign key, because users.id is a ULID
        // and the row it points at may since have been deleted. Null is the
        // normal case for accounts the migration grandfathered.
        $decidedBy = $user->approval_decided_by
            ? User::find($user->approval_decided_by)
            : null;

        return view('users.show', compact('user', 'decidedBy'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'is_admin' => 'boolean',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('profile-images', 'public');
            
            // Delete old image
            if ($user->profile_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_image);
            }
            
            $validated['profile_image'] = $path;
        }

        $user->update($validated);

        return redirect()->route('users.index')->with('status', 'User updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted successfully!');
    }
}
