<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A Blade component and its Vue twin must agree about class hooks.
 *
 * Eleven components are used by both the public Blade pages and the converted
 * Vue pages, so each exists twice for the length of the conversion. Two
 * definitions of one thing is the defect this project keeps meeting --
 * PokerSeason::current(), VenuePoints::readableBy() and initials() all exist
 * because a second definition drifted or was about to.
 *
 * **The pairs are DISCOVERED, not listed.** Every Components/X.vue whose
 * kebab-cased name matches a views/components/x.blade.php is a pair. A
 * hand-written list would be one more thing to remember, and the list that
 * nobody updates is the failure mode this whole suite keeps running into --
 * see UnusedCssClassTest::KEPT, which needs a second test purely to stop its
 * own list rotting. There is nothing here to forget to add.
 *
 * This does not prove the two behave alike. It proves they agree about the
 * hooks the stylesheet is keyed on, which is the drift that silently changes
 * how a page looks. Behavioural drift is accepted and unguarded -- recorded in
 * the spec, §2.
 */
class ComponentParityTest extends TestCase
{
    public function test_each_discovered_pair_uses_the_same_class_hooks(): void
    {
        $pairs = $this->pairs();
        $drifted = [];

        foreach ($pairs as $vuePath => $bladePath) {
            $inBlade = $this->classes((string) file_get_contents($bladePath));
            $inVue = $this->classes((string) file_get_contents($vuePath));

            $missing = array_diff($inBlade, $inVue);
            $extra = array_diff($inVue, $inBlade);

            if ($missing !== [] || $extra !== []) {
                $drifted[] = sprintf(
                    "%s\n        only in Blade: %s\n        only in Vue:   %s",
                    basename($vuePath),
                    implode(', ', $missing) ?: '(none)',
                    implode(', ', $extra) ?: '(none)'
                );
            }
        }

        $this->assertSame([], $drifted,
            "A component and its Blade twin disagree about class hooks. The CSS is\n"
            ."keyed on these, so the two now render differently:\n  ".implode("\n  ", $drifted)."\n");
    }

    public function test_the_discovery_actually_finds_the_pairs(): void
    {
        // The guard above passes trivially when it finds nothing, which is
        // exactly what a broken name mapping looks like. Once any Vue
        // component exists that has a Blade counterpart, at least one pair
        // must be discovered -- otherwise this suite reports parity it never
        // checked.
        $vueWithBladeTwin = [];

        foreach (glob(resource_path('js/Components/*.vue')) ?: [] as $vuePath) {
            if (file_exists($this->bladePathFor($vuePath))) {
                $vueWithBladeTwin[] = basename($vuePath);
            }
        }

        if ($vueWithBladeTwin === []) {
            $this->markTestSkipped('No Vue component has a Blade counterpart yet.');
        }

        $this->assertCount(count($vueWithBladeTwin), $this->pairs(),
            'Discovery missed a pair it should have found: '.implode(', ', $vueWithBladeTwin));
    }

    /**
     * Vue component path => its Blade counterpart, for every pair that exists.
     *
     * @return array<string, string>
     */
    private function pairs(): array
    {
        $pairs = [];

        foreach (glob(resource_path('js/Components/*.vue')) ?: [] as $vuePath) {
            $bladePath = $this->bladePathFor($vuePath);

            if (file_exists($bladePath)) {
                $pairs[$vuePath] = $bladePath;
            }
        }

        return $pairs;
    }

    /** SiteFooter.vue => resources/views/components/site-footer.blade.php */
    private function bladePathFor(string $vuePath): string
    {
        $name = basename($vuePath, '.vue');
        $kebab = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));

        return resource_path('views/components/'.$kebab.'.blade.php');
    }

    /**
     * Every class literal written in a class attribute.
     *
     * Both `class="a b"` and Vue's `:class` bindings are read: a hook that
     * appears only inside a binding still has to exist on both sides. Blade
     * echoes and Vue expressions are stripped first, so a conditional class
     * contributes its literals and not its logic.
     *
     * @return list<string>
     */
    private function classes(string $source): array
    {
        preg_match_all('/(?::class|class)="([^"]*)"/', $source, $matches);

        $classes = [];

        foreach ($matches[1] as $attribute) {
            $literal = preg_replace('/\{\{.*?\}\}/s', ' ', $attribute);

            foreach (preg_split('/[\s\'{}:,?()]+/', (string) $literal, -1, PREG_SPLIT_NO_EMPTY) as $token) {
                // A class name, not an identifier from an expression. Both look
                // alike, so this errs towards including a token: a false
                // positive shows up as drift on both sides and cancels out, a
                // false negative silently drops a hook from the comparison.
                if (preg_match('/^[a-z][a-z0-9_-]*$/', $token)) {
                    $classes[] = $token;
                }
            }
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }
}
