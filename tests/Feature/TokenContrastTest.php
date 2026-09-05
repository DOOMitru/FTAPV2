<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The design system's contrast figures, as enforced invariants.
 *
 * Every colour defect this project has shipped passed every assertion that
 * existed at the time, because no assertion measured colour. This one does: it
 * reads the real token file, so a hand edit that breaks AA fails the suite
 * instead of reaching a screenshot -- or a user.
 *
 * Thresholds follow WCAG 2.1: 4.5:1 for normal text, 3:1 for large text and
 * non-text. Hairlines are exempt from 3:1 but get their own floor, because a
 * border at 1.1:1 is mathematically present and optically absent -- a failure
 * this system has now designed out four separate times.
 */
class TokenContrastTest extends TestCase
{
    /** Relative luminance, WCAG 2.1 definition. */
    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $c = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Every token as a literal hex, resolved from the real file.
     *
     * The light palette is declared on bare :root and the dark values once as
     * --dark-*, both before the mapping blocks that alias one onto the other.
     * Reading the first declaration of each name therefore yields both
     * palettes without having to model the cascade.
     *
     * @return array<string, string>
     */
    private static function tokens(): array
    {
        $css = file_get_contents(resource_path('css/1-base/_tokens.css'));

        // Strip comments first. The token file explains its own values, and
        // those explanations quote hex literals ("white on #EF4537 is only
        // 3.77:1") that must not be read as declarations.
        $css = preg_replace('#/\*.*?\*/#s', '', $css);

        preg_match_all('/(--[a-z0-9-]+):\s*(#[0-9A-Fa-f]{6})\s*;/', $css, $matches, PREG_SET_ORDER);

        $tokens = [];

        foreach ($matches as $match) {
            $tokens[$match[1]] ??= strtoupper($match[2]);
        }

        return $tokens;
    }

    /** @return array<string, array{string, string, float}> */
    public static function contrastPairs(): array
    {
        return [
            // Light theme — body and muted copy on both grounds.
            'light text on bg' => ['--c-text', '--c-bg', 4.5],
            'light text on surface' => ['--c-text', '--c-surface', 4.5],
            'light muted on bg' => ['--c-text-muted', '--c-bg', 4.5],
            'light muted on surface' => ['--c-text-muted', '--c-surface', 4.5],

            // Dark theme.
            'dark text on bg' => ['--dark-text', '--dark-bg', 4.5],
            'dark text on surface' => ['--dark-text', '--dark-surface', 4.5],
            'dark muted on bg' => ['--dark-text-muted', '--dark-bg', 4.5],
            'dark muted on surface' => ['--dark-text-muted', '--dark-surface', 4.5],

            // The brand red as text.
            'light primary on surface' => ['--c-primary', '--c-surface', 4.5],
            'light primary on bg' => ['--c-primary', '--c-bg', 4.5],
            'dark primary on surface' => ['--dark-primary', '--dark-surface', 4.5],
            'dark primary on bg' => ['--dark-primary', '--dark-bg', 4.5],

            // The brand red as a fill carrying a small uppercase label. This is
            // the pair that fails the moment anyone "simplifies" the fill back
            // to --c-primary: white on the logo red #EF4537 is only 3.77:1.
            'light fill carries ink' => ['--c-primary-ink', '--c-primary-fill', 4.5],
            'light fill hover carries ink' => ['--c-primary-ink', '--c-primary-fill-hover', 4.5],
            'dark fill carries ink' => ['--dark-primary-ink', '--dark-primary-fill', 4.5],
            'dark fill hover carries ink' => ['--dark-primary-ink', '--dark-primary-fill-hover', 4.5],

            // Felt green, the open/won semantic.
            'light open on surface' => ['--c-open', '--c-surface', 4.5],
            'light open on bg' => ['--c-open', '--c-bg', 4.5],
            'dark open on surface' => ['--dark-open', '--dark-surface', 4.5],
            'dark open on bg' => ['--dark-open', '--dark-bg', 4.5],

            // Medals are discs with ink, never text: no gold clears 4.5:1 on
            // white (#D4A017 manages 2.38:1), and a medal is a disc anyway.
            'gold disc carries ink' => ['--c-medal-ink', '--c-gold', 4.5],
            'silver disc carries ink' => ['--c-medal-ink', '--c-silver', 4.5],
            'bronze disc carries ink' => ['--c-medal-ink', '--c-bronze', 4.5],
        ];
    }

    #[DataProvider('contrastPairs')]
    public function test_token_pair_meets_its_contrast_floor(string $fg, string $bg, float $min): void
    {
        $tokens = self::tokens();

        $this->assertArrayHasKey($fg, $tokens, "Token {$fg} is not defined as a hex literal.");
        $this->assertArrayHasKey($bg, $tokens, "Token {$bg} is not defined as a hex literal.");

        $ratio = self::ratio($tokens[$fg], $tokens[$bg]);

        $this->assertGreaterThanOrEqual(
            $min,
            round($ratio, 2),
            sprintf(
                '%s (%s) on %s (%s) is %.2f:1, below the %.1f:1 floor.',
                $fg,
                $tokens[$fg],
                $bg,
                $tokens[$bg],
                $ratio,
                $min
            )
        );
    }

    /** @return array<string, array{string, string}> */
    public static function hairlinePairs(): array
    {
        return [
            'light hairline on surface' => ['--c-border', '--c-surface'],
            'light hairline on raised' => ['--c-border', '--c-surface-raised'],
            'dark hairline on surface' => ['--dark-border', '--dark-surface'],
            'dark hairline on raised' => ['--dark-border', '--dark-surface-raised'],
        ];
    }

    #[DataProvider('hairlinePairs')]
    public function test_hairline_is_optically_present(string $border, string $surface): void
    {
        $tokens = self::tokens();

        $this->assertArrayHasKey($border, $tokens, "Token {$border} is not defined as a hex literal.");
        $this->assertArrayHasKey($surface, $tokens, "Token {$surface} is not defined as a hex literal.");

        $ratio = self::ratio($tokens[$border], $tokens[$surface]);

        $this->assertGreaterThanOrEqual(
            1.4,
            round($ratio, 2),
            sprintf(
                '%s (%s) on %s (%s) is %.2f:1 — present in the maths, absent to the eye.',
                $border,
                $tokens[$border],
                $surface,
                $tokens[$surface],
                $ratio
            )
        );
    }
}
