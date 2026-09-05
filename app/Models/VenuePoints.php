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

    /**
     * Integer columns, cast so they arrive as integers.
     *
     * Not cosmetic and not test-only. PDO's MySQL driver returns every column
     * as a STRING by default, where SQLite returns typed values -- so without
     * this the same row is 5 in development and "5" in production, and every
     * identity comparison and int-typed parameter behaves differently on the
     * two. The casts make the model the authority on its own types rather than
     * the driver.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            // event_date is deliberately NOT cast: it is stored and read
            // as a plain Y-m-d string, the index view parses it with
            // Carbon, and a test asserts that exact string comes back.
        ];
    }

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
