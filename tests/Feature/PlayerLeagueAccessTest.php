<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * What a player may see of the league's records.
 *
 * Seasons, venues and tournaments used to be entirely behind the admin gate,
 * because the only reason to open them was to edit something. A player has a
 * reason to read them -- where the league plays, what has been scheduled, how a
 * season went -- so the three LISTS are open and everything that changes a
 * record is not.
 *
 * Venues are the exception within the exception. A player may see that a venue
 * exists, and nothing more: the venue detail page carries takings, leaderboards
 * and per-player point histories, so it stays shut and the row offers no way in.
 */
class PlayerLeagueAccessTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private function player(): User
    {
        return User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);
    }

    private function seedLeague(): void
    {
        $this->tournament('Autumn Showdown');
    }

    /** @return array<string, array{0: string}> */
    public static function openLists(): array
    {
        return [
            'seasons' => ['poker.seasons.index'],
            'venues' => ['poker.venues.index'],
            'tournaments' => ['poker.tournaments.index'],
        ];
    }

    #[DataProvider('openLists')]
    public function test_a_player_can_read_the_league_lists(string $route): void
    {
        $this->seedLeague();

        $this->actingAs($this->player())->get(route($route))->assertOk();
    }

    #[DataProvider('openLists')]
    public function test_a_guest_is_still_sent_to_the_login_page(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    public function test_a_player_can_read_a_season(): void
    {
        $this->seedLeague();

        $this->actingAs($this->player())
            ->get(route('seasons.show', PokerSeason::first()))->assertOk();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function closedRoutes(): array
    {
        return [
            'create a season' => ['get', 'poker.seasons.create'],
            'create a venue' => ['get', 'poker.venues.create'],
            'create a tournament' => ['get', 'poker.tournaments.create'],
            'the venue detail page' => ['get', 'poker.venues.show'],
            'edit a venue' => ['get', 'poker.venues.edit'],
            'edit a season' => ['get', 'poker.seasons.edit'],
            'edit a tournament' => ['get', 'poker.tournaments.edit'],
        ];
    }

    #[DataProvider('closedRoutes')]
    public function test_a_player_cannot_reach_what_changes_a_record(string $verb, string $route): void
    {
        $this->seedLeague();

        $parameters = match (true) {
            str_contains($route, 'venues') && ! str_contains($route, 'create') => [Venue::first()],
            str_contains($route, 'seasons') && ! str_contains($route, 'create') => [PokerSeason::first()],
            str_contains($route, 'tournaments') && ! str_contains($route, 'create') => [PokerTournament::first()],
            default => [],
        };

        $this->actingAs($this->player())
            ->{$verb}(route($route, $parameters))
            ->assertForbidden();
    }

    public function test_a_player_cannot_delete_a_season(): void
    {
        // The destructive verbs matter most and a data provider hides them, so
        // this one is spelled out.
        $this->seedLeague();

        $this->actingAs($this->player())
            ->delete(route('poker.seasons.destroy', PokerSeason::first()))
            ->assertForbidden();

        $this->assertSame(1, PokerSeason::count());
    }

    public function test_a_player_cannot_create_a_venue(): void
    {
        $this->seedLeague();

        $this->actingAs($this->player())
            ->post(route('poker.venues.store'), ['name' => 'Back Room', 'address' => '2 Card St'])
            ->assertForbidden();

        $this->assertSame(1, Venue::count());
    }

    public function test_the_seasons_list_offers_a_player_nothing_to_change(): void
    {
        $this->seedLeague();

        $html = $this->actingAs($this->player())
            ->get(route('poker.seasons.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Create Season', $html);
        $this->assertStringNotContainsString(route('poker.seasons.create'), $html);
        $this->assertStringNotContainsString('title="Edit"', $html);
        $this->assertStringNotContainsString('title="Delete"', $html);

        // Reading a season is the reason a player is here at all.
        $this->assertStringContainsString(route('seasons.show', PokerSeason::first()), $html);
    }

    public function test_the_tournaments_list_offers_a_player_nothing_to_change(): void
    {
        $this->seedLeague();

        $html = $this->actingAs($this->player())
            ->get(route('poker.tournaments.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Schedule Tournament', $html);
        $this->assertStringNotContainsString('title="Edit"', $html);
        $this->assertStringNotContainsString('title="Delete"', $html);

        $this->assertStringContainsString(route('tournaments.show', PokerTournament::first()), $html);
    }

    public function test_the_venue_list_offers_a_player_no_way_in_at_all(): void
    {
        // Not even View Stats. The venue detail page is admin-only, so a link
        // to it is a link to a 403.
        $this->seedLeague();

        $html = $this->actingAs($this->player())
            ->get(route('poker.venues.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Add Venue', $html);
        $this->assertStringNotContainsString(route('poker.venues.show', Venue::first()), $html);
        $this->assertStringNotContainsString('title="Edit"', $html);
        $this->assertStringNotContainsString('title="Delete"', $html);

        // The venue itself is still named -- that is the point of the page.
        $this->assertStringContainsString(Venue::first()->name, $html);
    }

    public function test_an_admin_still_gets_every_control(): void
    {
        // The other half. Without it, hiding everything from everybody passes
        // all four assertions above.
        $this->seedLeague();

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('poker.seasons.index'))->assertOk()
            ->assertSee('Create Season')->assertSee('title="Delete"', false);

        $this->actingAs($admin)->get(route('poker.tournaments.index'))->assertOk()
            ->assertSee('Schedule Tournament')->assertSee('title="Delete"', false);

        $this->actingAs($admin)->get(route('poker.venues.index'))->assertOk()
            ->assertSee('Add Venue')
            ->assertSee(route('poker.venues.show', Venue::first()), false);
    }

    public function test_the_league_menu_is_offered_to_a_player(): void
    {
        $this->seedLeague();

        $html = $this->actingAs($this->player())
            ->get(route('dashboard'))->assertOk()->getContent();

        // Access with no way to navigate to it is half a feature.
        $this->assertStringContainsString(route('poker.seasons.index'), $html);
        $this->assertStringContainsString(route('poker.venues.index'), $html);
        $this->assertStringContainsString(route('poker.tournaments.index'), $html);
    }

    public function test_the_admin_menus_are_not(): void
    {
        $this->seedLeague();

        $html = $this->actingAs($this->player())
            ->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('poker.results.index'), $html);
        $this->assertStringNotContainsString(route('poker.registrants.index'), $html);
        $this->assertStringNotContainsString(route('users.index'), $html);
        $this->assertStringNotContainsString(route('sponsors.index'), $html);
    }
}
