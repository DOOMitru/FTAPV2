<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerTournamentResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * One panel where there were two.
 *
 * Final Standings and Registered Players listed the same people in the same
 * order and differed only in what they put beside a name -- a medal and a
 * points total on one, a monogram and the controls on the other -- so a player
 * appeared twice and an administrator read down one list to find a name and
 * across to the other to act on it.
 *
 * These tests are mostly about what the merge had to carry over. A merge is
 * subtractive by nature, and the way it goes wrong is quietly dropping one
 * side's feature rather than failing.
 */
class MergedStandingsPanelTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private function player(string $first, string $last): User
    {
        return User::factory()->create([
            'first_name' => $first, 'last_name' => $last, 'approval_status' => 'approved',
        ]);
    }

    private function panel(string $html): string
    {
        $at = strpos($html, 'tshow__players');
        $this->assertNotFalse($at, 'The standings card is not on the page.');

        return substr($html, $at, (int) (strpos($html, 'Admin: Register', $at) ?: strlen($html)) - $at);
    }

    public function test_there_is_only_one_list_of_players(): void
    {
        // The point of the merge. A player used to be printed twice on a played
        // tournament: once in the standings table, once in the registrants
        // list.
        $tournament = $this->tournament();
        $player = $this->player('Wanda', 'Reeve');
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        $html = $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()->getContent();

        // Counted as ROWS, not as occurrences of the name in the document. The
        // register dialog carries every approved player in its x-data payload,
        // so a page-wide substring count says two and means nothing -- one of
        // them is data in an attribute, not a list the reader sees.
        // The title holds an <a> now -- the name links to that player's figures
        // -- so the capture spans elements and the tags come off afterwards.
        // [^<]+ matched bare text and silently found none.
        preg_match_all('/<div class="entry__title">(.*?)<\/div>/s', $html, $matches);
        $rows = array_filter(
            array_map(fn ($cell) => trim(strip_tags($cell)), $matches[1]),
            fn ($n) => $n === 'Wanda Reeve'
        );

        $this->assertCount(1, $rows, 'The player is listed twice.');
    }

    public function test_a_finisher_keeps_the_medal_from_the_standings_table(): void
    {
        $tournament = $this->tournament();
        $player = $this->player('Wanda', 'Reeve');
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        $panel = $this->panel($this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()->getContent());

        $this->assertStringContainsString('rank--1', $panel, 'The gold medal did not survive the merge.');
        $this->assertStringContainsString('100', $panel, 'The points did not survive the merge.');
    }

    public function test_a_player_still_in_keeps_the_monogram(): void
    {
        $tournament = $this->tournament();
        $this->enter($tournament, $this->player('Wanda', 'Reeve'));

        $panel = $this->panel($this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()->getContent());

        $this->assertStringContainsString('monogram', $panel);
        $this->assertStringContainsString('>WR<', $panel);
    }

    public function test_the_place_is_stated_once(): void
    {
        // The duplication the merge exists to remove: the row's leading glyph
        // IS the place, so the trailing badge carries points alone. "3rd" in
        // the badge as well would be the old two-panel redundancy moved inside
        // one row.
        $tournament = $this->tournament();
        $players = [$this->player('A', 'One'), $this->player('B', 'Two'), $this->player('C', 'Three')];

        foreach ($players as $p) {
            $this->enter($tournament, $p);
        }

        $this->score($tournament, $players[2], 3, 75);

        $panel = $this->panel($this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()->getContent());

        $this->assertStringContainsString('rank--3', $panel);
        $this->assertStringNotContainsString('3rd', $panel, 'The place is printed twice in one row.');
    }

    public function test_eliminate_is_still_offered_for_a_player_still_in(): void
    {
        $tournament = $this->tournament();
        $this->enter($tournament, $this->player('Wanda', 'Reeve'));

        $this->actingAs($this->admin())->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()->assertSee('Eliminate');
    }

    public function test_eliminate_is_not_offered_to_someone_already_out(): void
    {
        $tournament = $this->tournament();
        $player = $this->player('Wanda', 'Reeve');
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        $this->actingAs($this->admin())->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()->assertDontSee('Eliminate');
    }

    public function test_remove_survives_and_is_now_gated_per_player(): void
    {
        // This asserted that ONE result took Remove off every row, which is
        // what the rule used to be. It is per player now: somebody else's
        // finish is none of this player's business, and only publishing closes
        // the field for everyone.
        $tournament = $this->tournament();
        $stillIn = $this->player('Wanda', 'Reeve');
        $this->enter($tournament, $stillIn);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()->assertSee('Remove from tournament');

        // Another player finishes. Wanda is still in, so her row keeps it.
        $other = $this->player('Other', 'Player');
        $this->enter($tournament, $other);
        $this->score($tournament, $other, 2, 85);

        $this->actingAs($admin)->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()
            ->assertSee('Remove from tournament')
            ->assertDontSee('entries locked');

        $tournament->forceFill(['published_at' => now()])->save();

        $this->actingAs($admin)->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()
            ->assertDontSee('Remove from tournament')
            ->assertSee('Results published · field locked', false);
    }

    public function test_an_eliminated_row_is_offered_no_remove_control(): void
    {
        // The gate that the first version of this change left out: the remove
        // block is its own @if rather than a branch of the chain that draws the
        // points badge, so without ! $result an eliminated player got both.
        $tournament = $this->tournament();
        $out = $this->player('Ousted', 'Player');
        $this->enter($tournament, $out);
        $this->score($tournament, $out, 1, 100);

        $registrant = $tournament->registrants()->where('user_id', $out->id)->firstOrFail();

        $this->actingAs($this->admin())->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()
            ->assertDontSee(route('poker.registrants.destroy', $registrant), false);
    }

    public function test_a_finish_with_no_registration_behind_it_is_still_shown(): void
    {
        // Results can be created from the results screen without a
        // registration, and those rows only ever appeared in Final Standings.
        // Merging the two lists on registrants alone would have deleted them
        // from the page.
        $tournament = $this->tournament();
        $this->enter($tournament, $this->player('Wanda', 'Reeve'));

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'player_name' => 'Unregistered Fernandez',
            'place' => 4,
            'points' => 187,
        ]);

        $panel = $this->panel($this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()->getContent());

        $this->assertStringContainsString('Unregistered Fernandez', $panel);
        $this->assertStringContainsString('187', $panel);
    }

    public function test_an_unregistered_finish_is_offered_no_remove_control(): void
    {
        // Honest about its mechanism: what stops the form here is the
        // results gate, not the missing registration. A row without a
        // registrant can only exist because a result exists, and any result at
        // all closes Remove for every row -- so the two can never disagree, and
        // the `$row['registrant']` check in the view is unreachable today.
        //
        // It stays as the guard on the row's shape rather than on the
        // tournament's state: the route needs a registrant, and a row built
        // from a result alone has none. Mutating it away does not fail this
        // test, and that is recorded here rather than left for someone to
        // rediscover.
        $tournament = $this->tournament();

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'player_name' => 'Unregistered Fernandez',
            'place' => 4, 'points' => 187,
        ]);

        $this->actingAs($this->admin())->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()->assertDontSee('Remove from tournament');
    }

    public function test_the_heading_follows_what_is_in_the_list(): void
    {
        $tournament = $this->tournament();
        $players = [$this->player('A', 'One'), $this->player('B', 'Two')];

        foreach ($players as $p) {
            $this->enter($tournament, $p);
        }

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()->assertSee('Registered Players');

        $this->score($tournament, $players[0], 2, 85);

        // Played, but not finished: standings, and not FINAL standings.
        $this->actingAs($admin)->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()->assertSee('Standings')->assertDontSee('Final Standings');

        $this->score($tournament, $players[1], 1, 100);

        $this->actingAs($admin)->get(route('tournaments.show', $tournament->fresh()))
            ->assertOk()->assertSee('Final Standings');
    }

    public function test_a_player_sees_the_list_without_the_controls(): void
    {
        $tournament = $this->tournament();
        $player = $this->player('Wanda', 'Reeve');
        $this->enter($tournament, $player);

        $this->actingAs($player)->get(route('tournaments.show', $tournament->fresh()))->assertOk()
            ->assertSee('Wanda Reeve')
            ->assertDontSee('Eliminate')
            ->assertDontSee('Remove from tournament');
    }

    public function test_the_page_no_longer_prints_the_points_table(): void
    {
        // Points at Stake listed every paying place beside an upcoming
        // tournament. The figure that matters is the one on offer for the next
        // place, and the Eliminate confirmation quotes it at the moment it is
        // awarded -- so the table was a standing reference next to a page about
        // one night.
        PointsStructure::create(['place' => 1, 'points' => 100]);
        PointsStructure::create(['place' => 2, 'points' => 85]);

        $tournament = $this->tournament();
        $tournament->forceFill(['start_time' => now()->addWeek()])->save();
        $this->enter($tournament, $this->player('Wanda', 'Reeve'));

        $html = $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()->getContent();

        $this->assertStringNotContainsString('Points at Stake', $html);
        $this->assertStringNotContainsString('Points are based on league rules.', $html);
        $this->assertStringNotContainsString('tshow__points', $html);

        // The points on offer are still named where they are acted on: the
        // confirmation on the Eliminate button. One player is registered -- the
        // admin is not -- so the place on offer is 1st and it pays 100.
        $this->assertStringContainsString('are awarded 100 points', $html);
    }

    public function test_the_page_carries_no_stat_tiles(): void
    {
        // Final Results, Avg Points and Points Pot. Two of the three restated
        // what the list below them already showed row by row, and the third was
        // a number nobody acts on.
        $tournament = $this->tournament();
        $player = $this->player('Wanda', 'Reeve');
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()
            ->assertDontSee('Final Results')
            ->assertDontSee('Avg Points')
            ->assertDontSee('Points Pot');
    }

    public function test_the_register_trigger_keeps_its_words(): void
    {
        // Its accessible name. On a phone the label is CLIPPED so the button is
        // just its plus sign, and clipping is the whole point: display:none
        // would leave a control announced as nothing at all.
        //
        // Which of the two is drawn is CSS and was measured instead: the button
        // is 173px wide at 1440 and 32x32 at 375 and 320 -- the same box as the
        // .action delete beside every player -- with innerText still reading
        // "REGISTER PLAYERS" at every width.
        $tournament = $this->tournament();

        $html = $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament))->assertOk()->getContent();

        $this->assertStringContainsString(
            '<span class="tshow__register-label">Register players</span>',
            $html
        );
    }

    public function test_the_stat_tile_data_is_no_longer_computed_for_the_view(): void
    {
        // The tiles were the only readers. Left in compact() they would be a
        // sum over every result on the page, recomputed on every load, for
        // nobody.
        $tournament = $this->tournament();

        $response = $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament))->assertOk();

        // The data array itself: viewData() asserts the key is there, which is
        // the opposite of the question.
        $data = $response->original->getData();

        $this->assertArrayNotHasKey('totalPoints', $data);
        $this->assertArrayNotHasKey('resultsCount', $data);

        // And the keys the page still needs are still there, so this is not
        // passing because the view stopped receiving anything.
        $this->assertArrayHasKey('standings', $data);
        $this->assertArrayHasKey('registrantsCount', $data);
    }
}
