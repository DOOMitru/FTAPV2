<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    /** @use HasFactory<\Database\Factories\VenueFactory> */
    use HasFactory, HasUlids;

    protected $table = 'venues';

    protected $fillable = [
        'name',
        'description',
        'address',
    ];

    public function tournaments(): HasMany
    {
        return $this->hasMany(PokerTournament::class, 'venue_id');
    }

    public function venuePoints(): HasMany
    {
        return $this->hasMany(VenuePoints::class, 'venue_id');
    }
}
