<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenuePoints extends Model
{
    /** @use HasFactory<\Database\Factories\VenuePointsFactory> */
    use HasFactory, HasUlids;

    protected $table = 'venue_points';

    protected $fillable = [
        'event_date',
        'amount',
        'user_id',
        'user_name',
        'venue_id',
        // Stored, not inferred. The season used to be whichever one's dates
        // happened to contain event_date, so editing those dates moved venue
        // points between seasons and changed who qualified for the finale --
        // silently, because a coincidence of two numbers cannot report that it
        // has changed its mind.
        'season_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(PokerSeason::class, 'season_id');
    }
}
