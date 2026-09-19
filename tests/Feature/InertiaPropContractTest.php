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

    /** Inertia component name => the route that renders it. */
    private const PAGES = [
        'Profile/Edit' => 'profile.edit',
        'Poker/Seasons/Index' => 'poker.seasons.index',
    ];

    public function test_each_page_declares_exactly_the_props_it_is_sent(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
        $mismatches = [];

        foreach (self::PAGES as $component => $routeName) {
            $response = $this->actingAs($admin)->get(route($routeName));
            $response->assertOk();

            $page = $response->viewData('page');

            $this->assertSame($component, $page['component'],
                "Route {$routeName} rendered {$page['component']}, not {$component}.");

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

        foreach (self::PAGES as $component => $routeName) {
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

        if (! preg_match('/interface\s+Props\s*\{(.*?)\n\}/s', $source, $block)) {
            $this->fail("{$component} declares no `interface Props`.");
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
