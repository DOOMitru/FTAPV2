<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Implements MustVerifyEmail, which is what activates the `verified` middleware
 * already guarding the dashboard route. Without it that middleware is a no-op
 * and the profile page's "resend verification" block is unreachable, so the app
 * looked protected while letting everyone through.
 *
 * Accounts created before this was switched on are grandfathered by the
 * 2026_09_01 migration; the requirement applies to new registrations only.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasUlids, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * The database defaults approval_status to 'pending', but a database
     * default is applied on INSERT and never hydrated back, so a freshly
     * created instance held in memory had a null status. isApproved() failed
     * closed on that, which is correct, but isPendingApproval() also returned
     * false -- an account that was in no state at all until it was reloaded.
     * Declaring it here makes the in-memory model agree with the row.
     */
    protected $attributes = [
        'approval_status' => 'pending',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'nickname',
        'is_admin',
        'profile_image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'approval_decided_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * The single expression of the approval rule.
     *
     * Every gate and every view calls this rather than comparing the column,
     * so the control that OFFERS an action and the guard that REFUSES it can
     * never disagree about what approved means. A player seeing a button that
     * then fails is worse than a player seeing no button at all.
     *
     * Note that approval is not email verification. They answer different
     * questions -- "should this person play?" and "is this address real?" --
     * and a player can be blocked by either, or both.
     */
    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeAwaitingApproval($query)
    {
        return $query->where('approval_status', 'pending');
    }

    /**
     * Get the user's nickname or first name.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!empty($this->nickname)) {
                    return $this->nickname;
                }

                return $this->first_name;
            }
        );
    }

    /**
     * Get the user's profile image URL.
     */
    protected function profileImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->profile_image) {
                    return asset('storage/' . $this->profile_image);
                }

                return asset('images/default_profile.png');
            }
        );
    }

    public function tournamentResults(): HasMany
    {
        return $this->hasMany(PokerTournamentResult::class, 'user_id');
    }

    public function tournamentRegistrations(): HasMany
    {
        return $this->hasMany(PokerTournamentRegistrant::class, 'user_id');
    }

    public function registrationsPerformed(): HasMany
    {
        return $this->hasMany(PokerTournamentRegistrant::class, 'registered_by');
    }

    public function venuePoints(): HasMany
    {
        return $this->hasMany(VenuePoints::class, 'user_id');
    }
}
