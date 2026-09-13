<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * An Alpine expression must not contain the quote that delimits it.
 *
 * x-data="{ ... }" is a double-quoted HTML attribute holding JavaScript. A
 * double quote anywhere inside it -- in a string, or in a comment, which is how
 * this happened -- ends the attribute there. The browser keeps the truncated
 * fragment, Alpine fails to parse it, and the whole component dies: no list, no
 * search, no dialog. There is no server-side signal at all. Blade renders
 * happily, every feature test passes, and the page looks fine until you open
 * the console.
 *
 * Truncation is not directly detectable -- reading the attribute gives you the
 * fragment, not the fact that it was cut -- but it is detectable by its
 * consequence: an expression cut mid-way almost always leaves brackets
 * unbalanced. That is what this checks.
 *
 * It is a heuristic, and it is a one-way one: balanced brackets do not prove an
 * expression is whole. It catches the failure that actually happened, which is
 * the bar a guard has to clear.
 */
class AlpineAttributeGuardTest extends TestCase
{
    /** Alpine directives whose value is JavaScript rather than a plain string. */
    private const DIRECTIVES = 'x-data|x-init|x-show|x-if|x-for|x-text|x-html|x-model|x-effect|x-on:[\w.-]+|x-bind:[\w.-]+|@[\w.-]+|:[\w-]+';

    public function test_every_alpine_expression_has_balanced_brackets(): void
    {
        $offenders = [];
        $root = resource_path('views').DIRECTORY_SEPARATOR;

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            $source = $file->getContents();
            $relative = str_replace($root, '', $file->getRealPath());

            // The value runs to the next double quote, which is exactly what
            // the browser does -- so a stray quote inside gives us the same
            // truncated fragment it gives the parser.
            preg_match_all('/\b('.self::DIRECTIVES.')="([^"]*)"/', $source, $matches, PREG_SET_ORDER);

            foreach ($matches as [$whole, $directive, $expression]) {
                if ($this->balanced($expression)) {
                    continue;
                }

                $line = substr_count(substr($source, 0, strpos($source, $whole)), "\n") + 1;

                $offenders[] = sprintf(
                    '%s:%d — %s="%s…"',
                    $relative, $line, $directive, mb_substr(trim($expression), 0, 60)
                );
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['An Alpine expression has unbalanced brackets, which usually means a double '
                .'quote inside it ended the attribute early and truncated the expression. '
                .'Alpine then fails to parse it and the whole component stops working, '
                .'silently. Move the text that needs quotes into a Blade comment:'],
            $offenders
        )));
    }

    /** Brackets outside string literals, which is where truncation shows. */
    private function balanced(string $expression): bool
    {
        $pairs = ['}' => '{', ')' => '(', ']' => '['];
        $stack = [];
        $quote = null;

        for ($i = 0, $n = strlen($expression); $i < $n; $i++) {
            $c = $expression[$i];

            if ($quote !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($c === "'" || $c === '`') {
                $quote = $c;
            } elseif (in_array($c, ['{', '(', '['], true)) {
                $stack[] = $c;
            } elseif (isset($pairs[$c])) {
                if (array_pop($stack) !== $pairs[$c]) {
                    return false;
                }
            }
        }

        return $stack === [] && $quote === null;
    }
}
