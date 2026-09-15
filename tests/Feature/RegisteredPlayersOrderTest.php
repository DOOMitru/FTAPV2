<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * The order of the standings panel.
 *
 * It read alphabetically by full name, which says nothing about a tournament.
 * The panel is a live standings list: players still in sit at the top, because
 * they are competing for the places above the ones already awarded, and below
 * them the finishers appear in the order they finished -- 1st, then 2nd, then
 * 3rd.
 *
 * Places count DOWN as players go out, so the first player eliminated from a
 * field of ten holds 10th and appears last. That is the point: this is a
 * standings order, not an elimination log.
 *
 * This panel was two -- Final Standings and Registered Players -- so the order
 * had to agree across both. They are one card now and the order is the card's
 * own, which is why the rows are found by .tshow__players rather than by a
 * heading: the heading changes with what is in the list.
 */
class RegisteredPlayersOrderTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    /** The panel's rows, in the order they are rendered. */
    private function rowsFor(string $html): array
    {
        // Anchored on the card's own class. The heading is "Registered
        // Players", "Standings" or "Final Standings" depending on how much of
        // the tournament has been played, and strpos() returns false for a
        // heading that is not there -- which (int) turns into 0, quietly
        // handing back the whole document instead of failing.
        $at = strpos($html, 'tshow__players');
        $this->assertNotFalse($at, 'The standings card is not on the page.');

        $panel = substr($html, $at);
        $panel = substr($panel, 0, strpos($panel, 'Admin: Register') ?: null);

        // The title holds a name that may be an <a> -- a player with an account
        // links to their figures, one without stays plain text -- so the
        // capture spans elements and the tags come off after. [^<]+ matched
        // bare text only, and quietly returned an empty list for every row.
        preg_match_all('/<div class="entry__title">(.*?)<\/div>/s', $panel, $matches);

        return array_map(fn ($cell) => trim(strip_tags($cell)), $matches[1]);
    }

    private function player(string $first, string $last): User
    {
        return User::factory()->create([
            'first_name' => $first, 'last_name' => $last, 'approval_status' => 'approved',
        ]);
    }

    public function test_finishers_are_listed_by_place_best_first(): void
    {
        $tournament = $this->tournament();

        $winner = $this->player('Zoe', 'Abbott');
        $second = $this->player('Adam', 'Zeller');
        $third = $this->player('Mia', 'Nolan');

        foreach ([$winner, $second, $third] as $player) {
            $this->enter($tournament, $player);
        }

        // Deliberately scored out of order, and with names whose alphabetical
        // order is the reverse of their finishing order -- so a lingering
        // sortBy('player_name') cannot pass this.
        $this->score($tournament, $third, 3, 40);
        $this->score($tournament, $winner, 1, 100);
        $this->score($tournament, $second, 2, 60);

        $rows = $this->rowsFor(
            $this->actingAs($this->admin())->get(route('tournaments.show', $tournament->fresh()))
                ->assertOk()->getContent()
        );

        $this->assertSame(['Zoe Abbott', 'Adam Zeller', 'Mia Nolan'], $rows);
    }

    public function test_players_still_in_sit_above_the_finishers(): void
    {
        // Three of five out, holding 5th, 4th and 3rd. The two still playing
        // are competing for 1st and 2nd, so they belong at the top.
        $tournament = $this->tournament();

        $out = [$this->player('Ann', 'Fifth'), $this->player('Ben', 'Fourth'), $this->player('Cal', 'Third')];
        $in = [$this->player('Dee', 'Young'), $this->player('Eli', 'Ash')];

        foreach (array_merge($out, $in) as $player) {
            $this->enter($tournament, $player);
        }

        foreach ([[0, 5, 10], [1, 4, 20], [2, 3, 40]] as [$i, $place, $points]) {
            $this->score($tournament, $out[$i], $place, $points);
        }

        $rows = $this->rowsFor(
            $this->actingAs($this->admin())->get(route('tournaments.show', $tournament->fresh()))
                ->assertOk()->getContent()
        );

        $this->assertSame(
            ['Eli Ash', 'Dee Young', 'Cal Third', 'Ben Fourth', 'Ann Fifth'],
            $rows
        );
    }

    public function test_nobody_eliminated_yet_is_a_list_by_last_name(): void
    {
        $tournament = $this->tournament();

        foreach ([['Zoe', 'Abbott'], ['Adam', 'Zeller'], ['Mia', 'Nolan']] as [$first, $last]) {
            $this->enter($tournament, $this->player($first, $last));
        }

        $rows = $this->rowsFor(
            $this->actingAs($this->admin())->get(route('tournaments.show', $tournament->fresh()))
                ->assertOk()->getContent()
        );

        // Surname order: Abbott, Nolan, Zeller. Full-name order would put Adam
        // Zeller first, so this fails against the old sort.
        $this->assertSame(['Zoe Abbott', 'Mia Nolan', 'Adam Zeller'], $rows);
    }

    public function test_a_registrant_with_no_account_falls_back_to_their_recorded_name(): void
    {
        // user_id is nullable with nullOnDelete, so a deleted player leaves a
        // player_name string and nothing to read a surname from.
        $tournament = $this->tournament();
        $this->enter($tournament, $this->player('Mia', 'Nolan'));

        $orphan = $this->enter($tournament, $this->player('Zoe', 'Abbott'));
        $orphan->forceFill(['user_id' => null, 'player_name' => 'Wanda Reeve'])->save();

        $rows = $this->rowsFor(
            $this->actingAs($this->admin())->get(route('tournaments.show', $tournament->fresh()))
                ->assertOk()->getContent()
        );

        // Nolan before Reeve, so the surname was taken from the stored name.
        $this->assertSame(['Mia Nolan', 'Wanda Reeve'], $rows);
    }

    public function test_a_one_word_name_still_sorts(): void
    {
        $tournament = $this->tournament();
        $this->enter($tournament, $this->player('Mia', 'Nolan'));

        $single = $this->enter($tournament, $this->player('Zoe', 'Abbott'));
        $single->forceFill(['user_id' => null, 'player_name' => 'Cher'])->save();

        $rows = $this->rowsFor(
            $this->actingAs($this->admin())->get(route('tournaments.show', $tournament->fresh()))
                ->assertOk()->getContent()
        );

        $this->assertSame(['Cher', 'Mia Nolan'], $rows);
    }
}
