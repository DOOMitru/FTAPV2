<?php

namespace App\Notifications;

use App\Models\PokerTournamentResult;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Told to a player when the results of a tournament they scored in are
 * published.
 *
 * Only players with points > 0 receive one: the cut is the points structure, so
 * a structure paying the top ten of a field of twenty notifies ten people and
 * eleventh onwards score nothing and hear nothing.
 *
 * The data is a COPY of the tournament's name and date rather than a reference
 * to it. A tournament can be renamed, re-dated or deleted afterwards, and a
 * message in somebody's inbox should go on saying what was true when it was
 * sent rather than quietly changing underneath them.
 */
class TournamentPlacement extends Notification
{
    use Queueable;

    public function __construct(private PokerTournamentResult $result) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $tournament = $this->result->tournament;

        return [
            'tournament_id' => $tournament->id,
            'tournament_name' => $tournament->name,
            'played_on' => $tournament->start_time->toDateString(),
            'place' => $this->result->place,
            'points' => $this->result->points,
        ];
    }
}
