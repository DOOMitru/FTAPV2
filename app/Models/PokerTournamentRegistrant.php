<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PokerTournamentRegistrant extends Model
{
    /** @use HasFactory<\Database\Factories\PokerTournamentRegistrantFactory> */
    use HasFactory, HasUlids;

    protected $table = 'tournament_registrants';

    protected $fillable = [
        'user_id',
        'player_name',
        'player_nickname',
        'registered_at',
        'tournament_id',
        'registered_by',
        'is_late_entry',
    ];

    protected $casts = [
        'is_late_entry' => 'boolean',
        'registered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(PokerTournament::class, 'tournament_id');
    }
}
