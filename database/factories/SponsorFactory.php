<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Sponsor> */
class SponsorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'logo_path' => 'sponsor-logos/placeholder.png',
            'website_url' => null,
            'tier' => 'regular',
            'sort_order' => 0,
        ];
    }

    public function premium(): static
    {
        return $this->state(fn () => ['tier' => 'premium']);
    }
}
