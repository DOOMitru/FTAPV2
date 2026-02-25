<?php

namespace Database\Factories;

use App\Models\PokerTournament;
use App\Models\PokerSeason;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

class PokerTournamentFactory extends Factory
{
    protected $model = PokerTournament::class;

    private static array $adjectives = ['High Stakes', 'Championship', 'Invitational', 'Classic', 'Open', 'Masters', 'Elite', 'Grand', 'Premier', 'Ultimate'];
    private static array $nouns = ['Showdown', 'Battle', 'Challenge', 'Tournament', 'Series', 'Clash', 'Faceoff', 'Championship', 'Cup', 'Event'];

    public function definition(): array
    {
        $adjective = $this->faker->randomElement(self::$adjectives);
        $noun = $this->faker->randomElement(self::$nouns);

        return [
            'name'        => "The {$adjective} {$noun}",
            'description' => $this->faker->paragraph(),
            'scheduled_at' => null, // set by seeder
            'start_time'  => null, // set by seeder
            'season_id'   => PokerSeason::factory(),
            'venue_id'    => Venue::factory(),
        ];
    }
}
