<?php

namespace Database\Factories;

use App\Models\VenuePoints;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

class VenuePointsFactory extends Factory
{
    protected $model = VenuePoints::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        return [
            'event_date' => $this->faker->date(),
            'amount'     => $this->faker->numberBetween(5, 100),
            'user_id'    => $user->id,
            'user_name'  => $user->first_name . ' ' . $user->last_name,
            'venue_id'   => Venue::factory(),
        ];
    }
}
