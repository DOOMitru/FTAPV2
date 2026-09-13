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

    /**
     * Who may read one player's venue tally.
     *
     * The league's rule, in one place: the player it belongs to, and the
     * admins who award it. Nobody else, on any page, signed in or not.
     *
     * It lives on the model rather than in each controller because the rule is
     * the league's, not any one screen's -- a second copy of it somewhere else
     * is a copy that can drift. Every surface that puts a tally in front of
     * somebody asks this, or sits behind the admin middleware.
     *
     * The finale THRESHOLD is deliberately NOT covered. It is a published
     * target rather than anybody's tally, and it is printed on the landing
     * page for people who have not signed in at all.
     *
     * @param  ?User  $viewer  the signed-in user, or null for a guest
     * @param  ?string  $ownerId  the id of the player the tally belongs to
     */
    public static function readableBy(?User $viewer, ?string $ownerId): bool
    {
        if ($viewer === null) {
            return false;
        }

        if ($viewer->is_admin) {
            return true;
        }

        // Both sides must have an account. A tally with no owner belongs to
        // nobody, and null === null would otherwise hand it to everyone.
        return $ownerId !== null && $ownerId === $viewer->id;
    }
}
