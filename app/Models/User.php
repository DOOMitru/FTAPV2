<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasUlids, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
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
