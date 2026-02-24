<?php

namespace Database\Factories;

use App\Models\PokerTournamentResult;
use App\Models\PokerTournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PokerTournamentResultFactory extends Factory
{
    protected $model = PokerTournamentResult::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        return [
            'place'           => $this->faker->numberBetween(1, 10),
            'points'          => $this->faker->numberBetween(10, 500),
            'user_id'         => $user->id,
            'player_name'     => $user->first_name . ' ' . $user->last_name,
            'player_nickname' => $user->nickname,
            'tournament_id'   => PokerTournament::factory(),
        ];
    }
}
