<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * A BEM-style modifier carries only the properties that differ; the base class
 * carries the rest. `class="l-grid--wide"` without `l-grid` therefore sets
 * grid-template-columns on an element that is not a grid, and does nothing at
 * all -- no error, no failing test, and a computed-style check still looks
 * plausible because the property really is set.
 *
 * This has now happened three times in one phase (l-grid--wide twice,
 * l-cluster--between once), which is twice more than a rule that lives only in
 * someone's memory deserves.
 */
class ModifierClassGuardTest extends TestCase
{
    public function test_every_layout_modifier_is_used_with_its_base_class(): void
    {
        $offenders = [];
        $root = resource_path('views').DIRECTORY_SEPARATOR;

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            $relative = str_replace($root, '', $file->getRealPath());

            foreach (file($file->getRealPath(), FILE_IGNORE_NEW_LINES) as $index => $line) {
                foreach ($this->classAttributes($line) as $attribute) {
                    $classes = preg_split('/\s+/', $attribute, -1, PREG_SPLIT_NO_EMPTY);

                    foreach ($classes as $class) {
                        if (! str_contains($class, '--')) {
                            continue;
                        }

                        $base = strstr($class, '--', true);

                        // Only layout primitives are checked. Component
                        // modifiers such as .btn--primary are applied by the
                        // component itself, which always emits the base too.
                        if (! str_starts_with($base, 'l-')) {
                            continue;
                        }

                        if (! in_array($base, $classes, true)) {
                            $offenders[] = sprintf(
                                '%s:%d — "%s" used without "%s"',
                                $relative, $index + 1, $class, $base
                            );
                        }
                    }
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['A layout modifier needs its base class; alone it silently does nothing:'],
            $offenders
        )));
    }

    /**
     * @return list<string>
     */
    private function classAttributes(string $line): array
    {
        preg_match_all('/class="([^"]*)"/', $line, $matches);

        // Blade expressions inside a class attribute are left alone: their
        // value is not knowable statically.
        return array_map(fn (string $value) => preg_replace('/\{\{.*?\}\}/', ' ', $value), $matches[1]);
    }
}
