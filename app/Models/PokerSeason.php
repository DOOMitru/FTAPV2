<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

class PokerSeason extends Model
{
    /** @use HasFactory<\Database\Factories\PokerSeasonFactory> */
    use HasFactory, HasUlids;

    protected $table = 'seasons';

    /**
     * The league's current season, and the only answer to that question.
     *
     * The flag, not the dates. Both were in use: the home page asked which
     * season's range contained today, while the dashboard, the tournament
     * forms and the points structure read is_current. Two definitions of one
     * word disagree the moment a season ends without anyone moving the flag, or
     * a flag is set on a season that has not started -- and then which season a
     * player was told about depended on which page they happened to open.
     *
     * The flag wins because it is a decision somebody makes and can see, where a
     * date range is a rule that quietly re-answers itself as time passes. Only
     * one season can carry it; the model enforces that on save.
     *
     * Null when nothing is flagged. A league between seasons genuinely has no
     * current one, and falling back to the most recent -- which the home page
     * used to do -- is the same guess this method exists to remove.
     */
    public static function current(): ?self
    {
        return static::where('is_current', true)->first();
    }

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

    /**
     * Every ranked player in this season, best first.
     *
     * The one definition of what a season rank IS: points scored divided by
     * tournaments ENTERED. A player who turns up to half the nights and scores
     * what somebody scored across all of them is not behind them, which a
     * straight points total says they are.
     *
     * Lives here rather than in a controller because two pages read it -- the
     * dashboard, for the viewer's own position, and the landing page, for the
     * top three -- and a rank that meant one thing on one and something else on
     * the other would be worse than no rank at all. Same reasoning as the
     * comment on the home route about points and wins.
     *
     * Two grouped queries rather than a join: results and registrations answer
     * different questions -- what you scored, and how often you turned up --
     * and a player can have either without the other.
     *
     * Queried from the tables rather than through $this->results(), which is a
     * HasManyThrough. That relation silently adds `tournaments`.`season_id` as
     * `laravel_through_key` to the SELECT so it can match rows back to their
     * parent. Harmless normally; fatal beside a GROUP BY, because MySQL's
     * ONLY_FULL_GROUP_BY -- on by default since 8.0 -- rejects a selected
     * column that is neither grouped nor aggregated. SQLite does not enforce
     * that rule, so such a query runs locally for months and fails on the first
     * request against production's driver.
     *
     * @return Collection<int, array{user_id: string, name: string, ratio: float, points: int, events: int}>
     */
    public function rankings(): Collection
    {
        $inSeason = fn ($query) => $query->where('season_id', $this->id);

        $points = PokerTournamentResult::query()
            ->whereHas('tournament', $inSeason)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, SUM(points) as total_points')
            ->groupBy('user_id')
            ->pluck('total_points', 'user_id');

        $entries = PokerTournamentRegistrant::query()
            ->whereHas('tournament', $inSeason)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as entries')
            ->groupBy('user_id')
            ->pluck('entries', 'user_id');

        // The name as it was recorded on the night, which is what every other
        // standings list on the site shows -- a player who has since changed
        // their account name still appears under the name they played as.
        $names = PokerTournamentRegistrant::query()
            ->whereHas('tournament', $inSeason)
            ->whereNotNull('user_id')
            ->pluck('player_name', 'user_id');

        // Entering is what puts you on the board. A player with a result but no
        // registration -- which the results screen can create -- has no
        // denominator, and dividing by nothing is not a rank. They are absent
        // from $entries entirely, which is what leaves them unranked.
        //
        // The filter cannot fire: a GROUP BY ... COUNT(*) never returns a zero.
        // It is the guard on the division rather than on the data.
        return $entries
            ->filter(fn ($count) => (int) $count > 0)
            ->map(fn ($count, $id) => [
                'user_id' => $id,
                'name' => $names[$id] ?? '',
                'ratio' => (float) (int) ($points[$id] ?? 0) / (int) $count,
                // The tie-break, so two players on the same average are ordered
                // by who scored more rather than by whatever the database
                // happened to return first.
                'points' => (int) ($points[$id] ?? 0),
                'events' => (int) $count,
            ])
            ->sortByDesc(fn (array $row) => [$row['ratio'], $row['points']])
            ->values();
    }

    public function registrants(): HasManyThrough
    {
        return $this->hasManyThrough(PokerTournamentRegistrant::class, PokerTournament::class, 'season_id', 'tournament_id');
    }
}
