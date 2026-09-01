<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\PokerSeason;
use App\Models\Venue;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\PointsStructure;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ─── 1. Points Structure ──────────────────────────────────────────────
        $pointsMap = [
            1 => 100, 2 => 85, 3 => 75, 4 => 65, 5 => 55,
            6 => 47, 7 => 40, 8 => 34, 9 => 29, 10 => 24,
        ];
        foreach ($pointsMap as $place => $points) {
            PointsStructure::create(['place' => $place, 'points' => $points]);
        }

        // ─── 2. Admin User ────────────────────────────────────────────────────
        $admin = User::create([
            'first_name'        => 'Admin',
            'last_name'         => 'User',
            'nickname'          => 'The Boss',
            'email'             => 'admin@example.com',
            'password'          => Hash::make('password'),
            'is_admin'          => true,
            'email_verified_at' => now(),
        ]);

        // ─── 3. Regular Users (99 more) ───────────────────────────────────────
        $users = User::factory(99)->create();
        $users = $users->push($admin);

        // ─── 4. Venues ────────────────────────────────────────────────────────
        $venueData = [
            ['name' => 'The Grand Card Room',       'address' => '100 Casino Blvd, Las Vegas, NV 89101, USA'],
            ['name' => 'Royal Flush Casino',         'address' => '250 Royal Ave, Atlantic City, NJ 08401, USA'],
            ['name' => 'Ace of Spades Lounge',       'address' => '77 Poker Lane, Chicago, IL 60601, USA'],
            ['name' => 'The Poker Palace',           'address' => '3300 Main St, Houston, TX 77002, USA'],
            ['name' => 'Diamond Club',               'address' => '900 Diamond Dr, Miami, FL 33101, USA'],
        ];
        $venues = collect($venueData)->map(fn($v) => Venue::create(array_merge($v, ['description' => 'A premier poker venue known for its competitive atmosphere.'])));

        // ─── 5. Seasons (past 3, current 1, future 1) ───────────────────────
        $seasonDefs = [
            ['name' => 'Season 1', 'start_date' => '2023-01-01', 'end_date' => '2023-12-31'],
            ['name' => 'Season 2', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31'],
            ['name' => 'Season 3', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31'],
            ['name' => 'Season 4', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
            ['name' => 'Season 5', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31'],
        ];

        foreach ($seasonDefs as $sd) {
            $isCurrent = ($sd['name'] === 'Season 4');
            $season = PokerSeason::create(array_merge($sd, [
                'description' => 'Official league season for competitive poker.',
                'is_current' => $isCurrent,
            ]));

            // ─── 6. Tournaments per Season ────────────────────────────────────
            $numTournaments = rand(10, 20);
            $seasonStart = Carbon::parse($sd['start_date']);
            $seasonEnd   = Carbon::parse($sd['end_date']);

            // Distribute tournaments evenly across the season
            $interval = (int) ($seasonStart->diffInDays($seasonEnd) / $numTournaments);

            $tournamentNames = [
                'Opening Classic', 'Winter Showdown', 'Spring Challenge', 'Invitational Open',
                'High Stakes Masters', 'Mid-Season Battle', 'Grand Prix', 'Summer Cup',
                'Autumn Series', 'The Main Event', 'Elite Faceoff', 'Championship Night',
                'Ultimate Showdown', 'Player Championship', 'Premier League Night',
                'The Big Blind Special', 'River Run Classic', 'Final Table Frenzy',
                'Last Man Standing', 'Season Closer',
            ];

            for ($t = 0; $t < $numTournaments; $t++) {
                $tournamentDate = $seasonStart->copy()->addDays($interval * $t + rand(0, max(1, $interval - 1)));
                if ($tournamentDate->gt($seasonEnd)) {
                    $tournamentDate = $seasonEnd->copy()->subDays(rand(1, 10));
                }

                $startHour = rand(18, 20);

                $tournament = PokerTournament::create([
                    'name'        => ($tournamentNames[$t % count($tournamentNames)]) . ' ' . $sd['name'],
                    'description' => null,
                    // One derived from the other, never two independent rolls.
                    // rand(17, 19) called TWICE produced unrelated hours in the
                    // same window, so seeded tournaments closed registration up
                    // to two hours AFTER play began -- data the app's own
                    // validation rejects (start_time is after_or_equal
                    // scheduled_at on both store and update).
                    'scheduled_at'=> $tournamentDate->copy()->setTime($startHour - 1, 0),
                    'start_time'  => $tournamentDate->copy()->setTime($startHour, 0),
                    'season_id'   => $season->id,
                    'venue_id'    => $venues->random()->id,
                ]);

                // Only create registrants/results for past tournaments
                if ($tournamentDate->isPast()) {
                    // Pick 12-20 random users for this tournament
                    $participants = $users->random(rand(12, min(20, $users->count())))->values();

                    // Register them all
                    foreach ($participants as $user) {
                        PokerTournamentRegistrant::create([
                            'user_id'          => $user->id,
                            'player_name'      => $user->first_name . ' ' . $user->last_name,
                            'player_nickname'  => $user->nickname,
                            'registered_at'    => $tournamentDate->copy()->subDays(rand(1, 14)),
                            'tournament_id'    => $tournament->id,
                        ]);
                    }

                    // Assign results with points from point structure
                    foreach ($participants as $position => $user) {
                        $place  = $position + 1;
                        $points = $pointsMap[$place] ?? max(1, 20 - $place);

                        PokerTournamentResult::create([
                            'place'           => $place,
                            'points'          => $points,
                            'user_id'         => $user->id,
                            'player_name'     => $user->first_name . ' ' . $user->last_name,
                            'player_nickname' => $user->nickname,
                            'tournament_id'   => $tournament->id,
                        ]);
                    }
                } elseif ($tournamentDate->isToday() || $tournamentDate->isFuture()) {
                    // For future tournaments, only register some users (no results yet)
                    $participants = $users->random(rand(6, 15))->values();
                    foreach ($participants as $user) {
                        PokerTournamentRegistrant::create([
                            'user_id'          => $user->id,
                            'player_name'      => $user->first_name . ' ' . $user->last_name,
                            'player_nickname'  => $user->nickname,
                            'registered_at'    => now()->subDays(rand(1, 10)),
                            'tournament_id'    => $tournament->id,
                        ]);
                    }
                }
            }
        }
    }
}
