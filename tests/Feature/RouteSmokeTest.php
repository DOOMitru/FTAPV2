<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\RouteFixtures;
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

        /*
         * One record of each kind, and the parameter map, from
         * Tests\Support\RouteFixtures.
         *
         * Shared with bin/sweep.sh, which needs exactly the same fixtures for
         * a different question -- this asks whether a route responds, the
         * sweep asks whether the page it returns is right in a browser. Two
         * maps would be a second definition free to drift, and the unbound
         * check below is what makes this one authoritative: a new {thing} in a
         * URI fails the suite here rather than going quietly unvisited there.
         */
        ['admin' => $this->admin, 'player' => $this->player, 'bindings' => $this->bindings]
            = RouteFixtures::seed();
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
