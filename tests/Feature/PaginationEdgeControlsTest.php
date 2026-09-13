<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Previous and Next in the design system's pagination.
 *
 * A third file beside PaginationViewTest (which paginator types render) and
 * PaginationWindowTest (which page numbers are chosen). This one is about the
 * two controls at the ends.
 *
 * Previous and Next carry a word AND an arrow. On a wide screen the word shows;
 * below 48rem it is clipped and the arrow takes its place, because PREVIOUS,
 * five page numbers and NEXT want about 400px against the 343 a 375 screen
 * offers -- so Next wrapped onto a row of its own and sat there orphaned while
 * Previous stayed above it.
 *
 * Which of the two is drawn is CSS and cannot be asserted here; it was measured
 * -- one row at 1440, two at 375 and 320 before, one at all three after, with
 * no horizontal overflow.
 *
 * The clip-versus-display:none distinction is measurable but not assertable
 * either, and no test below would notice it: both produce the identical layout.
 * Measured in the browser at 375 -- with the clip the control's innerText is
 * "PREVIOUS", and with display:none it is the empty string, which is an
 * unlabelled link for anyone not looking at the arrow. If this stylesheet is
 * ever rewritten, that is the check to repeat.
 *
 * What IS assertable is the thing the fix could most easily have broken: the
 * word is still in the document. Clipped, not removed -- display:none would
 * have taken the link's accessible name with it and left a screen reader with
 * an unlabelled control.
 */
class PaginationEdgeControlsTest extends TestCase
{
    use RefreshDatabase;

    private function schedule(int $count): void
    {
        $season = PokerSeason::firstOrCreate(['name' => 'Season 9'], [
            'start_date' => now()->subMonth(), 'end_date' => now()->addYear(), 'is_current' => true,
        ]);

        $venue = Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St']);

        foreach (range(1, $count) as $i) {
            PokerTournament::create([
                'name' => sprintf('Night %02d', $i),
                'start_time' => now()->addDays($i),
                'venue_id' => $venue->id,
                'season_id' => $season->id,
            ]);
        }
    }

    private function pager(): string
    {
        $html = $this->get('/events')->assertOk()->getContent();

        $at = strpos($html, 'class="pager"');
        $this->assertNotFalse($at, 'No pager on the events page.');

        return substr($html, $at, (int) strpos($html, '</nav>', $at) - $at);
    }

    public function test_previous_and_next_keep_their_words(): void
    {
        // The accessible name. Clipped at a breakpoint, never removed.
        $this->schedule(6);

        $pager = $this->pager();

        $this->assertStringContainsString('<span class="pager__label">Previous</span>', $pager);
        $this->assertStringContainsString('<span class="pager__label">Next</span>', $pager);
    }

    public function test_previous_and_next_carry_an_arrow_for_the_narrow_layout(): void
    {
        $this->schedule(6);

        $pager = $this->pager();

        $this->assertSame(2, substr_count($pager, 'pager__arrow'));

        // Decoration beside a word that says the same thing, so it is hidden
        // from the reader that already has the word.
        $this->assertSame(2, substr_count($pager, 'class="pager__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"'));
    }

    public function test_both_edges_are_marked_so_the_padding_can_go(): void
    {
        // With the word clipped, the inline padding holds open a box around
        // nothing -- so the two edge controls are square like the numbers.
        $this->schedule(6);

        $pager = $this->pager();

        $this->assertSame(2, substr_count($pager, 'pager__link--edge'));
    }

    public function test_a_disabled_edge_is_still_a_word_and_an_arrow(): void
    {
        // Page one: Previous is disabled. It is not display:none -- a control
        // that vanishes makes the row jump as you page through it -- so it has
        // to carry both halves like the live one.
        $this->schedule(6);

        $pager = $this->pager();

        $this->assertMatchesRegularExpression(
            '/pager__link--edge pager__link--disabled" aria-disabled="true">.*?pager__arrow.*?<span class="pager__label">Previous<\/span>/s',
            $pager
        );
    }

    public function test_the_arrows_point_opposite_ways(): void
    {
        // One path copied to both arms would render two identical arrows, which
        // no test on counts alone would notice.
        $this->schedule(6);

        $pager = $this->pager();

        $this->assertStringContainsString('M15 19l-7-7 7-7', $pager);
        $this->assertStringContainsString('M9 5l7 7-7 7', $pager);
    }

    public function test_the_arrow_leads_previous_and_follows_next(): void
    {
        // Reading order, not just presence: the back arrow before its word, the
        // forward arrow after it.
        $this->schedule(6);

        $pager = $this->pager();

        $this->assertMatchesRegularExpression('/M15 19l-7-7 7-7.*?>Previous</s', $pager);
        $this->assertMatchesRegularExpression('/>Next<.*?M9 5l7 7-7 7/s', $pager);
    }

    public function test_no_pager_at_all_on_a_single_page(): void
    {
        $this->schedule(1);

        $this->assertStringNotContainsString('class="pager"', $this->get('/events')->assertOk()->getContent());
    }
}
