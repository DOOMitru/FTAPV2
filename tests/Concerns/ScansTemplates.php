<?php

namespace Tests\Concerns;

use Symfony\Component\Finder\Finder;

/**
 * Every template in the app, whichever half it belongs to.
 *
 * The guards that sweep a directory were written when there was one directory
 * to sweep. Half the app is Vue now, and a guard still pointed at
 * resources/views is a guard whose coverage shrinks with every page converted
 * -- passing more easily each time, which is the failure mode this suite keeps
 * meeting. `resources/js` is swept alongside `resources/views` so that a rule
 * about markup applies to markup, not to a file extension.
 *
 * A trait rather than a base class, for the reason BuildsTournaments gives: a
 * parent's test methods run again inside every subclass.
 */
trait ScansTemplates
{
    /**
     * Relative path => source, for every Blade view and Vue component.
     *
     * The path is relative to `resources/`, so a failure says
     * `js/Pages/Users/Index.vue` or `views/home.blade.php` and the reader can
     * tell at a glance which half it is looking at.
     *
     * @return array<string, string>
     */
    protected function templates(): array
    {
        $found = [];

        foreach ($this->templateRoots() as $directory => $pattern) {
            $root = resource_path($directory).DIRECTORY_SEPARATOR;

            foreach (Finder::create()->files()->in(resource_path($directory))->name($pattern) as $file) {
                $found[$directory.'/'.str_replace($root, '', $file->getRealPath())]
                    = (string) file_get_contents($file->getRealPath());
            }
        }

        return $found;
    }

    /**
     * Blade views and Vue single-file components.
     *
     * resources/js also holds plain .ts -- confirm.ts, emph.ts, the two entry
     * points -- and those are not templates. Only .vue files carry markup.
     *
     * @return array<string, string>
     */
    protected function templateRoots(): array
    {
        return [
            'views' => '*.blade.php',
            'js' => '*.vue',
        ];
    }

    /** Is this one of the Vue half's files? */
    protected function isVue(string $path): bool
    {
        return str_ends_with($path, '.vue');
    }
}
