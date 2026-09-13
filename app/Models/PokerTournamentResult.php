<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PokerTournamentResult extends Model
{
    /** @use HasFactory<\Database\Factories\PokerTournamentResultFactory> */
    use HasFactory, HasUlids;

    protected $table = 'tournament_results';

    protected $fillable = [
        'place',
        'points',
        'user_id',
        'player_name',
        'player_nickname',
        'tournament_id',
    ];

    /**
     * Integer columns, cast so they arrive as integers.
     *
     * Not cosmetic and not test-only. PDO's MySQL driver returns every column
     * as a STRING by default, where SQLite returns typed values -- so without
     * this the same row is 5 in development and "5" in production, and every
     * identity comparison and int-typed parameter behaves differently on the
     * two. The casts make the model the authority on its own types instead of
     * the driver.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'place' => 'integer',
            'points' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(PokerTournament::class, 'tournament_id');
    }

    /**
     * Set every finish in one tournament to the points its CURRENT place pays.
     *
     * Called from two places that must not drift: the hook on
     * PokerTournamentRegistrant, which runs whenever a late entry shifts the
     * field, and results:reprice, which repairs rows written before that hook
     * did this. A repair that computed points differently from the runtime
     * would be its own bug, so there is one statement and both use it.
     *
     * One UPDATE with a CASE: atomic, and a single statement whatever the size
     * of the field. The alternative -- a lookup and a save per row -- is also
     * where the ELSE below tends to get lost, because a miss reads as "leave it
     * alone" rather than as "this place pays nothing".
     *
     * ELSE 0 is the rule, not a fallback. A structure paying the top ten of a
     * field of twenty means eleventh onwards score nothing, the same reading as
     * eliminate()'s `?? 0`. Without it a finish pushed off the end of the
     * structure would keep whatever it was last worth.
     *
     * CASE is plain SQL and behaves the same on SQLite and MySQL. Values are
     * bound rather than interpolated even though both columns are integer-cast
     * by PointsStructure.
     */
    public static function repriceForTournament(string $tournamentId): void
    {
        $cases = '';
        $bindings = [];

        foreach (PointsStructure::pluck('points', 'place') as $place => $points) {
            $cases .= ' when ? then ?';
            $bindings[] = (int) $place;
            $bindings[] = (int) $points;
        }

        // No structure at all pays nothing, and "case place end" is a syntax
        // error rather than a no-op -- so the empty case is spelled out.
        $points = $cases === '' ? '0' : 'case place'.$cases.' else 0 end';

        $bindings[] = $tournamentId;

        DB::update(
            'update tournament_results set points = '.$points.' where tournament_id = ?',
            $bindings
        );
    }
}
