<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three league listings are lists of links.
 *
 * A row used to carry View, Edit and Delete as three icons. Reading a record
 * is what a listing is for, so the row itself is the way in; changing one is
 * offered on the record's own page, where whoever is about to change it can
 * see what they are changing.
 *
 * The rule that is easy to get wrong here: a row must only be a link for
 * somebody who can follow it. poker.venues.show is admin-only while its index
 * is not, so a linked row would hand a player a 403 -- a fault this project
 * has shipped three times, on the event card, the kebab and the archive cards.
 */
class IndexRowLinksTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    private function player(): User
    {
        return User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);
    }

    private function seedLeague(): array
    {
        $season = PokerSeason::create([
            'name' => 'Season 9', 'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(), 'is_current' => true,
        ]);

        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '1 St']);

        $tournament = PokerTournament::create([
            'name' => 'Wednesday Night Poker', 'start_time' => now()->subDays(3),
            'venue_id' => $venue->id, 'season_id' => $season->id,
        ]);

        return [$season, $venue, $tournament];
    }

    public function test_each_listing_makes_its_rows_links(): void
    {
        [$season, $venue, $tournament] = $this->seedLeague();
        $admin = $this->admin();

        foreach ([
            'poker.seasons.index' => route('seasons.show', $season),
            'poker.venues.index' => route('poker.venues.show', $venue),
            'poker.tournaments.index' => route('tournaments.show', $tournament),
        ] as $listing => $destination) {
            $html = $this->actingAs($admin)->get(route($listing))->assertOk()->getContent();

            $this->assertStringContainsString('table__row--link', $html, $listing.' has no linked row.');
            $this->assertStringContainsString('class="table__link"', $html, $listing.' has no stretched anchor.');
            $this->assertStringContainsString($destination, $html, $listing.' does not reach its record.');
        }
    }

    public function test_no_listing_still_carries_row_controls(): void
    {
        $this->seedLeague();
        $admin = $this->admin();

        foreach (['poker.seasons.index', 'poker.venues.index', 'poker.tournaments.index'] as $listing) {
            $html = $this->actingAs($admin)->get(route($listing))->assertOk()->getContent();

            $this->assertStringNotContainsString('table__actions', $html, $listing.' still has an actions column.');
            $this->assertStringNotContainsString('title="Edit"', $html, $listing.' still edits from a row.');
            $this->assertStringNotContainsString('title="Delete"', $html, $listing.' still deletes from a row.');
        }
    }

    public function test_each_record_page_offers_edit_and_delete(): void
    {
        [$season, $venue, $tournament] = $this->seedLeague();
        $admin = $this->admin();

        foreach ([
            route('seasons.show', $season) => [
                route('poker.seasons.edit', $season), route('poker.seasons.destroy', $season),
            ],
            route('poker.venues.show', $venue) => [
                route('poker.venues.edit', $venue), route('poker.venues.destroy', $venue),
            ],
            route('tournaments.show', $tournament) => [
                route('poker.tournaments.edit', $tournament), route('poker.tournaments.destroy', $tournament),
            ],
        ] as $page => $controls) {
            $html = $this->actingAs($admin)->get($page)->assertOk()->getContent();

            // Matched as whole attributes. route(...destroy) is a PREFIX of
            // route(...edit) -- /poker/tournaments/{id} against
            // /poker/tournaments/{id}/edit -- so a bare substring search for
            // the destroy URL is satisfied by the Edit link, and this test
            // passed on a page that had no Delete button at all.
            [$edit, $destroy] = $controls;

            $this->assertStringContainsString('href="'.$edit.'"', $html, $page.' is missing Edit.');
            $this->assertStringContainsString('action="'.$destroy.'"', $html, $page.' is missing Delete.');
        }
    }

    public function test_a_player_is_offered_neither_on_a_page_they_can_read(): void
    {
        // seasons.show and tournaments.show are open to anyone signed in, so
        // the controls that moved onto them need the gate the rows had.
        [$season, , $tournament] = $this->seedLeague();
        $player = $this->player();

        foreach ([
            route('seasons.show', $season) => route('poker.seasons.destroy', $season),
            route('tournaments.show', $tournament) => route('poker.tournaments.destroy', $tournament),
        ] as $page => $control) {
            $this->actingAs($player)->get($page)->assertOk()
                ->assertDontSee('action="'.$control.'"', false);
        }
    }

    public function test_a_player_gets_no_link_to_an_admin_only_page(): void
    {
        // The venues listing is readable by a player; the venue page is not.
        [, $venue] = $this->seedLeague();

        $this->actingAs($this->player())->get(route('poker.venues.index'))->assertOk()
            ->assertSee('The Grand Card Room')
            ->assertDontSee(route('poker.venues.show', $venue), false)
            ->assertDontSee('table__row--link', false);
    }

    public function test_deleting_from_the_record_page_still_works(): void
    {
        [$season] = $this->seedLeague();

        $this->actingAs($this->admin())
            ->delete(route('poker.seasons.destroy', $season))
            ->assertRedirect(route('poker.seasons.index'));

        $this->assertDatabaseMissing('seasons', ['id' => $season->id]);
    }
}
