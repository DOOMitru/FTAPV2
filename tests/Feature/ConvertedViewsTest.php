<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The conversion ledger, enforced.
 *
 * "50 views left" written in a document is a number nobody updates. This is the
 * same number as a test: every view still permitted to contain Tailwind is
 * named below, and the list only shrinks.
 *
 * Two assertions, which matter equally:
 *
 *   1. A view NOT on the list must contain no Tailwind. That catches a
 *      regression -- a converted page picking a utility class back up.
 *   2. A view ON the list must still contain some. That catches a STALE entry:
 *      once a view is converted its name has to come off, or the list slowly
 *      stops describing anything and the first assertion's coverage rots.
 *
 * Phase 3 removes the auth and profile entries, Phase 4 the three showcase
 * pages, Phase 5 the admin CRUD. When the array is empty, Tailwind can be
 * deleted from package.json and app.css -- and that is a test going green
 * rather than somebody's judgement call.
 */
class ConvertedViewsTest extends TestCase
{
    /**
     * Views that may still contain Tailwind utility classes.
     *
     * @var list<string>
     */
    private const NOT_YET_CONVERTED = [
        'components/tournament-badge.blade.php',
        'poker/registrants/create.blade.php',
        'poker/registrants/edit.blade.php',
        'poker/results/create.blade.php',
        'poker/results/edit.blade.php',
        'poker/tournaments/create.blade.php',
        'poker/tournaments/edit.blade.php',
        'poker/venue-points/create.blade.php',
        'poker/venue-points/edit.blade.php',
        'users/edit.blade.php',
        'users/show.blade.php',
    ];

    /**
     * Shapes that only a Tailwind utility takes. Deliberately specific: a bare
     * word like "container" or a BEM class with a dash must not match, or the
     * whole guard becomes noise.
     */
    private const TAILWIND = [
        '/\b(?:sm|md|lg|xl|2xl|dark|hover|focus|group-hover|active|disabled):[a-z0-9-]/',
        '/\b(?:bg|text|border|from|via|to|ring|divide|placeholder|decoration|shadow|fill|stroke)-(?:white|black|transparent|current|inherit|gray|slate|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)(?:-\d{2,3})?(?:\/\d{1,3})?\b/',
        '/\b(?:p|px|py|pt|pb|pl|pr|m|mx|my|mt|mb|ml|mr|gap|space-[xy])-(?:\d+|px|auto)\b/',
        '/\b(?:w|h|min-w|min-h|max-w|max-h)-(?:\d+|full|screen|auto|px|min|max|fit|xs|sm|md|lg|xl|\d?xl)\b/',
        '/\b(?:rounded|shadow)(?:-(?:none|sm|md|lg|xl|2xl|3xl|full))?\b/',
        // (?<![\w-]) rather than \b: a hyphen IS a word boundary, so \bgrid\b
        // matches inside l-grid, \bhidden\b inside u-visually-hidden and
        // \bblock\b inside btn--block. Before this was tightened the guard
        // flagged seven already-converted views on that alone.
        '/(?<![\w-])(?:flex|grid|inline-flex|inline-grid|block|inline-block|hidden)(?![\w-])/',
        '/\b(?:text|font|tracking|leading)-(?:xs|sm|base|lg|xl|\dxl|thin|light|normal|medium|semibold|bold|extrabold|black|tight|wide|wider|widest|snug|relaxed|loose)\b/',
    ];

    public function test_no_converted_view_contains_tailwind(): void
    {
        $offenders = [];

        foreach ($this->views() as $relative => $source) {
            if (in_array($relative, self::NOT_YET_CONVERTED, true)) {
                continue;
            }

            if ($count = $this->tailwindClassCount($source)) {
                $offenders[] = sprintf('%s — %d Tailwind classes', $relative, $count);
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['These views are converted and must stay that way:'],
            $offenders
        )));
    }

    public function test_the_allowlist_names_no_view_that_is_already_converted(): void
    {
        $views = $this->views();
        $stale = [];

        foreach (self::NOT_YET_CONVERTED as $relative) {
            if (! array_key_exists($relative, $views)) {
                $stale[] = $relative.' — no longer exists';
                continue;
            }

            if ($this->tailwindClassCount($views[$relative]) === 0) {
                $stale[] = $relative.' — already converted';
            }
        }

        $this->assertSame([], $stale, implode("\n  ", array_merge(
            ['Converted. Remove these from NOT_YET_CONVERTED so the guard covers them:'],
            $stale
        )));
    }

    private function tailwindClassCount(string $source): int
    {
        preg_match_all('/class="([^"]*)"/', $source, $attributes);

        $count = 0;

        foreach ($attributes[1] as $attribute) {
            foreach (self::TAILWIND as $pattern) {
                $count += preg_match_all($pattern, $attribute);
            }
        }

        return $count;
    }

    /**
     * @return array<string, string>
     */
    private function views(): array
    {
        $root = resource_path('views').DIRECTORY_SEPARATOR;
        $views = [];

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            $views[str_replace($root, '', $file->getRealPath())] = file_get_contents($file->getRealPath());
        }

        return $views;
    }
}
