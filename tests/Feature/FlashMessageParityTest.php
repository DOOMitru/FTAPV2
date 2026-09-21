<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A page that is sent a flash message shows it.
 *
 * The flash is shared by HandleInertiaRequests rather than sent by a
 * controller, so InertiaPropContractTest cannot see it: the page declares no
 * prop for it and the middleware shares it whether or not anyone draws it. A
 * page that simply omits the alert therefore passes every other guard here
 * while swallowing "Venue :name created." on the way back from the form --
 * which is what three of the converted index pages were doing when this was
 * written.
 *
 * Two rules, and the second is the one that cost a morning:
 *
 *   1. Every page a controller redirects to with a flash must SHOW it --
 *      itself, or through the shell it wears.
 *   2. No page or layout may capture it in a plain const. Inertia REUSES a
 *      component when a visit renders the same page, so a value read once at
 *      setup survives the visit while the props under it change -- and every
 *      redirect back to the page you are on shows nothing.
 *
 * The first rule used to be checked by pairing each converted page with the
 * Blade view it replaced. That premise expired: a converted page's Blade twin
 * is deleted with it, so the pairing found nothing and said so.
 *
 * It then expired a second time, in the other direction: AppLayout draws the
 * flash now, so a page that wears it keeps the promise without mentioning the
 * flash at all. What is checked is that SOMETHING shows it -- which is why the
 * shell's own half is asserted here too rather than taken on trust.
 */
class FlashMessageParityTest extends TestCase
{
    public function test_a_page_a_flash_is_sent_to_renders_it(): void
    {
        /*
         * A controller that redirects with a flash is making a promise on the
         * receiving page's behalf. Whether that page keeps it is not visible
         * from either end alone -- the middleware shares the flash whether or
         * not anyone draws it, so the props are identical either way, and the
         * only symptom is a confirmation that never appears.
         *
         * This used to pair each converted page with the Blade view it
         * replaced and compare the two. That premise expired: the Blade half
         * of a converted page is deleted with it, so the pairing found nothing
         * and said so. Reading the redirects is better anyway -- it asks what
         * the app actually sends rather than what the old template happened to
         * draw, and it keeps working when the last Blade page is gone.
         */
        $missing = [];

        foreach ($this->flashTargets() as $routeName => $keys) {
            $component = $this->componentFor($routeName);

            // A route still rendering Blade is not this guard's business; the
            // Blade views read session('status') directly.
            if ($component === null) {
                continue;
            }

            $source = (string) file_get_contents(resource_path('js/Pages/'.$component.'.vue'));

            /*
             * A page inside AppLayout is covered by the shell, which draws the
             * flash for every page that wears it. That is the whole point of
             * moving it there -- eleven pages each kept their own copy, and
             * three of them forgot.
             *
             * The import, not the word: prose mentioning AppLayout is not
             * AppLayout, a distinction this suite has had to learn repeatedly.
             * test_the_shell_shows_the_flash below is what stops this arm from
             * excusing every page while the shell quietly shows nothing.
             */
            if (str_contains($source, 'import AppLayout from')) {
                continue;
            }

            foreach ($keys as $key) {
                if (str_contains($source, 'flash.'.$key) || str_contains($source, 'flash?.'.$key)) {
                    continue;
                }

                $missing[] = sprintf(
                    '%s (%s): a controller redirects here with a %s flash, and the page never reads it.',
                    $component, $routeName, $key
                );
            }
        }

        $this->assertSame([], $missing,
            "A flash is sent to a page that does not show it, and it is not inside the shell that would:\n  "
            .implode("\n  ", $missing)."\n");
    }

    public function test_the_shell_shows_the_flash(): void
    {
        /*
         * The other half of rule 1. Every page that wears AppLayout is excused
         * above on the grounds that the shell draws the flash for it, so if
         * the shell ever stopped, that arm would excuse all of them and
         * nothing would fail.
         */
        $layout = (string) file_get_contents(resource_path('js/Layouts/AppLayout.vue'));

        foreach (['status' => 'success', 'error' => 'danger'] as $key => $variant) {
            $this->assertStringContainsString(
                '<Alert v-if="flash.'.$key.'" variant="'.$variant.'" :message="flash.'.$key.'" />',
                $layout,
                "The shell no longer shows flash.{$key}, and every page it wraps is excused on the assumption that it does."
            );
        }
    }

    public function test_a_page_may_hold_the_shells_flash_back_but_not_by_accident(): void
    {
        /*
         * One page turns the shell's flash off: the tournament page, while its
         * register dialog is open. The dialog is modal -- it sits in the top
         * layer and makes the rest of the page inert -- so a banner behind it
         * is announced to somebody who can neither see nor reach it, and the
         * dialog shows the same words itself.
         *
         * Both ends are asserted. A page can only opt out through a prop the
         * layout honours, so if either side is dropped the suppression
         * silently becomes permanent or silently stops.
         */
        $layout = (string) file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        $page = (string) file_get_contents(resource_path('js/Pages/Poker/Tournaments/Show.vue'));

        $this->assertStringContainsString('showFlash: true', $layout,
            'The shell must show the flash unless a page says otherwise.');
        $this->assertStringContainsString('props.showFlash &&', $layout,
            'The shell takes a showFlash prop and ignores it.');

        $this->assertStringContainsString(':show-flash="! dialogOpen"', $page,
            'The tournament page no longer holds the flash back while its dialog is open.');

        // Keyed on the dialog being OPEN, not on the action succeeding: a
        // player registering themselves opens no dialog and must still be told.
        $this->assertStringContainsString('dialogOpen.value = true;', $page);
    }

    public function test_the_redirect_scan_actually_finds_flashes(): void
    {
        // The guard above passes trivially on an empty set, and a broken regex
        // looks exactly like a clean run. Controllers do flash; this says so.
        $targets = $this->flashTargets();

        $this->assertNotEmpty($targets, 'No flashing redirect found. The scan is broken.');

        $this->assertArrayHasKey('poker.venues.index', $targets,
            'The venue controller flashes on create, update and delete; the scan missed it.');
    }

    public function test_no_page_reads_the_flash_into_a_plain_const(): void
    {
        /*
         * `const flash = usePage().props.flash` is the flash that arrived with
         * this component, captured once and never updated.
         *
         * Inertia REUSES a component when a visit renders the same page, so
         * the const survives the visit while the props underneath it change.
         * Every redirect back to the page you are already on is affected --
         * deleting a user and landing on the user list again, the
         * points-structure form returning to itself after each entry -- and
         * those are exactly the places a confirmation matters most, because
         * nothing else on screen changes enough to say the thing worked.
         *
         * A computed re-reads on every render and is correct in both cases.
         * Checked here rather than in a type: both spellings type-check, and
         * both render on a first visit, so nothing else can tell them apart.
         */
        $stale = [];

        foreach ($this->vuePages() as $path) {
            $source = (string) file_get_contents($path);

            if (preg_match('/=\s*usePage<[^>]*>\(\)\.props\.flash\s*;/', $source)) {
                $stale[] = str_replace(resource_path('js/').'', '', $path);
            }
        }

        $this->assertSame([], $stale,
            "A page captures the flash once instead of reading it reactively. Use
"
            ."computed(() => usePage<PageProps>().props.flash) -- a plain const goes stale
"
            ."the moment a visit re-renders the same component:
  ".implode("\n  ", $stale)."
");
    }

    /**
     * Route name => the flash keys controllers send to it.
     *
     * `back()->with(...)` is deliberately not read: where back() lands depends
     * on the referrer, so there is no page to hold to anything.
     *
     * @return array<string, list<string>>
     */
    private function flashTargets(): array
    {
        $targets = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Http/Controllers'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            // One statement: the redirect, its route, and everything chained
            // onto it up to the semicolon.
            preg_match_all(
                "/redirect\(\)->route\('([^']+)'(?:[^;]*?)->with\('(status|error)'/s",
                $source, $matches, PREG_SET_ORDER
            );

            foreach ($matches as [, $routeName, $key]) {
                if (! in_array($key, $targets[$routeName] ?? [], true)) {
                    $targets[$routeName][] = $key;
                }
            }
        }

        ksort($targets);

        return $targets;
    }

    /**
     * The Inertia component a named route renders, or null if it renders Blade.
     *
     * Read out of the controller method, because nothing else knows: the link
     * between a route and a component is a string passed to Inertia::render().
     */
    private function componentFor(string $routeName): ?string
    {
        $route = app('router')->getRoutes()->getByName($routeName);

        if ($route === null || ! is_string($action = $route->getActionName()) || ! str_contains($action, '@')) {
            return null;
        }

        [$class, $method] = explode('@', $action);

        if (! class_exists($class)) {
            return null;
        }

        $path = (new \ReflectionClass($class))->getFileName();

        if ($path === false) {
            return null;
        }

        $reflection = new \ReflectionMethod($class, $method);
        $body = implode('', array_slice(
            file($path),
            $reflection->getStartLine(),
            $reflection->getEndLine() - $reflection->getStartLine()
        ));

        return preg_match("/Inertia::render\('([^']+)'/", $body, $found) ? $found[1] : null;
    }

    /**
     * Every Vue page file, partials and LAYOUTS included.
     *
     * Wider than pairs(): a partial reads the flash too, and a page with no
     * Blade twin left is still a page. Layouts are in scope because AppLayout
     * is where the flash is actually read now -- and a layout persists across
     * far more visits than any page did, so a captured value there goes stale
     * in more places, not fewer.
     *
     * @return list<string>
     */
    private function vuePages(): array
    {
        $paths = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'))
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'vue') {
                $paths[] = $file->getPathname();
            }
        }

        return $paths;
    }

}
