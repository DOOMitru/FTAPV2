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

    /**
     * The season a given date falls inside, if any.
     *
     * Ordered by start date so that overlapping seasons -- which nothing
     * prevents -- resolve to the earlier one every time rather than to whatever
     * the database happened to return first. The backfill migration orders the
     * same way, so a row assigned then and a row assigned now agree.
     */
    public static function covering(mixed $date): ?self
    {
        return static::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('start_date')
            ->first();
    }

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'is_current',
        'finale_points_required',
        'finale_wins_required',
        'finale_venue_points_required',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        // Cast so a null stays null and a value is an int. Without this a
        // threshold read back from SQLite is a numeric STRING, and '300' < 300
        // is false while '9' < 100 is also false -- string comparison would
        // pass some checks and fail others for no visible reason.
        'finale_points_required' => 'integer',
        'finale_wins_required' => 'integer',
        'finale_venue_points_required' => 'integer',
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

    /**
     * Whether this season publishes any qualification target at all.
     *
     * A season with none is not "everybody qualifies" -- it is a season whose
     * rules have not been set, and a screen must say so rather than showing a
     * tick against a rule nobody has written. Callers gate on this BEFORE
     * calling qualifies(), which answers vacuously yes when nothing is
     * published.
     */
    public function hasThresholds(): bool
    {
        return $this->finale_points_required !== null
            || $this->finale_wins_required !== null
            || $this->finale_venue_points_required !== null;
    }

    /**
     * The single definition of qualifying for the finale.
     *
     * Every screen calls this rather than comparing the columns, so the season
     * page and anything added later cannot disagree about who is in.
     *
     * All three must be met. A NULL threshold is not a barrier: a season may
     * publish a points target while the other two are still being decided.
     */
    public function qualifies(int $points, int $wins, int $venuePoints): bool
    {
        return $this->unmetBy($points, $wins, $venuePoints) === [];
    }

    /**
     * Which criteria a player falls short on, in a fixed order.
     *
     * Named rather than counted, so a screen can tell a player WHAT they are
     * short on. A bare cross says they failed without saying what to do.
     *
     * @return array<int, string> any of 'points', 'wins', 'venue_points'
     */
    public function unmetBy(int $points, int $wins, int $venuePoints): array
    {
        $unmet = [];

        // >=, not >: meeting the number exactly is meeting it.
        if ($this->finale_points_required !== null && $points < $this->finale_points_required) {
            $unmet[] = 'points';
        }

        if ($this->finale_wins_required !== null && $wins < $this->finale_wins_required) {
            $unmet[] = 'wins';
        }

        if ($this->finale_venue_points_required !== null && $venuePoints < $this->finale_venue_points_required) {
            $unmet[] = 'venue_points';
        }

        return $unmet;
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
