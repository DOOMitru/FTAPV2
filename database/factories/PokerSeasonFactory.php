<?php

namespace Database\Factories;

use App\Models\PokerSeason;
use Illuminate\Database\Eloquent\Factories\Factory;

class PokerSeasonFactory extends Factory
{
    protected $model = PokerSeason::class;

    public function definition(): array
    {
        return [
            'name'        => 'Season ' . $this->faker->unique()->numberBetween(1, 100),
            'description' => $this->faker->sentence(),
            'start_date'  => $this->faker->date(),
            'end_date'    => $this->faker->date(),
            'is_current'  => false,
        ];
    }
}
