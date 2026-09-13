<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entity names set apart inside messages.
 *
 * A confirmation or a flash naming a venue, player, season, sponsor or
 * tournament is read for the NAME. The sentence around it is the same every
 * time -- "Delete :name? This cannot be undone." -- so an administrator
 * deleting the third of four seasons is scanning for which one, and it was set
 * in the same weight as the words either side of it.
 *
 * The mechanism is a marker, not markup, and that is the part worth guarding.
 * The message is plain text from the controller, through the session or a data
 * attribute, to the renderer; confirm.ts reads its copy as a VALUE rather than
 * as source, which is what stops a season called `'); alert(1); //` being a
 * script. Putting <strong> in the message would hand that back. So the name is
 * wrapped in an invisible marker and the bolding happens at the very end, out
 * of text nodes and escaped segments.
 */
class EmphasisTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    public function test_a_marked_name_is_bolded_when_rendered(): void
    {
        $html = emph_html('Delete '.emph('Ace of Spades').'?')->toHtml();

        $this->assertSame('Delete <strong class="emph">Ace of Spades</strong>?', $html);
    }

    public function test_the_message_around_it_is_left_alone(): void
    {
        $this->assertSame('Nothing marked here.', emph_html('Nothing marked here.')->toHtml());
    }

    public function test_a_name_is_escaped_rather_than_parsed(): void
    {
        // The whole reason this is a marker and not markup. A venue named with
        // a tag must come out as text, bolded, not as a tag.
        $html = emph_html('Delete '.emph('<script>alert(1)</script>').'?')->toHtml();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<strong class="emph">', $html);
    }

    public function test_an_unpaired_marker_does_not_bold_the_rest_of_the_message(): void
    {
        // Being wrong in the quiet direction. A stray marker leaves a tail
        // unbolded; the alternative is a sentence that is all bold from the
        // marker onwards.
        $html = emph_html('Delete '.EMPH.'Ace of Spades? This cannot be undone.')->toHtml();

        $this->assertStringNotContainsString('<strong', $html);
    }

    public function test_an_empty_name_produces_no_empty_element(): void
    {
        $this->assertSame('Deleted .', emph_html('Deleted '.emph('').'.')->toHtml());
    }

    public function test_a_season_name_is_bolded_in_its_delete_confirmation(): void
    {
        PokerSeason::create([
            'name' => 'Autumn 2026',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('poker.seasons.index'))->assertOk()->getContent();

        // In the attribute it is still text with markers round it: confirm.ts
        // does the bolding in the browser, out of text nodes.
        $this->assertStringContainsString(EMPH.'Autumn 2026'.EMPH, $html);
    }

    public function test_a_venue_name_is_bolded_in_its_delete_confirmation(): void
    {
        Venue::create(['name' => 'Ace of Spades Lounge', 'address' => '1 Card Street']);

        $html = $this->actingAs($this->admin())
            ->get(route('poker.venues.index'))->assertOk()->getContent();

        $this->assertStringContainsString(EMPH.'Ace of Spades Lounge'.EMPH, $html);
    }

    public function test_a_player_name_is_bolded_in_a_flash_message(): void
    {
        // Through the session and out the other side, where the alert component
        // turns the marked run into <strong>.
        $player = User::factory()->create([
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin())
            ->from(route('users.index'))
            ->patch(route('users.approve', $player));

        $this->assertStringContainsString(EMPH.'Ada Lovelace'.EMPH, (string) session('status'));

        $this->assertStringContainsString(
            '<strong class="emph">Ada Lovelace</strong>',
            $this->followRedirects($response)->getContent()
        );
    }

    public function test_a_name_with_markup_in_it_survives_a_real_flash_message(): void
    {
        // End to end, with the input that matters: the name reaches the page
        // bolded and escaped, and nothing it contains becomes an element.
        $player = User::factory()->create([
            'first_name' => '<b>Ada', 'last_name' => 'Lovelace</b>', 'approval_status' => 'pending',
        ]);

        $html = $this->followRedirects(
            $this->actingAs($this->admin())
                ->from(route('users.index'))
                ->patch(route('users.approve', $player))
        )->getContent();

        $this->assertStringContainsString('&lt;b&gt;Ada Lovelace&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Ada', $html);
    }

    public function test_no_marker_reaches_a_reader_unrendered(): void
    {
        // The marker is invisible, so a page that printed one raw would look
        // perfectly fine and be subtly broken for anyone copying the text out.
        // Every rendered marker must have become a <strong>.
        Venue::create(['name' => 'Ace of Spades Lounge', 'address' => '1 Card Street']);

        $player = User::factory()->create([
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'approval_status' => 'pending',
        ]);

        $html = $this->followRedirects(
            $this->actingAs($this->admin())
                ->from(route('users.index'))
                ->patch(route('users.approve', $player))
        )->getContent();

        // Markers survive only inside data-confirm, which is read by script.
        $withoutAttributes = preg_replace('/data-confirm="[^"]*"/', '', $html);

        $this->assertStringNotContainsString(EMPH, $withoutAttributes);
    }
}
