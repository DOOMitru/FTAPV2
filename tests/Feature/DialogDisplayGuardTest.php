<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * A closed <dialog> must stay closed.
 *
 * The UA stylesheet gives `dialog:not([open])` display: none. Any display
 * declaration on the element's own class beats it -- one specificity class
 * against a pseudo-class, and the author sheet wins regardless -- so the dialog
 * renders in the page flow, permanently visible, at the bottom of the document.
 *
 * That is not a hypothetical. `.register` needed an inner flex layout, took
 * `display: flex`, and shipped a register-players dialog sitting open at the
 * foot of every tournament page. Nothing caught it: the feature tests assert
 * markup and cannot see CSS, and every screenshot was taken after showModal(),
 * so the closed state -- the state the page is in almost all of the time -- was
 * never once looked at.
 *
 * The rule is that the layout goes on the [open] selector. This test reads the
 * dialog classes out of the views so a new dialog is covered the day it is
 * written rather than the day somebody remembers this file.
 */
class DialogDisplayGuardTest extends TestCase
{
    /** Every class used on a <dialog> anywhere in the views. */
    private function dialogClasses(): array
    {
        $classes = [];

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            preg_match_all('/<dialog\b[^>]*\bclass="([^"]+)"/', $file->getContents(), $matches);

            foreach ($matches[1] as $attribute) {
                foreach (preg_split('/\s+/', $attribute, -1, PREG_SPLIT_NO_EMPTY) as $class) {
                    // Blade expressions inside the attribute are not classes.
                    if (! str_contains($class, '{')) {
                        $classes[$class] = true;
                    }
                }
            }
        }

        return array_keys($classes);
    }

    public function test_the_views_actually_contain_dialogs(): void
    {
        // Without this the guard below passes on an empty set forever, which is
        // how a guard quietly stops guarding.
        $classes = $this->dialogClasses();

        $this->assertNotEmpty($classes, 'No <dialog> found in any view.');
        $this->assertContains('register', $classes);
        $this->assertContains('confirm', $classes);
    }

    public function test_no_dialog_class_sets_display_outside_the_open_state(): void
    {
        $offenders = [];
        $root = resource_path('css').DIRECTORY_SEPARATOR;
        $classes = $this->dialogClasses();

        foreach (Finder::create()->files()->in(resource_path('css'))->name('*.css') as $file) {
            // Comments first, or the text before a rule is read as part of its
            // selector -- which this test did on its first run, reporting a
            // prose paragraph as a CSS selector.
            $css = preg_replace('#/\*.*?\*/#s', '', $file->getContents());

            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

            foreach ($rules as [, $selectorList, $declarations]) {
                if (! preg_match('/(^|;)\s*display\s*:/', $declarations)) {
                    continue;
                }

                foreach (explode(',', $selectorList) as $selector) {
                    $selector = trim($selector);

                    if ($selector === '') {
                        continue;
                    }

                    // Only the SUBJECT of the selector -- its rightmost
                    // compound -- is the element being styled. `.register
                    // .picker__btn { display: grid }` lays out a row INSIDE the
                    // dialog and is none of this test's business; flagging it
                    // would make the rule unfollowable.
                    $compounds = preg_split('/\s*[\s>+~]\s*/', $selector, -1, PREG_SPLIT_NO_EMPTY);
                    $subject = end($compounds);

                    if ($subject === false || str_contains($subject, '[open]')) {
                        continue;
                    }

                    foreach ($classes as $class) {
                        // The class, not a longer name starting with it:
                        // .register must not match .register__note.
                        if (preg_match('/\.'.preg_quote($class, '/').'(?![\w-])/', $subject)) {
                            $offenders[] = str_replace($root, '', $file->getRealPath()).' — "'.$selector.'"';
                        }
                    }
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['A <dialog> class sets display outside [open], so the dialog renders '
                .'in the page flow while closed. Put the layout on the [open] selector:'],
            $offenders
        )));
    }
}
