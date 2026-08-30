<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Requests every registered GET route and asserts none returns a server
 * error. This is the guard for large-scale view rewrites.
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, string> route parameter name => concrete value */
    private array $bindings = [];

    /**
     * URIs that cannot be smoke-tested by a plain GET.
     */
    private const SKIPPED = [
        'up',                       // health check, no view
        'storage/{path}',           // static file serving
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);

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
            'scheduled_at' => now()->addDays(7),
            'start_time' => now()->addDays(7)->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $structure = PointsStructure::create(['place' => 1, 'points' => 100]);

        $registrant = PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->admin->id,
            'player_name' => 'Admin User',
            'registered_at' => now(),
        ]);

        $result = PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->admin->id,
            'player_name' => 'Admin User',
            'place' => 1,
            'points' => 100,
        ]);

        $venuePoints = VenuePoints::create([
            'venue_id' => $venue->id,
            'user_id' => $this->admin->id,
            'user_name' => 'Admin User',
            'event_date' => now()->toDateString(),
            'amount' => 50,
        ]);

        $this->bindings = [
            'user' => $this->admin->id,
            'venue' => $venue->id,
            'season' => $season->id,
            'tournament' => $tournament->id,
            'points_structure' => $structure->id,
            'registrant' => $registrant->id,
            'result' => $result->id,
            'venue_point' => $venuePoints->id,
            'token' => 'smoke-test-token',
            'id' => $this->admin->id,
            'hash' => sha1($this->admin->email),
        ];
    }

    public function test_every_get_route_responds_to_an_admin_without_a_server_error()
    {
        $this->assertNoServerErrors(fn (string $uri) => $this->actingAs($this->admin)->get($uri));
    }

    public function test_every_get_route_responds_to_a_guest_without_a_server_error()
    {
        $this->assertNoServerErrors(fn (string $uri) => $this->get($uri));
    }

    private function assertNoServerErrors(callable $request): void
    {
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (in_array($uri, self::SKIPPED, true) || str_starts_with($uri, '_')) {
                continue;
            }

            $unbound = [];

            $resolved = preg_replace_callback(
                '/\{(\w+)\??\}/',
                function (array $matches) use (&$unbound) {
                    if (! array_key_exists($matches[1], $this->bindings)) {
                        $unbound[] = $matches[1];

                        return $matches[0];
                    }

                    return $this->bindings[$matches[1]];
                },
                $uri
            );

            if ($unbound !== []) {
                $failures[] = sprintf(
                    '%s — no fixture for {%s}. Add it to $bindings in RouteSmokeTest.',
                    $uri,
                    implode('}, {', $unbound)
                );

                continue;
            }

            $status = $request('/'.ltrim($resolved, '/'))->getStatusCode();

            if ($status >= 500) {
                $failures[] = sprintf('%s — HTTP %d', $uri, $status);
            }
        }

        $this->assertSame(
            [],
            $failures,
            "Routes failed the smoke test:\n  ".implode("\n  ", $failures)
        );
    }
}
