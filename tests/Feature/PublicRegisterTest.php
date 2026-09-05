<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The system has two visual registers.
 *
 * The dashboard is borders-not-shadows: a 1px hairline does every separation,
 * and the only shadow in the app shell is --shadow-overlay on dropdowns and
 * modals. The public site is the shop window and is allowed gradients and
 * elevation.
 *
 * That licence is only worth having if it has an edge. Without this test the
 * boundary is a comment, and a comment loses the first time a gradient looks
 * good on a stat tile. The tokens themselves are the thing being fenced:
 * checking for the word "gradient" instead would ban _meter.css's
 * repeating-linear-gradient, which is the chip-stack pattern -- one colour
 * repeated, not decoration.
 */
class PublicRegisterTest extends TestCase
{
    /** Tokens that belong to the public register alone. */
    private const PUBLIC_ONLY = [
        '--gradient-primary',
        '--gradient-accent',
        '--gradient-accent-ink',
        '--gradient-surface',
        '--gradient-raised',
        '--gradient-panel',
        '--gradient-panel-ink',
        '--gradient-panel-ink-inverse',
        '--shadow-raised',
        '--shadow-float',
        '--radius-lg',
    ];

    /**
     * Where they may be referenced. _tokens.css defines them, which is not a
     * use; the check below only looks at var() references.
     */
    private const ALLOWED = [
        '5-public/',
        '2-layout/_shell-public.css',
        '4-pages/',
    ];

    public function test_public_register_tokens_stay_out_of_the_app_shell(): void
    {
        $offenders = [];

        foreach ($this->stylesheets() as $relative => $lines) {
            if ($this->isAllowed($relative)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                foreach (self::PUBLIC_ONLY as $token) {
                    if (str_contains($line, 'var('.$token)) {
                        $offenders[] = sprintf('%s:%d — %s', $relative, $index + 1, trim($line));
                    }
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['The public register is for the public site. These belong in resources/css/5-public/:'],
            $offenders
        )));
    }

    /**
     * The converse: the dashboard's rule is that --shadow-overlay is its only
     * shadow. A raw box-shadow value outside the public register would sidestep
     * both the tokens and the test above.
     */
    public function test_the_app_shell_declares_no_raw_shadows(): void
    {
        $offenders = [];

        foreach ($this->stylesheets() as $relative => $lines) {
            if ($this->isAllowed($relative) || str_contains($relative, '1-base/_tokens.css')) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (! preg_match('/^\s*box-shadow\s*:\s*(.+);/', $line, $matches)) {
                    continue;
                }

                $value = trim($matches[1]);

                if ($value === 'none' || str_starts_with($value, 'var(--shadow-overlay)')) {
                    continue;
                }

                $offenders[] = sprintf('%s:%d — box-shadow: %s', $relative, $index + 1, $value);
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['Outside the public register, --shadow-overlay is the only shadow:'],
            $offenders
        )));
    }

    /**
     * @return array<string, list<string>>
     */
    private function stylesheets(): array
    {
        $root = resource_path('css').DIRECTORY_SEPARATOR;
        $sheets = [];

        foreach (Finder::create()->files()->in(resource_path('css'))->name('*.css') as $file) {
            $sheets[str_replace($root, '', $file->getRealPath())] = file(
                $file->getRealPath(),
                FILE_IGNORE_NEW_LINES
            );
        }

        return $sheets;
    }

    private function isAllowed(string $relative): bool
    {
        foreach (self::ALLOWED as $prefix) {
            if (str_contains($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
