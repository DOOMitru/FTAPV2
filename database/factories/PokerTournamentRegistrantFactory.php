<?php

namespace Database\Factories;

use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PokerTournamentRegistrantFactory extends Factory
{
    protected $model = PokerTournamentRegistrant::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        return [
            'user_id'         => $user->id,
            'player_name'     => $user->first_name . ' ' . $user->last_name,
            'player_nickname' => $user->nickname,
            'registered_at'   => $this->faker->dateTimeBetween('-1 month', 'now'),
            'tournament_id'   => PokerTournament::factory(),
        ];
    }
}
