<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PokerTournamentRegistrant extends Model
{
    /** @use HasFactory<\Database\Factories\PokerTournamentRegistrantFactory> */
    use HasFactory, HasUlids;

    protected $table = 'tournament_registrants';

    protected $fillable = [
        'user_id',
        'player_name',
        'player_nickname',
        'registered_at',
        'tournament_id',
        'registered_by',
        'is_late_entry',
    ];

    protected $casts = [
        'is_late_entry' => 'boolean',
        'registered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (PokerTournamentRegistrant $registrant) {
            // Both halves or neither. A shift that lands without its repricing
            // leaves the table in the exact state this hook exists to prevent:
            // finishes holding the money for places they no longer occupy.
            DB::transaction(function () use ($registrant) {
                // A field that grows after play has begun pushes every finish
                // down one. The first player out of ten finished tenth; once an
                // eleventh enters, that same finish is eleventh -- a place
                // describes a position in a field, and the field just changed.
                //
                // Here rather than in a controller because there are two ways
                // in, the tournament's own register action and the registrants
                // CRUD, and this is a property of the data rather than of
                // either request.
                //
                // One UPDATE, not a read-modify-write per row: atomic, and it
                // shifts whatever places are already recorded rather than
                // recomputing them from an elimination order this table does
                // not keep. A result entered by hand through the results screen
                // moves down with the rest rather than being resequenced.
                PokerTournamentResult::where('tournament_id', $registrant->tournament_id)
                    ->increment('place');

                // A place is worth what the structure pays for it, so a
                // finish that moves must take the money of the place it moved
                // to. The shift used to run alone: tenth of ten became eleventh
                // of eleven and kept tenth place's points, and nothing
                // downstream put it right -- publish() reads the stored points
                // and the placement notification quotes them, so the wrong
                // figure reached the player and the season standings.
                //
                // Nothing bespoke is lost to this. Every path that writes a
                // result takes its points from a structure row, so repricing
                // re-derives what was already derived.
                PokerTournamentResult::repriceForTournament($registrant->tournament_id);
            });
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(PokerTournament::class, 'tournament_id');
    }
}
