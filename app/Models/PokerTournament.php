<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
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

    /**
     * The podium, but only the places that are actually settled.
     *
     * Places are handed out from the bottom of the field up, so the lowest
     * place numbers on record are NOT the podium until the field has shrunk to
     * meet them. With eight players of ten out, the best finish recorded is
     * third; first and second are still being played for, and showing the
     * current top three would put two players on a podium nobody has won.
     *
     * Third is settled once two players are left, which is the moment it was
     * awarded. First and second appear together and only once everyone has a
     * result: second is technically known when one player remains, but a
     * silver medal beside an empty gold one reads as a rendering fault.
     */
    public function podium(): Collection
    {
        $remaining = max(0, $this->countOf('registrants') - $this->countOf('results'));

        $settled = match (true) {
            $remaining === 0 => [1, 2, 3],
            $remaining <= 2 => [3],
            default => [],
        };

        if ($settled === []) {
            return collect();
        }

        return $this->results->whereIn('place', $settled)->sortBy('place')->values();
    }

    /**
     * Count a relation from whatever the caller already has: a loaded
     * relation, then a withCount alias, and only then a query of its own. The
     * events archive draws a podium per tournament, so a query here would be a
     * query per card.
     */
    /**
     * Has anyone been given a finish here yet?
     *
     * The gate on removing a registrant. A place is a position in a field, so
     * taking a player out of the field after finishes are recorded leaves every
     * one of those finishes describing a tournament that no longer exists --
     * tenth of ten, in a field of nine. Registering someone late is the
     * opposite case and is handled: the shift hook moves the recorded places
     * down to match. There is no matching way back, because removing a player
     * is ambiguous in a way that adding one is not -- did they never play, or
     * did they play and their result should go too?
     *
     * So the answer is that they stay. This is asked in two places, and lives
     * here so the two cannot come to different conclusions.
     */
    public function hasRecordedResults(): bool
    {
        return $this->countOf('results') > 0;
    }

    private function countOf(string $relation): int
    {
        if ($this->relationLoaded($relation)) {
            return $this->getRelation($relation)->count();
        }

        return $this->{$relation.'_count'} ?? $this->{$relation}()->count();
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
