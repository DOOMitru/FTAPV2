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
     * Every class hook a file mentions, as literal text.
     *
     * Two naive versions of this were wrong in opposite directions, which is
     * why it works the way it does:
     *
     *   - Reading only `class="..."` missed Blade's
     *     `$attributes->merge(['class' => 'dropdown__item'])`, where the hook
     *     is a PHP string rather than an attribute.
     *   - Reading Vue's `:class="classes"` counted the VARIABLE NAME as a hook,
     *     so a component was reported as having one its twin lacked.
     *
     * So: take every quoted string in the file, split it into tokens, and keep
     * only the tokens the stylesheet actually declares. A variable called
     * `classes` is not a declared class and drops out; `dropdown__item` is one
     * and is counted wherever it is written. Computed halves like
     * `'btn btn--'.$variant` contribute `btn`, and the modifier is covered by
     * UnusedCssClassTest rather than here.
     *
     * Two more things the first version got wrong, both found by running it:
     *
     *   - COMMENTS are stripped first. This project's components explain
     *     themselves at length and name class hooks while doing it --
     *     dropdown-link.blade.php mentions `.nav-link` in prose -- so a
     *     comment would report drift against a twin that renders identically.
     *     UnusedCssClassTest strips comments for exactly this reason.
     *   - BACKTICK strings are read too. A Vue template literal like
     *     `btn btn--${variant}` is where a computed class list naturally
     *     lands, and a regex looking only for quotes cannot see it.
     *
     * @return list<string>
     */
    private function classes(string $source): array
    {
        $declared = $this->declaredCssClasses();
        $found = [];
        $strings = [];

        $source = $this->withoutComments($source);
        $source = $this->withoutNonClassAttributes($source);

        // Kept before the binding strip below, because a composed modifier is
        // usually written INSIDE a binding -- :class="`alert--${variant}`" --
        // and detecting prefixes after removing them found none.
        $beforeBindings = $source;

        /*
         * `class` and `:class` are not the same kind of attribute.
         *
         * `class="a b"` is a list of classes. `:class="row.medal"` is an
         * EXPRESSION, and reading it as a list credited this file with `row`,
         * which is a declared class and had nothing to do with anything. The
         * same shape as `:class="classes"` earlier, except that one dropped
         * out because `classes` is not a declared class and this one did not.
         *
         * So a binding contributes only the quoted strings written INSIDE it,
         * and is then removed before the general pass. A component building
         * its class names in a const still gets full credit, because those
         * strings are quoted where they are declared.
         */
        preg_match_all('/:class="([^"]*)"/', $source, $bindings);

        foreach ($bindings[1] as $expression) {
            preg_match_all('/\'([^\']*)\'|`([^`]*)`/', $expression, $inner);

            foreach (array_merge($inner[1], $inner[2]) as $literal) {
                $strings[] = $literal;
            }
        }

        $source = (string) preg_replace('/:class="[^"]*"/', ' ', $source);

        /*
         * A composed modifier counts as every modifier it can compose.
         *
         * Blade writes `'badge badge--'.$variant`; the Vue twin writes the
         * three names out, because a composed name is invisible to
         * UnusedCssClassTest and that is how four notification tier classes
         * went missing. Both are correct, and without this they read as drift
         * in both directions at once.
         *
         * So a file mentioning the PREFIX is credited with every declared
         * class under it -- the same trade UnusedCssClassTest makes.
         */
        foreach ($this->composedPrefixes($beforeBindings) as $prefix) {
            foreach ($declared as $class) {
                if (str_starts_with($class, $prefix)) {
                    $found[] = $class;
                }
            }
        }

        // Every single-, double- or backtick-quoted run, which covers class
        // attributes, PHP string literals, Vue bindings and template literals.
        preg_match_all('/"([^"]*)"|\'([^\']*)\'|`([^`]*)`/', $source, $matches);

        $strings = array_merge($strings ?? [], $matches[1], $matches[2], $matches[3]);

        foreach ($strings as $string) {
            // Quotes, backticks and `$` are separators too: a nested literal
            // inside a template string -- `btn ${x ? ' btn--sm' : ''}` -- would
            // otherwise yield the token "'btn--sm'" with its quotes attached,
            // which matches no declared class and reads as drift.
            foreach (preg_split('/[\s{}:,?()\[\].\'"`$]+/', $string, -1, PREG_SPLIT_NO_EMPTY) as $token) {
                if (in_array($token, $declared, true)) {
                    $found[] = $token;
                }
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }

    /**
     * Modifier prefixes the file builds a class name from at runtime.
     *
     * `'badge badge--'.$variant` in Blade, `badge--${variant}` in Vue: in both
     * the prefix is followed by the end of a string literal or an
     * interpolation, which is what distinguishes it from a class written out
     * in full.
     *
     * @return list<string>
     */
    private function composedPrefixes(string $source): array
    {
        preg_match_all(
            '/(?<![\w-])([a-z][\w-]*--)(?:[\'"`]\s*\.|\$\{|\{\{|\$)/i',
            $source,
            $matches
        );

        return array_values(array_unique($matches[1]));
    }

    /**
     * The file with attributes that never carry class names blanked out.
     *
     * Two kinds get blanked out, because reading every quoted string in the
     * file cannot tell either from a class list:
     *
     *   - Attributes whose value is an identifier: `<slot name="action" />` is
     *     a slot called action, not the .action class.
     *   - Vue DIRECTIVE expressions, which are logic. `v-if="slots.action"`
     *     mentions `action` while saying nothing about classes -- that is what
     *     reported EmptyState as drifting from a twin it renders identically
     *     to. `:class` survives, since it is the `:` shorthand rather than a
     *     `v-` directive; a caller writing `v-bind:class` in full would be
     *     stripped and lose coverage, which is why nothing in this codebase
     *     does.
     */
    private function withoutNonClassAttributes(string $source): string
    {
        $patterns = [
            '/\b(?:name|id|for|type|role|href|src|alt|title|icon|label|value)="[^"]*"/i',
            '/(?:v-(?!bind:class)[\w:.-]+|@[\w:.-]+)="[^"]*"/i',

            /*
             * Every other `:` binding. :key="row.key", :title, :href, :style --
             * all expressions, none of them class lists, and each one able to
             * mention a declared class name by coincidence. :key="row.key"
             * credited a component with `row`, which is a real class in
             * _rows.css and had nothing to do with anything here.
             *
             * :class is excluded: it is handled separately above, where only
             * the quoted strings inside it are read.
             *
             * Anchored to whitespace. Unanchored, it matched the `:enter=` part
             * inside Blade's `x-transition:enter="dropdown__motion"` and ate
             * the transition classes off the Blade side of every pair that has
             * them -- reporting them as Vue-only when both had them.
             */
            '/(?<=\s):(?!class[=\s])[\w:.-]+="[^"]*"/',
        ];

        return (string) preg_replace($patterns, ' ', $source);
    }

    /**
     * The file with its commentary removed.
     *
     * Blade comments, HTML comments, and JavaScript block and line comments.
     * The line-comment pattern deliberately requires whitespace or a line
     * start before `//` so a URL's scheme is not mistaken for one.
     */
    private function withoutComments(string $source): string
    {
        $patterns = [
            '/\{\{--.*?--\}\}/s',   // Blade
            '/<!--.*?-->/s',         // HTML
            '/\/\*.*?\*\//s',        // /* block */
            '/(^|\s)\/\/[^\n]*/m',    // // line

            /*
             * A function NAME is not a class hook, and one of them collides.
             * `emph()` is a helper; `emph` is also the class on the <strong>
             * it produces. Blade calling emph() therefore looked like a hook
             * the Vue twin was missing. Dropping the name and keeping the
             * arguments leaves the strings inside the call readable.
             */
            '/\b[a-z_]\w*\s*\(/i',
        ];

        return (string) preg_replace($patterns, ' ', $source);
    }

    /**
     * Every class the stylesheets declare.
     *
     * The same extraction UnusedCssClassTest uses, for the same reason:
     * comments discuss class names constantly in this project, so they are
     * stripped before the selectors are read.
     *
     * @return list<string>
     */
    private function declaredCssClasses(): array
    {
        static $classes = null;

        if ($classes !== null) {
            return $classes;
        }

        $classes = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('css'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'css') {
                continue;
            }

            $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($file->getPathname()));

            preg_match_all('/\.(-?[_a-zA-Z][\w-]*)/', (string) $css, $matches);

            foreach ($matches[1] as $class) {
                $classes[] = $class;
            }
        }

        $classes = array_values(array_unique($classes));

        return $classes;
    }

}
