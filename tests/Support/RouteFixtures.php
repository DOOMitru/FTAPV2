<?php

namespace Tests\Support;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;

/**
 * One record of every kind a GET route can ask for, and the map from a route
 * parameter to a concrete id.
 *
 * Extracted from RouteSmokeTest, which is still the guard: it hard-fails on a
 * route parameter with no fixture rather than skipping the route, so a new
 * `{thing}` in a URI is reported by the suite instead of quietly going
 * unvisited.
 *
 * Shared because bin/sweep.sh needs exactly the same thing for a different
 * purpose -- the suite asks whether a route responds, the sweep asks whether
 * the page it returns is right in a browser -- and two fixture maps would be a
 * second definition free to drift. That is the defect this project keeps
 * meeting: PokerSeason::current(), VenuePoints::readableBy() and initials() all
 * exist because a second definition drifted or was about to.
 *
 * Plain class rather than a trait: the sweep's seed script is not a TestCase
 * and has no assertions to inherit. tests/ is on the dev autoloader, which is
 * what lets a bin/ script reach it.
 */
class RouteFixtures
{
    /**
     * Seed the fixtures and return the parameter map.
     *
     * @return array{admin: User, player: User, bindings: array<string, string>}
     */
    public static function seed(): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create(['is_admin' => false]);

        $venue = Venue::create([
            'name' => 'The Grand Card Room',
            'address' => '100 Casino Blvd',
            'description' => 'A premier poker venue.',
        ]);

        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $tournament = PokerTournament::create([
            'name' => 'Weekly Freezeout',
            'start_time' => now()->addDays(7)->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $structure = PointsStructure::create(['place' => 1, 'points' => 100]);

        $registrant = PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $admin->id,
            'player_name' => 'Admin User',
            'registered_at' => now(),
        ]);

        $result = PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $admin->id,
            'player_name' => 'Admin User',
            'place' => 1,
            'points' => 100,
        ]);

        $venuePoints = VenuePoints::create([
            'venue_id' => $venue->id,
            'user_id' => $admin->id,
            'user_name' => 'Admin User',
            'event_date' => now()->toDateString(),
            'amount' => 50,
        ]);

        /*
         * The logo path is NAMED for this fixture, not for a test.
         *
         * bin/sweep.sh has to put a real file behind it -- the storage route
         * answers 403 otherwise and every page drawing a sponsor reports a
         * console error -- and it writes into the project's own public disk,
         * because that is where the server it starts will look. So the name
         * has to be unmistakably this fixture's, or the sweep's cleanup would
         * be deleting a path a real sponsor might use.
         */
        $sponsor = Sponsor::create([
            'name' => 'Smoke Test Sponsor',
            'logo_path' => 'sponsor-logos/route-fixture.png',
        ]);

        return [
            'admin' => $admin,
            'player' => $player,
            'bindings' => [
                'user' => $admin->id,
                'player' => $admin->id,
                'venue' => $venue->id,
                'season' => $season->id,
                'tournament' => $tournament->id,
                'points_structure' => $structure->id,
                'registrant' => $registrant->id,
                'result' => $result->id,
                'venue_point' => $venuePoints->id,
                'sponsor' => $sponsor->id,
                'token' => 'smoke-test-token',
                'id' => $admin->id,
                'hash' => sha1($admin->email),
            ],
        ];
    }
}
