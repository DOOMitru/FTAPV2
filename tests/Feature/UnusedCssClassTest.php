<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Every class the stylesheet declares is used by something that renders.
 *
 * Dead CSS is invisible. It costs bytes on every page and, worse, it reads as
 * live: the next person finds a block that looks like the way to style the
 * thing they are building, and follows a pattern nothing has used in months.
 *
 * This was found twice by hand in two days -- three unused components with
 * their styles, then nine more classes and a whole file -- and both times the
 * manual grep nearly got it wrong in the SAME way: `p-item` looks used because
 * `p-item__title` contains it, and `tshow__register` looks used because
 * `tshow__register-btn` does. Matching here is token-exact for that reason: a
 * class counts as used only where no class-name character sits either side of
 * it.
 *
 * Which leaves the classes assembled at runtime -- `'alert alert--'.$variant`
 * never contains the string `alert--danger`. Those are recognised by their
 * prefix, but only where the prefix is CONCATENATED with something: the end of
 * a string literal, or a Blade echo. The looser version of that rule let
 * `l-grid--trio`, written out in full, vouch for every other `l-grid--`
 * modifier in the file.
 */
class UnusedCssClassTest extends TestCase
{
    /**
     * Declared, unused, and kept anyway -- with the reason.
     *
     * The point of the list is that it is short and that every line is a
     * decision somebody made, rather than a class nobody has looked at. An
     * entry is a promise that this one earns its place; it is not a parking
     * space.
     */
    private const KEPT = [
        'l-container--narrow' => 'Layout vocabulary. The container has one modifier and this is it; a primitive with no modifiers left stops reading as a primitive.',
        'l-grid--tight' => 'Layout vocabulary, and the pair to l-grid--wide.',
        'l-grid--wide' => 'Layout vocabulary, and the worked example in ModifierClassGuardTest: that test explains itself with `class="l-grid--wide"` without its base.',
        'link--danger' => 'Cited by name in _btn.css and _action.css as the pattern each follows. Deleting the class would leave both notes pointing at nothing.',
    ];

    /** Where a class may be put to use. Not the stylesheets themselves. */
    private const MARKUP = [
        'resources/views' => '.blade.php',
        'resources/js' => '.ts',
        'app' => '.php',
        'config' => '.php',
        'routes' => '.php',
    ];

    public function test_every_declared_class_is_used_or_deliberately_kept(): void
    {
        $markup = $this->markup();
        $unused = [];

        foreach ($this->declaredClasses() as $class => $files) {
            if ($this->isUsed($class, $markup) || $this->isComposed($class, $markup)) {
                continue;
            }

            if (array_key_exists($class, self::KEPT)) {
                continue;
            }

            $unused[] = sprintf('.%s — %s', $class, implode(', ', $files));
        }

        sort($unused);

        $this->assertSame([], $unused,
            "Declared and never used. Delete the block, or add the class to\n"
            ."UnusedCssClassTest::KEPT with the reason it stays:\n  "
            .implode("\n  ", $unused)."\n");
    }

    public function test_nothing_is_kept_that_no_longer_needs_keeping(): void
    {
        // The half that stops the list rotting. A kept class that has since
        // found a use, or been deleted outright, leaves behind a line that
        // reads as a considered decision and is not one.
        $markup = $this->markup();
        $declared = $this->declaredClasses();
        $stale = [];

        foreach (self::KEPT as $class => $reason) {
            if (! array_key_exists($class, $declared)) {
                $stale[] = sprintf('.%s is no longer declared anywhere.', $class);

                continue;
            }

            if ($this->isUsed($class, $markup) || $this->isComposed($class, $markup)) {
                $stale[] = sprintf('.%s is in use now; it does not need an exemption.', $class);
            }
        }

        $this->assertSame([], $stale,
            "Stale entries in UnusedCssClassTest::KEPT:\n  ".implode("\n  ", $stale)."\n");
    }

    /**
     * Every class selector in the stylesheets, and the files declaring it.
     *
     * Comments are stripped first. They discuss class names constantly -- this
     * project's CSS explains itself at length -- and a class mentioned only in
     * the note above its own rule would otherwise declare itself.
     *
     * @return array<string, list<string>>
     */
    private function declaredClasses(): array
    {
        $declared = [];

        foreach ($this->filesIn(base_path('resources/css'), '.css') as $path) {
            $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

            preg_match_all('/\.(-?[_a-zA-Z][\w-]*)/', (string) $css, $matches);

            foreach ($matches[1] as $class) {
                $relative = str_replace(base_path().'/', '', $path);

                if (! in_array($relative, $declared[$class] ?? [], true)) {
                    $declared[$class][] = $relative;
                }
            }
        }

        ksort($declared);

        return $declared;
    }

    /** Everything that could put a class on an element, as one string. */
    private function markup(): string
    {
        $markup = '';

        foreach (self::MARKUP as $directory => $extension) {
            foreach ($this->filesIn(base_path($directory), $extension) as $path) {
                $markup .= file_get_contents($path)."\n";
            }
        }

        return $markup;
    }

    /** Written out in full, with no class-name character either side. */
    private function isUsed(string $class, string $markup): bool
    {
        return (bool) preg_match('/(?<![\w-])'.preg_quote($class, '/').'(?![\w-])/', $markup);
    }

    /** Assembled at runtime: the modifier's prefix, concatenated with a value. */
    private function isComposed(string $class, string $markup): bool
    {
        if (! str_contains($class, '--')) {
            return false;
        }

        $prefix = substr($class, 0, (int) strrpos($class, '--') + 2);

        return (bool) preg_match(
            '/(?<![\w-])'.preg_quote($prefix, '/').'(?:[\'"]\s*\.|\{\{|\$)/',
            $markup
        );
    }

    /** @return list<string> */
    private function filesIn(string $directory, string $extension): array
    {
        $paths = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $extension)) {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        return $paths;
    }
}
