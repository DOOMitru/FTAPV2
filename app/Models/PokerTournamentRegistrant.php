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
            // A field that grows after play has begun pushes every finish down
            // one. The first player out of ten finished tenth; once an
            // eleventh enters, that same finish is eleventh -- a place
            // describes a position in a field, and the field just changed.
            //
            // Here rather than in a controller because there are two ways in,
            // the tournament's own register action and the registrants CRUD,
            // and this is a property of the data rather than of either request.
            //
            // One UPDATE, not a read-modify-write per row: atomic, and it
            // shifts whatever places are already recorded rather than
            // recomputing them from an elimination order this table does not
            // keep. A result entered by hand through the results screen moves
            // with the rest instead of being overwritten.
                PokerTournamentResult::where('tournament_id', $registrant->tournament_id)
                    ->increment('place');

                // A place is worth what the structure pays for it, so a finish
                // that moves must take the money of the place it moved to. The
                // shift used to run alone: tenth of ten became eleventh of
                // eleven and kept tenth place's points, and nothing downstream
                // put it right -- publish() reads the stored points and the
                // placement notification quotes them, so the wrong figure
                // reached the player and the season standings.
                static::repriceFinishes($registrant->tournament_id);
            });
        });
    }

    /**
     * Set every finish's points to what its CURRENT place pays.
     *
     * One UPDATE with a CASE, matching the shift above: atomic, and a single
     * statement whatever the size of the field. The alternative -- a lookup and
     * a save per row -- is also where the ELSE below tends to get lost, because
     * a miss reads as "leave it alone" rather than as "this place pays
     * nothing".
     *
     * ELSE 0 is the rule, not a fallback. A structure paying the top ten of a
     * field of twenty means eleventh onwards score nothing, which is the same
     * reading as eliminate()'s `?? 0`. Without it a finish pushed off the end
     * of the structure would keep whatever it was last worth.
     *
     * CASE is plain SQL and behaves the same on SQLite and MySQL. Values are
     * bound rather than interpolated even though both columns are integer-cast
     * by PointsStructure.
     */
    private static function repriceFinishes(string $tournamentId): void
    {
        $structure = PointsStructure::pluck('points', 'place');

        $cases = '';
        $bindings = [];

        foreach ($structure as $place => $points) {
            $cases .= ' when ? then ?';
            $bindings[] = (int) $place;
            $bindings[] = (int) $points;
        }

        // No structure at all pays nothing, and "case end" is a syntax error.
        $points = $cases === '' ? '0' : 'case place'.$cases.' else 0 end';

        $bindings[] = $tournamentId;

        DB::update(
            'update tournament_results set points = '.$points.' where tournament_id = ?',
            $bindings
        );
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
