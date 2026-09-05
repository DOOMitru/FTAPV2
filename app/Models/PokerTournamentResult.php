<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
