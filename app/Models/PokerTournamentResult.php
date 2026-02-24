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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(PokerTournament::class, 'tournament_id');
    }
}
