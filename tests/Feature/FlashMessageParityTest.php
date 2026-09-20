<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A converted page still shows the flash message its Blade twin showed.
 *
 * The flash is shared by HandleInertiaRequests, not sent by a controller, so
 * InertiaPropContractTest cannot see it: the page declares no prop for it and
 * the middleware sends it whether or not anyone renders it. A page that simply
 * does not draw the alert therefore passes every other guard here while
 * swallowing "Venue :name created." on the way back from the form -- which is
 * what three of the converted index pages were doing when this was written.
 *
 * Paired by path, the way ComponentParityTest pairs components: Poker/Venues/
 * Index.vue against views/poker/venues/index.blade.php. A page whose Blade
 * twin is gone is not checked -- the Blade half of the app is being deleted as
 * it is converted, and a missing twin is that, not a fault.
 */
class FlashMessageParityTest extends TestCase
{
    public function test_a_converted_page_renders_the_flash_its_blade_twin_rendered(): void
    {
        $missing = [];

        foreach ($this->pairs() as $vuePath => $bladePath) {
            $blade = (string) file_get_contents($bladePath);
            $vue = (string) file_get_contents($vuePath);
            $page = $this->componentName($vuePath);

            foreach (['status', 'error'] as $key) {
                if (! str_contains($blade, "session('{$key}')")) {
                    continue;
                }

                // Either spelling counts: a page may read the whole bag once
                // into a `flash` const, as the index pages do, or reach for
                // the one key it wants inline.
                if (str_contains($vue, "flash.{$key}") || str_contains($vue, "flash?.{$key}")) {
                    continue;
                }

                $missing[] = sprintf(
                    '%s: its Blade twin renders session(%s) and this does not read flash.%s. '
                    .'The middleware shares it either way, so nothing else notices.',
                    $page, "'{$key}'", $key
                );
            }
        }

        $this->assertSame([], $missing,
            "A converted page drops a flash message its Blade twin shows:\n  "
            .implode("\n  ", $missing)."\n");
    }

    public function test_the_pairing_actually_finds_pages(): void
    {
        // The guard above passes trivially when it pairs nothing, and a broken
        // path mapping looks exactly like a clean run. Pages exist and some of
        // them still have Blade twins, so this must find some.
        $this->assertNotEmpty($this->pairs(),
            'No converted page was paired with a Blade view. The path mapping is broken, '
            .'and the guard above has been checking nothing.');
    }

    /**
     * Vue page path => its Blade counterpart, for every pair that still exists.
     *
     * @return array<string, string>
     */
    private function pairs(): array
    {
        $pairs = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js/Pages'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'vue') {
                continue;
            }

            $component = $this->componentName($file->getPathname());

            // Partials are rendered inside a page and have no Blade view of
            // their own to pair with; their Blade equivalents live under a
            // partials/ directory the name mapping would not reach anyway.
            if (str_contains($component, '/Partials/')) {
                continue;
            }

            $blade = resource_path('views/'.$this->kebabPath($component).'.blade.php');

            if (file_exists($blade)) {
                $pairs[$file->getPathname()] = $blade;
            }
        }

        return $pairs;
    }

    /** .../js/Pages/Poker/Venues/Index.vue => Poker/Venues/Index */
    private function componentName(string $path): string
    {
        return str_replace([resource_path('js/Pages').'/', '.vue'], '', $path);
    }

    /** Poker/PointsStructure/Index => poker/points-structure/index */
    private function kebabPath(string $component): string
    {
        return implode('/', array_map(
            fn (string $segment) => strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $segment)),
            explode('/', $component)
        ));
    }
}
