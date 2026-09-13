<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every icon-only control in the app is a box.
 *
 * They sit beside each other constantly -- the ghost + that opens the register
 * dialog next to the trash on every player row, the bell next to the theme
 * toggle in the bar -- and half of them used to rest with a transparent
 * hairline that only appeared on hover. A row read as one button and some
 * loose glyphs.
 *
 * A PHP suite cannot compute a border, so this reads the declarations. What it
 * is really guarding is the SET: a new icon control that rests bare, or an old
 * one quietly reverted, is the regression -- not a wrong pixel.
 */
class IconControlBorderTest extends TestCase
{
    /**
     * Every icon-only control, and the file its resting state is set in.
     *
     * Add to this list when you add a control. A test that names its subjects
     * is a test that can fall behind them, which is why the sweep below exists
     * as well.
     *
     * @var array<string, string>
     */
    private const CONTROLS = [
        '.action' => '3-components/_action.css',          // row actions: delete, edit, view, approve...
        '.theme-toggle' => '2-layout/_topbar.css',
        '.topbar__bell' => '2-layout/_topbar.css',
        '.topbar__burger' => '2-layout/_topbar.css',
        '.btn--ghost' => '3-components/_btn.css',         // the + trigger, search, clear
        '.pager__link' => '3-components/_pagination.css',
    ];

    /** The block of declarations a selector opens, up to its closing brace. */
    private function rule(string $selector, string $file): string
    {
        $css = file_get_contents(resource_path('css/'.$file));

        $this->assertSame(1, preg_match(
            '/^'.preg_quote($selector, '/').'\s*\{(?<body>[^}]*)\}/m', $css, $m
        ), "{$selector} should be declared exactly once at the start of a line in {$file}.");

        return $m['body'];
    }

    public function test_every_icon_control_rests_with_a_hairline(): void
    {
        foreach (self::CONTROLS as $selector => $file) {
            $body = $this->rule($selector, $file);

            $this->assertMatchesRegularExpression(
                '/border(-color)?:\s*(var\(--border-width\)\s+solid\s+)?var\(--c-border\)/',
                $body,
                "{$selector} rests without a border, so it reads as a glyph beside the controls it sits with."
            );

            $this->assertDoesNotMatchRegularExpression(
                '/border:\s*(0|var\(--border-width\)\s+solid\s+transparent)\s*;/',
                $body,
                "{$selector} still declares a bare or transparent border."
            );
        }
    }

    public function test_the_controls_that_sit_together_share_a_corner(): void
    {
        // The + that opens the register dialog is a .btn, at --radius. The
        // trash beside it was --radius-sm, so giving it a border alone left an
        // outlined 3px square against an outlined 6px one -- still not a pair.
        foreach (['.action' => '3-components/_action.css',
            '.theme-toggle' => '2-layout/_topbar.css',
            '.topbar__bell' => '2-layout/_topbar.css',
            '.topbar__burger' => '2-layout/_topbar.css'] as $selector => $file) {
            $this->assertStringContainsString(
                'border-radius: var(--radius);',
                $this->rule($selector, $file),
                "{$selector} does not share the corner of the buttons it sits beside."
            );
        }
    }

    public function test_no_icon_control_anywhere_declares_a_bare_border(): void
    {
        // The net for controls this test does not know about yet. Only rules
        // whose selector names an icon control are considered -- a nav link or
        // a dropdown item stripping its chrome is correct and stays correct.
        $offenders = [];

        foreach (['2-layout', '3-components'] as $layer) {
            foreach (glob(resource_path('css/'.$layer.'/*.css')) as $path) {
                preg_match_all('/^(?<sel>\.[\w-]+)\s*\{(?<body>[^}]*)\}/m',
                    file_get_contents($path), $rules, PREG_SET_ORDER);

                foreach ($rules as $rule) {
                    $looksLikeAnIconControl = preg_match(
                        '/(toggle|burger|bell|action|btn--icon)/', $rule['sel']
                    );

                    if (! $looksLikeAnIconControl) {
                        continue;
                    }

                    if (preg_match('/border:\s*(0|var\(--border-width\)\s+solid\s+transparent)\s*;/', $rule['body'])) {
                        $offenders[] = basename($path).' — '.$rule['sel'];
                    }
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['An icon control rests without a border:'], $offenders
        )));
    }
}
