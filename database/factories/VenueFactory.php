<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

class VenueFactory extends Factory
{
    protected $model = Venue::class;

    private static array $venueNames = [
        'The Grand Card Room',
        'Royal Flush Casino',
        'Ace of Spades Lounge',
        'The Poker Palace',
        'Diamond Club',
        'Full House Bar & Grill',
        'The River Room',
        'Stack & Rake Social Club',
        'The High Roller Lounge',
        'Blind Raise Tavern',
    ];

    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->randomElement(self::$venueNames),
            'description' => $this->faker->sentence(),
            'address'     => $this->faker->streetAddress() . ', ' . $this->faker->city() . ', ' . $this->faker->stateAbbr() . ' ' . $this->faker->postcode() . ', USA',
        ];
    }
}
