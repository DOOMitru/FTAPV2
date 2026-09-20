<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A page's Props interface describes what its controller actually sends.
 *
 * The props are built in PHP and consumed in TypeScript, so the interface is a
 * second definition of one thing and free to drift. TypeScript cannot catch it:
 * it checks the Vue side is internally consistent and has no idea what the
 * server sends. Nor can the type checker, now that PageProps carries an index
 * signature to satisfy Inertia's own constraint -- an undeclared prop is simply
 * `unknown`, not an error.
 *
 * Deliberately shallow: top-level keys only. It catches the common failure -- a
 * prop renamed, added or dropped -- without reimplementing a type checker in
 * PHP, which would be a third definition of the same thing.
 *
 * Pages are LISTED here rather than discovered. Unlike ComponentParityTest,
 * there is nothing on disk tying a route to a component: the link is made
 * inside a controller method by a string passed to Inertia::render(), and
 * finding it would mean parsing PHP. The list is checked for staleness instead
 * -- an entry naming a route or a component that no longer exists fails.
 */
class InertiaPropContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Inertia component => [route name, the name of its route parameter].
     *
     * The second element is null for a page that takes none. Until the forms
     * arrived every converted page was parameterless and this was a plain
     * name => route map; an edit route needs a record, and route('x.edit')
     * with no argument THROWS rather than failing an assertion, which reads as
     * a broken test rather than a missing fixture.
     */
    private const PAGES = [
        'Profile/Edit' => ['profile.edit', null],
        'Poker/Seasons/Index' => ['poker.seasons.index', null],
        'Poker/Venues/Index' => ['poker.venues.index', null],
        'Poker/Tournaments/Index' => ['poker.tournaments.index', null],
        'Poker/PointsStructure/Index' => ['poker.points-structure.index', null],
        'Sponsors/Index' => ['sponsors.index', null],
        'Users/Index' => ['users.index', null],
        'Poker/Venues/Create' => ['poker.venues.create', null],
        'Poker/Venues/Edit' => ['poker.venues.edit', 'venue'],
        'Poker/Seasons/Create' => ['poker.seasons.create', null],
        'Poker/Seasons/Edit' => ['poker.seasons.edit', 'season'],
        'Poker/Tournaments/Create' => ['poker.tournaments.create', null],
        'Poker/Tournaments/Edit' => ['poker.tournaments.edit', 'tournament'],
        'Poker/PointsStructure/Create' => ['poker.points-structure.create', null],
        'Poker/PointsStructure/Edit' => ['poker.points-structure.edit', 'points_structure'],
        'Users/Create' => ['users.create', null],
        'Users/Edit' => ['users.edit', 'user'],
        'Sponsors/Create' => ['sponsors.create', null],
        'Sponsors/Edit' => ['sponsors.edit', 'sponsor'],
    ];

    /**
     * A record for a parameterised page to be rendered against.
     *
     * Made inside the test rather than listed as an id: RefreshDatabase gives
     * each test an empty database. An unhandled binding raises
     * \UnhandledMatchError, which names the missing arm -- better than a null
     * id producing a 404 that reads as a routing problem.
     */
    private function fixture(string $binding): string
    {
        return match ($binding) {
            'venue' => \App\Models\Venue::create([
                'name' => 'Fixture Room', 'address' => '1 Test St',
            ])->id,

            // Both dates, because seasons.start_date and end_date are NOT
            // NULL -- an earlier test here was written for a season with
            // neither and could not be inserted at all.
            'season' => \App\Models\PokerSeason::create([
                'name' => 'Fixture Season',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ])->id,

            'tournament' => \App\Models\PokerTournament::create([
                'name' => 'Fixture Night',
                'start_time' => '2026-06-01 19:00:00',
                'season_id' => \App\Models\PokerSeason::create([
                    'name' => 'Fixture Season For A Tournament',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                ])->id,
                'venue_id' => \App\Models\Venue::create(['name' => 'Fixture Hall'])->id,
            ])->id,

            'points_structure' => \App\Models\PointsStructure::create([
                'place' => 1, 'points' => 100,
            ])->id,

            // Not the acting admin: the page under test is somebody else's
            // account, and an admin editing their own is a different screen's
            // question.
            'user' => \App\Models\User::factory()->create([
                'email' => 'fixture.subject@example.test',
            ])->id,

            // logo_path is never null in practice -- store() requires the
            // upload -- so the fixture has one, and logoUrl() has something to
            // build a URL from.
            'sponsor' => \App\Models\Sponsor::create([
                'name' => 'Fixture Sponsor',
                'logo_path' => 'sponsor-logos/fixture.png',
                'tier' => 'regular',
                'sort_order' => 0,
            ])->id,
        };
    }

    /** The URL for a page, with its parameter resolved if it has one. */
    private function urlFor(array $page): string
    {
        [$routeName, $binding] = $page;

        return $binding === null
            ? route($routeName)
            : route($routeName, [$binding => $this->fixture($binding)]);
    }

    public function test_each_page_declares_exactly_the_props_it_is_sent(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
        $mismatches = [];

        foreach (self::PAGES as $component => $definition) {
            $response = $this->actingAs($admin)->get($this->urlFor($definition));
            $response->assertOk();

            $page = $response->viewData('page');

            $this->assertSame($component, $page['component'],
                "Route {$definition[0]} rendered {$page['component']}, not {$component}.");

            // Shared props come from the middleware and are not a page's to
            // declare; InertiaSharedPropsTest covers those. `errors` is
            // Inertia's own.
            $sent = array_values(array_diff(
                array_keys($page['props']),
                ['auth', 'flash', 'notifications', 'errors']
            ));
            sort($sent);

            $declared = $this->declaredProps($component);
            sort($declared);

            if ($sent !== $declared) {
                $mismatches[] = sprintf(
                    "%s\n        controller sends: %s\n        Props declares:   %s",
                    $component,
                    implode(', ', $sent) ?: '(none)',
                    implode(', ', $declared) ?: '(none)'
                );
            }
        }

        $this->assertSame([], $mismatches,
            "A page's Props interface no longer matches what its controller sends:\n  "
            .implode("\n  ", $mismatches)."\n");
    }

    public function test_the_page_list_has_no_stale_entries(): void
    {
        // The half that stops the list rotting, as UnusedCssClassTest needs for
        // its own. An entry naming a component that has been renamed or a route
        // that has been removed would otherwise sit here looking like coverage.
        $stale = [];

        foreach (self::PAGES as $component => [$routeName, $binding]) {
            if (! app('router')->has($routeName)) {
                $stale[] = "{$component}: route {$routeName} does not exist.";
            }

            $path = resource_path('js/Pages/'.$component.'.vue');

            if (! file_exists($path)) {
                $stale[] = "{$component}: no Vue page at ".str_replace(base_path().'/', '', $path);
            }
        }

        $this->assertSame([], $stale,
            "Stale entries in InertiaPropContractTest::PAGES:\n  ".implode("\n  ", $stale)."\n");
    }

    public function test_every_converted_page_is_listed(): void
    {
        // The other direction. A page added to resources/js/Pages without an
        // entry here is a page whose props nothing checks -- and since the list
        // cannot be derived from the controllers, the filesystem is the only
        // place that knows the page exists at all.
        $unlisted = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js/Pages'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'vue') {
                continue;
            }

            $component = str_replace(
                [resource_path('js/Pages').'/', '.vue'],
                '',
                $file->getPathname()
            );

            // Partials live beside their page and are not rendered by a
            // controller, so they have no props contract of their own.
            if (str_contains($component, '/Partials/')) {
                continue;
            }

            if (! array_key_exists($component, self::PAGES)) {
                $unlisted[] = $component;
            }
        }

        $this->assertSame([], $unlisted,
            "A converted page is not listed in InertiaPropContractTest::PAGES, so nothing\n"
            ."checks its props against its controller:\n  ".implode("\n  ", $unlisted)."\n");
    }

    /**
     * The top-level keys of `interface Props { ... }` in a page component.
     *
     * @return list<string>
     */
    private function declaredProps(string $component): array
    {
        $path = resource_path('js/Pages/'.$component.'.vue');

        $this->assertFileExists($path, "No Vue page for Inertia component {$component}.");

        $source = (string) file_get_contents($path);

        // No interface at all means no props declared, which is the honest
        // answer for a create form: it is sent nothing. Not a failure of its
        // own -- a page that IS sent props and has no interface still fails,
        // as a mismatch between what arrived and the empty list below.
        if (! preg_match('/interface\s+Props\s*\{(.*?)\n\}/s', $source, $block)) {
            return [];
        }

        // Top level only: anything nested inside a braced sub-shape is skipped,
        // since this checks the shape of the payload rather than its contents.
        $depth = 0;
        $keys = [];

        foreach (explode("\n", $block[1]) as $line) {
            if ($depth === 0 && preg_match('/^\s*([A-Za-z_]\w*)\??\s*:/', $line, $key)) {
                $keys[] = $key[1];
            }

            $depth += substr_count($line, '{') - substr_count($line, '}');
        }

        return array_values(array_unique($keys));
    }
}
