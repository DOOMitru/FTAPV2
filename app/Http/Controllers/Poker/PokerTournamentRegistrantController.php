<?php

namespace App\Http\Controllers\Poker;

use App\Http\Controllers\Controller;
use App\Models\PokerTournamentRegistrant;
use Illuminate\Http\RedirectResponse;

/**
 * Removing a tournament entry.
 *
 * All this controller does now. Entries are MADE from the tournament's own
 * Register players dialog, which posts to tournaments.register; the admin
 * listing and its create and edit forms are gone, because a list of every
 * entry in league history was a worse place to work than the night itself.
 */
class PokerTournamentRegistrantController extends Controller
{
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PokerTournamentRegistrant $registrant): RedirectResponse
    {
        // Checked before the results rule below, which would also refuse this
        // -- a published tournament has a finish for everyone -- but for a
        // reason that is true of any scored tournament. "Results have been
        // published" is what an administrator here needs to hear.
        if ($refusal = $registrant->tournament->publishedRefusal()) {
            return back()->with('error', $refusal);
        }

        // Per PLAYER, not per tournament.
        //
        // This used to refuse the moment any finish existed anywhere in the
        // tournament, which locked the whole field on the first elimination --
        // including the nine people still playing, one of whom might have been
        // entered by mistake. What actually cannot be removed is somebody who
        // has a finish of their own: their place is a position in a field, and
        // deleting the position makes every other place describe a tournament
        // that never happened.
        //
        // Everyone else can go, and the field shrinks cleanly: the shrink hook
        // on this model moves every recorded finish up a place and reprices it,
        // the mirror of what a late entry does.
        if ($registrant->hasFinished()) {
            return back()->with('error', __(
                // The second sentence used to send an administrator to the
                // results screen to delete the finish first. That screen is
                // gone, and with it the only way to undo one -- so the message
                // no longer names a remedy, because there is not one to name.
                ':name has already been eliminated from :tournament and cannot be removed. '
                .'Their finish is a position in the field that the players below them are counted from.',
                [
                    'name' => emph($registrant->player_name),
                    'tournament' => emph($registrant->tournament->name),
                ]
            ));
        }

        $name = $registrant->player_name;
        $tournament = $registrant->tournament->name;

        $registrant->delete();

        // back(), not a fixed destination. The tournament page is the only
        // caller now that the registrants listing is gone, but back() is still
        // right: it returns an administrator to the night they were working
        // rather than anywhere this controller chooses.
        return back()->with('status', __(':name has been removed from :tournament.', [
            'name' => emph($name),
            'tournament' => emph($tournament),
        ]));
    }
}
