<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The footer, shared by the public shell and the dashboard shell.
 *
 * Its two pieces of text are both too long for a phone, and both used to break
 * wherever they happened to run out of room: the tagline mid-clause ("Play
 * hard. Play / smart. Be first to act.") and the signature mid-name ("© 2026
 * First to / Act Poker"). A stylesheet cannot choose where a run of text
 * breaks, so each is written as its own parts and the parts take a line each
 * below 48rem.
 *
 * Where they sit is CSS and cannot be asserted here. It was measured instead,
 * through an iframe at a true 375 because Chromium clamps its own layout
 * viewport to 500: at 1440 the year and the name share a line, below the
 * breakpoint they do not, neither is ever broken across a line of its own, and
 * the signature stays on the right-hand gutter at every width.
 */
class SiteFooterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function shells(): array
    {
        // Both shells, because the footer belongs to neither and a change to it
        // has to hold on both.
        return ['public' => ['/'], 'dashboard' => ['/dashboard']];
    }

    private function footer(string $path): string
    {
        $response = $path === '/dashboard'
            ? $this->actingAs(User::factory()->create(['approval_status' => 'approved']))->get($path)
            : $this->get($path);

        $html = $response->assertOk()->getContent();

        $at = strpos($html, 'site-footer');
        $this->assertNotFalse($at, "No footer on {$path}.");

        return substr($html, $at);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shells')]
    public function test_the_signature_is_written_as_a_year_and_a_name(string $path): void
    {
        $footer = $this->footer($path);

        // &copy; in the template stays an entity in the markup; it is only a
        // © once a browser has parsed it.
        $this->assertMatchesRegularExpression(
            '/<span>&copy;\s*'.date('Y').'<\/span>/',
            $footer,
            'The year must be its own element so the break can land after it.'
        );

        $this->assertMatchesRegularExpression(
            '/<span>'.preg_quote(config('app.name'), '/').'<\/span>/',
            $footer,
            'The name must be its own element so it is never split across a line.'
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shells')]
    public function test_the_footer_still_says_what_it_said(string $path): void
    {
        // The split is a layout change, not a copy change.
        $footer = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($this->footer($path))));

        $this->assertStringContainsString('© '.date('Y').' '.config('app.name'), $footer);
        $this->assertStringContainsString('Play hard. Play smart. Be first to act.', $footer);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shells')]
    public function test_the_tagline_keeps_its_own_parts(string $path): void
    {
        // The signature now follows the pattern the tagline set; breaking that
        // one would leave the other as the odd case with no reason recorded.
        $footer = $this->footer($path);

        foreach (['Play hard.', 'Play smart.', 'Be first to act.'] as $clause) {
            $this->assertStringContainsString('<span>'.$clause.'</span>', $footer);
        }
    }
}
