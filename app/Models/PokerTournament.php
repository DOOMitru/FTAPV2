<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * Registration closes at scheduled_at, which is earlier than start_time.
     * "Not started" is not the same as "still open".
     */
    protected function registrationOpen(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->scheduled_at !== null
                && ! \Illuminate\Support\Carbon::parse($this->scheduled_at)->isPast(),
        );
    }

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
