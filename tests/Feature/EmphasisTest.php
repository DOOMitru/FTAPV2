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
            ->get(route('seasons.show', PokerSeason::firstOrFail()))->assertOk()->getContent();

        // In the attribute it is still text with markers round it: confirm.ts
        // does the bolding in the browser, out of text nodes.
        $this->assertStringContainsString(EMPH.'Autumn 2026'.EMPH, $html);
    }

    public function test_a_venue_name_is_bolded_in_its_delete_confirmation(): void
    {
        Venue::create(['name' => 'Ace of Spades Lounge', 'address' => '1 Card Street']);

        $html = $this->actingAs($this->admin())
            ->get(route('poker.venues.show', Venue::firstOrFail()))->assertOk()->getContent();

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

        // The markers reach the page as DATA and are turned into <strong> in
        // the browser, so the rendered bold cannot be read off the response.
        // Both halves are still checked: the flash arrives marked, and <Emph>
        // is what renders a marked run -- the same rule confirm.ts applies for
        // the Blade half, from the same definition in emph.ts.
        $flash = $this->followRedirects($response)->viewData('page')['props']['flash']['status'];

        $this->assertStringContainsString(EMPH.'Ada Lovelace'.EMPH, (string) $flash);

        $emph = file_get_contents(resource_path('js/Components/Emph.vue'));

        $this->assertStringContainsString('<strong v-if="part.marked" class="emph">', $emph);
    }

    public function test_a_name_with_markup_in_it_survives_a_real_flash_message(): void
    {
        // End to end, with the input that matters: the name reaches the page
        // bolded and escaped, and nothing it contains becomes an element.
        $player = User::factory()->create([
            'first_name' => '<b>Ada', 'last_name' => 'Lovelace</b>', 'approval_status' => 'pending',
        ]);

        $response = $this->followRedirects(
            $this->actingAs($this->admin())
                ->from(route('users.index'))
                ->patch(route('users.approve', $player))
        );

        $html = $response->getContent();

        // The name stays DATA. It travels in the props as the string it is --
        // angle brackets and all -- and reaches the document escaped, so
        // nothing it contains becomes an element.
        //
        // Not asserted as a literal byte sequence: Inertia serialises the props
        // as JSON, which escapes the closing tag's slash, so the page carries
        // `&lt;\/b&gt;` rather than `&lt;/b&gt;`. Checking for the escaped
        // opening tag and the absence of the raw one says the same thing
        // without depending on JSON's escaping rules.
        $flash = $response->viewData('page')['props']['flash']['status'];

        $this->assertStringContainsString('<b>Ada Lovelace</b>', (string) $flash,
            'The name must survive as data, exactly as it was typed.');

        // The security claim, stated as the thing that must NOT happen. The
        // positive form -- looking for the escaped bytes -- depends on how
        // Inertia serialises its payload, which is its business and not this
        // rule's.
        $this->assertStringNotContainsString('<b>Ada', $html,
            'The name reached the document as markup rather than as text.');

        // And Vue interpolates rather than parsing: <Emph> renders text nodes,
        // never v-html, which is the guarantee this test exists for.
        //
        // Comments stripped first. The component EXPLAINS that it never uses
        // v-html, so the words are in the file while the directive is not --
        // the third assertion in this conversion to trip on prose describing
        // the very thing it forbids.
        $emph = (string) preg_replace(
            ['/<!--.*?-->/s', '/\/\*.*?\*\//s'],
            ' ',
            (string) file_get_contents(resource_path('js/Components/Emph.vue'))
        );

        $this->assertStringNotContainsString('v-html', $emph);
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
