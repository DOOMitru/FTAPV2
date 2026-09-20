<?php

namespace Tests\Feature;

use Tests\Concerns\ScansTemplates;
use Tests\TestCase;

/**
 * The design system forbids inline CSS. Two forms are checked, because a rule
 * that only covered style attributes would leave <style> blocks as an open
 * door -- and two views were already using one.
 *
 * The single permitted exception is a style attribute whose every declaration
 * is a custom property. That is how a genuinely data-driven value reaches the
 * stylesheet: <x-meter> renders style="--meter-fill: 86%" because the
 * percentage is computed per row and cannot exist as a class. The value is
 * data; the styling that consumes it still lives in _meter.css.
 */
class InlineStyleGuardTest extends TestCase
{
    use ScansTemplates;

    /**
     * Where the rule genuinely inverts.
     *
     * HTML email is the one medium that requires the opposite of everything
     * this guard enforces. Gmail and Outlook.com strip <style> blocks outright,
     * so a stylesheet reaches nobody -- which is why Laravel's mail pipeline
     * reads themes/default.css at send time and writes every declaration back
     * out as a style attribute on the element it applies to. The published
     * templates carry a <style> block for exactly that reason, and Outlook
     * needs style="border: hidden !important" because it does not honour the
     * cellspacing attribute.
     *
     * So the styling for these views still lives in one stylesheet, as the
     * design system requires. It is themes/default.css rather than
     * resources/css/, because the destination is an inbox rather than a page.
     */
    private const EXEMPT = ['views/vendor/mail'];

    public function test_no_view_contains_an_inline_style_attribute(): void
    {
        $offenders = [];

        foreach ($this->views() as $relative => $lines) {
            foreach ($lines as $index => $line) {
                // \b...style catches x-bind:style and :style too -- an Alpine
                // binding and a Vue one both write the same inline CSS, just
                // later.
                if (! preg_match('/\bstyle\s*=\s*(["\'])(.*?)\1/', $line, $matches)) {
                    continue;
                }

                if ($this->isOnlyCustomProperties($matches[2])) {
                    continue;
                }

                $offenders[] = sprintf('%s:%d — style="%s"', $relative, $index + 1, $matches[2]);
            }
        }

        $this->assertSame([], $offenders, $this->message(
            'Inline style attributes are not allowed. Move these into a stylesheet',
            $offenders
        ));
    }

    public function test_no_view_contains_a_style_block(): void
    {
        $offenders = [];

        foreach ($this->views() as $relative => $lines) {
            foreach ($lines as $index => $line) {
                if (preg_match('/<style[\s>]/i', $line)) {
                    $offenders[] = sprintf('%s:%d', $relative, $index + 1);
                }
            }
        }

        $this->assertSame([], $offenders, $this->message(
            'A <style> block in a view is inline CSS. Move it into resources/css/ and import it from app.css',
            $offenders
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    private function views(): array
    {
        $views = [];

        foreach ($this->templates() as $relative => $source) {
            foreach (self::EXEMPT as $path) {
                if (str_starts_with($relative, $path)) {
                    continue 2;
                }
            }

            $views[$relative] = explode("\n", $source);
        }

        return $views;
    }

    public function test_the_exemption_covers_only_the_mail_templates(): void
    {
        // The exemption list is the kind of thing that grows quietly once it
        // exists -- a directory added here is a directory the design system no
        // longer governs. Pinned, so widening it is a deliberate edit to a test
        // rather than a line in a diff nobody reads.
        $this->assertSame(['views/vendor/mail'], self::EXEMPT);
    }

    /**
     * "--meter-fill: 86%" passes. "width: 86%" does not. So does an empty
     * attribute, which is styling nothing but is still a habit worth breaking.
     */
    private function isOnlyCustomProperties(string $attribute): bool
    {
        // A Vue binding writes the same declaration inside a template literal
        // -- :style="`--meter-fill: ${percentage}%`" is the Blade twin's
        // style="--meter-fill: {{ $percentage }}%" -- so the backticks come
        // off before the declarations are read. Without this the one approved
        // escape hatch fails the moment it is written in Vue.
        $attribute = trim($attribute, '`');

        $declarations = array_filter(array_map('trim', explode(';', $attribute)), 'strlen');

        if ($declarations === []) {
            return false;
        }

        foreach ($declarations as $declaration) {
            if (! str_starts_with($declaration, '--')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $offenders
     */
    private function message(string $headline, array $offenders): string
    {
        return $headline.":\n  ".implode("\n  ", $offenders);
    }
}
