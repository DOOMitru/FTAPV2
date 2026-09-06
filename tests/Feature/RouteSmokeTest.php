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

    private User $player;

    /** @var array<string, string> route parameter name => concrete value */
    private array $bindings = [];

    /**
     * URIs that cannot be smoke-tested by a plain GET.
     */
    private const SKIPPED = [
        'up',                       // health check, no view
        'storage/{path}',           // static file serving
    ];

    /**
     * Literal Blade syntax that should never survive rendering. If any of
     * these strings appear in a successful HTML response body, a directive
     * leaked into the output instead of executing — a classic bug during a
     * view rewrite that a "no 5xx" check alone cannot catch.
     */
    private const BLADE_ARTIFACTS = [
        '@if',
        '@else',
        '@endif',
        '@foreach',
        '@endforeach',
        '@forelse',
        '@empty',
        '@endforelse',
        '@php',
        '@endphp',
        '@csrf',
        '@include',
        '@props',
        '@auth',
        '@endauth',
        '@error',
        '@enderror',
        '@method',
        '@isset',
        '{{',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->player = User::factory()->create(['is_admin' => false]);

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

        $sponsor = \App\Models\Sponsor::create([
            'name' => 'Smoke Test Sponsor',
            'logo_path' => 'sponsor-logos/smoke.png',
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
            'sponsor' => $sponsor->id,
            'token' => 'smoke-test-token',
            'id' => $this->admin->id,
            'hash' => sha1($this->admin->email),
        ];
    }

    public function test_every_get_route_responds_to_an_admin_without_a_server_error()
    {
        $this->assertNoServerErrorsOrBladeArtifacts(fn (string $uri) => $this->actingAs($this->admin)->get($uri));
    }

    public function test_every_get_route_responds_to_a_guest_without_a_server_error()
    {
        $this->assertNoServerErrorsOrBladeArtifacts(fn (string $uri) => $this->get($uri));
    }

    /**
     * The role the upcoming view rewrite most affects: a logged-in player
     * with no admin rights. Many /poker and /users routes are expected to
     * 403 for this role — that is correct behaviour and is not a failure,
     * since 403 is well below the "no 5xx" threshold.
     */
    public function test_every_get_route_responds_to_a_non_admin_player_without_a_server_error()
    {
        $this->assertNoServerErrorsOrBladeArtifacts(fn (string $uri) => $this->actingAs($this->player)->get($uri));
    }

    /**
     * Requests every registered GET route and asserts two things: the
     * response is never a server error, and a successful HTML response
     * never contains literal, unrendered Blade syntax (a directive that
     * leaked into the output instead of executing).
     */
    private function assertNoServerErrorsOrBladeArtifacts(callable $request): void
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

            $response = $request('/'.ltrim($resolved, '/'));
            $status = $response->getStatusCode();

            if ($status >= 500) {
                $failures[] = sprintf('%s — HTTP %d', $uri, $status);

                continue;
            }

            // Only successful HTML responses are worth scanning for leaked
            // Blade syntax — a redirect body or an error page isn't real
            // rendered view output.
            if ($status >= 200 && $status < 300
                && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                $body = $response->getContent();

                foreach (self::BLADE_ARTIFACTS as $artifact) {
                    if (str_contains($body, $artifact)) {
                        $failures[] = sprintf(
                            '%s — literal Blade artifact "%s" found in the rendered response body',
                            $uri,
                            $artifact
                        );
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $failures,
            "Routes failed the smoke test:\n  ".implode("\n  ", $failures)
        );
    }
}
