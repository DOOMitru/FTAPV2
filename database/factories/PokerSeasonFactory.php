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

    /**
     * A season whose finale rules have been published.
     *
     * The defaults are deliberately round rather than realistic: a fixture that
     * looks like real data invites a reader to trust the numbers, and these are
     * only here so a test has thresholds to measure against.
     */
    public function withThresholds(int $points = 300, int $wins = 2, int $venuePoints = 50): static
    {
        return $this->state(fn () => [
            'finale_points_required' => $points,
            'finale_wins_required' => $wins,
            'finale_venue_points_required' => $venuePoints,
        ]);
    }
}
