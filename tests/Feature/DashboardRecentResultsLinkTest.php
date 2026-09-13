<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * Recent Results rows lead to the tournament they describe.
 *
 * The card named five tournaments a player had played and gave no way to reach
 * any of them -- the one page that would say who else was there, what the field
 * was, and where the points came from.
 *
 * The row is the link rather than the name in it, which is a claim about the
 * markup these tests can check and about the click target they cannot. The
 * target was measured separately with elementFromPoint at 500px, 900px and
 * 1440px: every cell in the row resolves to the anchor.
 */
class DashboardRecentResultsLinkTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private function player(): User
    {
        return User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);
    }

    /** The Recent Results card's markup, and nothing else on the page. */
    private function card(string $html): string
    {
        $at = strpos($html, 'Recent Results');
        $this->assertNotFalse($at, 'The Recent Results card is not on the dashboard.');

        $rest = substr($html, $at);
        $end = strpos($rest, 'Active Season');

        return $end === false ? $rest : substr($rest, 0, $end);
    }

    public function test_a_row_links_to_its_own_tournament(): void
    {
        $player = $this->player();
        $tournament = $this->tournament('Autumn Showdown');
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        $card = $this->card(
            $this->actingAs($player)->get(route('dashboard'))->assertOk()->getContent()
        );

        $this->assertStringContainsString(route('tournaments.show', $tournament), $card);
        $this->assertStringContainsString('table__row--link', $card);
    }

    public function test_each_row_links_to_the_tournament_it_names(): void
    {
        // Two rows, so a link built from the wrong variable -- the first result,
        // or the current tournament -- shows up as both rows pointing at one
        // tournament.
        $player = $this->player();

        $first = $this->tournament('Autumn Showdown');
        $second = $this->tournament('Winter Classic');

        foreach ([[$first, 1, 100], [$second, 2, 85]] as [$tournament, $place, $points]) {
            $this->enter($tournament, $player);
            $this->score($tournament, $player, $place, $points);
        }

        $card = $this->card(
            $this->actingAs($player)->get(route('dashboard'))->assertOk()->getContent()
        );

        foreach ([$first, $second] as $tournament) {
            $this->assertSame(
                1,
                substr_count($card, route('tournaments.show', $tournament)),
                $tournament->name.' is not linked exactly once.'
            );
        }
    }

    public function test_a_row_is_one_link_rather_than_one_per_cell(): void
    {
        // The reason the anchor is stretched instead of repeated. Nothing here
        // underlines an <a> -- the reset makes that opt-in -- so three anchors
        // would look identical to one and read as three to a screen reader,
        // with five rows announcing fifteen links to the same five pages.
        $player = $this->player();
        $tournament = $this->tournament('Autumn Showdown');
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        $card = $this->card(
            $this->actingAs($player)->get(route('dashboard'))->assertOk()->getContent()
        );

        $row = substr($card, (int) strpos($card, 'table__row--link'));
        $row = substr($row, 0, (int) strpos($row, '</tr>'));

        $this->assertSame(1, substr_count($row, '<a '), 'The row carries more than one anchor.');
    }

    public function test_the_link_is_not_underlined_like_body_copy(): void
    {
        // .link is this system's opt-in underline. The row is a row, not a
        // sentence with a link in it, and the venue page learned the same
        // lesson: ten linked rows read as thirty links when every piece of text
        // in them is underlined.
        $player = $this->player();
        $tournament = $this->tournament('Autumn Showdown');
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        $row = $this->card(
            $this->actingAs($player)->get(route('dashboard'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('table__link', $row);
        $this->assertStringNotContainsString('class="link"', $row);
    }

    public function test_a_player_with_no_results_still_gets_the_card(): void
    {
        $card = $this->card(
            $this->actingAs($this->player())->get(route('dashboard'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('No result data recorded yet.', $card);
        $this->assertStringNotContainsString('table__row--link', $card);
    }

    public function test_the_link_survives_a_tournament_a_player_never_registered_for(): void
    {
        // Results are created from the results screen without a registration,
        // and the dashboard reads results rather than registrations. The row
        // still has a tournament to point at.
        $player = $this->player();
        $tournament = $this->tournament('Autumn Showdown');
        $this->score($tournament, $player, 3, 75);

        $card = $this->card(
            $this->actingAs($player)->get(route('dashboard'))->assertOk()->getContent()
        );

        $this->assertStringContainsString(route('tournaments.show', $tournament), $card);
    }
}
