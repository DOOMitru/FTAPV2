<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PokerTournament extends Model
{
    /** @use HasFactory<\Database\Factories\PokerTournamentFactory> */
    use HasFactory, HasUlids;

    protected $table = 'tournaments';

    protected $fillable = [
        'name',
        'description',
        'scheduled_at',
        'start_time',
        'venue_id',
        'season_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'start_time' => 'datetime',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(PokerSeason::class, 'season_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(PokerTournamentResult::class, 'tournament_id');
    }

    public function registrants(): HasMany
    {
        return $this->hasMany(PokerTournamentRegistrant::class, 'tournament_id');
    }
}
