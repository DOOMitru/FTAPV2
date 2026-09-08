<?php

namespace App\Models;

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
        'start_time',
        'venue_id',
        'season_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'published_at' => 'datetime',
    ];

    // published_at is deliberately absent from $fillable. Publishing sends
    // messages to players and locks the record; it is not something the
    // tournament edit form should be able to do by posting a field.

    /** Results have been declared final, and the tournament is locked. */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Every player who entered has a finish, and somebody entered.
     *
     * Comparing counts would be wrong twice over. An empty tournament has zero
     * of each and would read as finished, and the admin results form validates
     * that a user EXISTS rather than that they registered -- so a result for
     * somebody who never played can make the numbers match while a registrant
     * is still unscored.
     */
    public function isComplete(): bool
    {
        $entered = $this->registrants()->whereNotNull('user_id')->pluck('user_id')->unique();

        if ($entered->isEmpty()) {
            return false;
        }

        $scored = $this->results()->whereIn('user_id', $entered)->distinct()->count('user_id');

        return $scored === $entered->count();
    }

    /**
     * Why this tournament cannot be changed, or null if it can.
     *
     * One method for six call sites -- register, unregister, eliminate, remove
     * registrant, and result create/update/delete -- because six copies of a
     * rule are six chances for it to drift, which is the same reasoning that
     * put hasRecordedResults() here.
     */
    public function publishedRefusal(): ?string
    {
        if (! $this->isPublished()) {
            return null;
        }

        return __('Results for :tournament have been published. Unpublish them first to make changes.', [
            'tournament' => $this->name,
        ]);
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
