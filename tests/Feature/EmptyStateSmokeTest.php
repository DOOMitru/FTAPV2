<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * RouteSmokeTest seeds one of every model, so its sweep never once
 * executes an @empty branch. This test fills that gap: it sweeps the
 * app's index/listing pages against a genuinely empty database (Pass A),
 * then sweeps the parameterised "show" pages with a single bare parent
 * record that has no children at all (Pass B). Together these exercise
 * the 18 @forelse/@empty blocks (36 @forelse/@empty directive
 * occurrences) spread across 13 views that the upcoming design-system
 * rewrite will touch.
 *
 * Shares RouteSmokeTest's 5xx and literal/unrendered-Blade-syntax
 * checks, but NOT its hard-fail guarantee: RouteSmokeTest hard-fails if
 * a parameterised route has no fixture bound for it, so a new route can
 * never slip through unswept. This file has no equivalent — Pass A
 * silently skips (`continue`s) every URI containing a `{parameter}`
 * segment, and Pass B covers only a small hardcoded list of specific
 * parameterised URIs (one per model "show" page), not a dynamic
 * enumeration. A newly added parameterised page with its own
 * empty-state branches gets zero coverage here and no warning.
 */
class EmptyStateSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * URIs that cannot be smoke-tested by a plain GET.
     */
    private const SKIPPED = [
        'up',                       // health check, no view
        'storage/{path}',           // static file serving
    ];

    /**
     * Literal Blade syntax that should never survive rendering.
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

    // -----------------------------------------------------------------
    // Pass A: every parameterless GET route, against a wholly empty DB.
    // -----------------------------------------------------------------

    /**
     * Covers (as admin, with only the admin user created — no other
     * rows in the database at all): poker/seasons, poker/tournaments,
     * poker/venues, poker/results, poker/registrants, poker/venue-points,
     * and poker/points-structure index pages, plus every other
     * parameterless admin/auth route (dashboard, profile, users, etc).
     */
    public function test_parameterless_routes_render_for_admin_with_an_empty_database(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertNoServerErrorsOrBladeArtifacts(
            $this->parameterlessGetUris(),
            fn (string $uri) => $this->actingAs($admin)->get($uri)
        );
    }

    /**
     * Covers the public parameterless pages — home, about, events,
     * rules/points-structure, contact, login, register, etc — as a
     * guest, with a completely empty database (not even an admin user).
     */
    public function test_parameterless_routes_render_for_a_guest_with_an_empty_database(): void
    {
        $this->assertNoServerErrorsOrBladeArtifacts(
            $this->parameterlessGetUris(),
            fn (string $uri) => $this->get($uri)
        );
    }

    // -----------------------------------------------------------------
    // Pass B: bare parent fixtures with zero children, one route each.
    // -----------------------------------------------------------------

    /**
     * A PokerSeason with no tournaments and therefore no results — the
     * 3 @forelse/@empty blocks (6 directive occurrences) in
     * poker/seasons/show.blade.php (leaderboard, venue hostings, and
     * schedule sidebar).
     */
    public function test_season_show_renders_with_no_tournaments(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $season = PokerSeason::create([
            'name' => 'Empty Season',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $this->assertNoServerErrorsOrBladeArtifacts(
            ['seasons/'.$season->id],
            fn (string $uri) => $this->actingAs($admin)->get($uri)
        );
    }

    /**
     * A Venue with no tournaments and no venue points — the 2
     * @forelse/@empty blocks (4 directive occurrences) in
     * poker/venues/show.blade.php (leaderboard and tournament history).
     */
    public function test_venue_show_renders_with_no_tournaments_and_no_venue_points(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $venue = Venue::create([
            'name' => 'Empty Venue',
            'address' => '1 Nowhere Street',
            'description' => 'A venue with no history yet.',
        ]);

        $this->assertNoServerErrorsOrBladeArtifacts(
            ['poker/venues/'.$venue->id],
            fn (string $uri) => $this->actingAs($admin)->get($uri)
        );
    }

    /**
     * A PokerTournament with no registrants and no results.
     *
     * This used to cover two @forelse/@empty blocks -- Final Standings and
     * Registered Players -- and turned on the difference between them: the
     * standings block only rendered when $isPast was true, so a future-dated
     * fixture alone never reached its empty arm and the "no results" coverage
     * was incidental rather than real.
     *
     * The two panels are one panel now, so there is one empty state rather than
     * two, and it does not depend on $isPast. Both fixtures are kept even so:
     * the merged card renders different controls either side of the start time,
     * and an empty list is where an @empty arm and a missing $standings would
     * both surface.
     */
    public function test_tournament_show_renders_with_no_registrants_and_no_results(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Own season/venue so this fixture doesn't also seed the
        // "season/venue with no tournaments" scenarios above.
        $venue = Venue::create([
            'name' => 'Bare Tournament Venue',
            'address' => '2 Somewhere Ave',
        ]);

        $season = PokerSeason::create([
            'name' => 'Bare Tournament Season',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $futureTournament = PokerTournament::create([
            'name' => 'Untouched Tournament',
            'start_time' => now()->addDays(7)->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $pastTournament = PokerTournament::create([
            'name' => 'Concluded Untouched Tournament',
            'start_time' => now()->subWeeks(2)->addMinutes(30),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $this->assertNoServerErrorsOrBladeArtifacts(
            ['tournaments/'.$futureTournament->id, 'tournaments/'.$pastTournament->id],
            fn (string $uri) => $this->actingAs($admin)->get($uri)
        );

        foreach ([$futureTournament, $pastTournament] as $tournament) {
            $this->actingAs($admin)->get('tournaments/'.$tournament->id)
                ->assertSee('No players registered yet.')
                // Nothing has been played, so the panel does not claim to be
                // standings of anything. isComplete() is false on an empty
                // field, which is what keeps "Final" off an untouched past
                // tournament.
                ->assertSee('Registered Players')
                ->assertDontSee('Final Standings');
        }
    }

    /**
     * A dashboard for a user with no tournament results and no
     * tournament registrations — the 2 @forelse/@empty blocks (4
     * directive occurrences) in dashboard.blade.php (upcoming
     * tournaments and recent results).
     */
    public function test_dashboard_renders_for_user_with_no_results_and_no_registrations(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertNoServerErrorsOrBladeArtifacts(
            ['dashboard'],
            fn (string $uri) => $this->actingAs($admin)->get($uri)
        );
    }

    // -----------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------

    /**
     * Every registered GET route whose URI has no {parameter} segment.
     *
     * This does NOT mirror RouteSmokeTest's hard-fail guarantee. Any URI
     * containing a `{` is silently skipped (`continue`d, below) rather
     * than resolved or flagged — a parameterised route just never
     * appears in this list, with no warning. Parameterised "show" pages
     * get their empty-state coverage from the hardcoded, one-URI-per-
     * model-type Pass B tests instead, not from any enumeration here.
     */
    private function parameterlessGetUris(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (in_array($uri, self::SKIPPED, true) || str_starts_with($uri, '_')) {
                continue;
            }

            if (str_contains($uri, '{')) {
                continue;
            }

            $uris[] = '/'.ltrim($uri, '/');
        }

        return $uris;
    }

    /**
     * Requests every given URI and asserts two things: the response is
     * never a server error, and a successful HTML response never
     * contains literal, unrendered Blade syntax.
     */
    private function assertNoServerErrorsOrBladeArtifacts(array $uris, callable $request): void
    {
        $failures = [];

        foreach ($uris as $uri) {
            $response = $request($uri);
            $status = $response->getStatusCode();

            if ($status >= 500) {
                $failures[] = sprintf('%s — HTTP %d', $uri, $status);

                continue;
            }

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
            "Routes failed the empty-state smoke test:\n  ".implode("\n  ", $failures)
        );
    }
}
