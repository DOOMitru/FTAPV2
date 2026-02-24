<?php

namespace Database\Factories;

use App\Models\PointsStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

class PointsStructureFactory extends Factory
{
    protected $model = PointsStructure::class;

    public function definition(): array
    {
        return [
            'place'  => $this->faker->unique()->numberBetween(1, 100),
            'points' => $this->faker->numberBetween(10, 1000),
        ];
    }
}
