<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PokerSeason extends Model
{
    /** @use HasFactory<\Database\Factories\PokerSeasonFactory> */
    use HasFactory, HasUlids;

    protected $table = 'seasons';

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (PokerSeason $season) {
            // Default to current on creation
            if (!isset($season->is_current)) {
                $season->is_current = true;
            }

            if ($season->is_current) {
                static::where('is_current', true)->update(['is_current' => false]);
            }
        });

        static::updating(function (PokerSeason $season) {
            if ($season->isDirty('is_current') && $season->is_current) {
                static::where('id', '!=', $season->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }
        });
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(PokerTournament::class, 'season_id');
    }

    public function results(): HasManyThrough
    {
        return $this->hasManyThrough(PokerTournamentResult::class, PokerTournament::class, 'season_id', 'tournament_id');
    }

    public function registrants(): HasManyThrough
    {
        return $this->hasManyThrough(PokerTournamentRegistrant::class, PokerTournament::class, 'season_id', 'tournament_id');
    }
}
