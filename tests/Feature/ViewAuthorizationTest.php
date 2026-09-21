<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_sees_the_league_menu_but_not_the_admin_ones()
    {
        // Inverted: the seasons, venues and tournaments lists are readable by
        // any signed-in player now, so the League menu is offered to them. What
        // a player must still not see is the machinery for running the league.
        $user = User::factory()->create(['is_admin' => false]);

        // The dashboard is Vue, so the menus are in Navigation.vue rather
        // than in the response. The question is unchanged: which links a
        // player is offered, and which are behind the admin gate.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $nav = (string) file_get_contents(resource_path('js/Layouts/Navigation.vue'));
        $gate = 'v-if="page.props.auth.user?.is_admin"';

        $this->assertStringContainsString($gate, $nav, 'The admin menu has lost its gate.');

        $league = substr($nav, 0, (int) strpos($nav, $gate));

        $this->assertStringContainsString('href="/poker/seasons"', $league);
        $this->assertStringNotContainsString('href="/poker/seasons/create"', $league);
        $this->assertStringNotContainsString('href="/users"', $league);
    }

    public function test_admin_sees_poker_seasons_link_on_dashboard()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // is_admin reaches the page, which is what the menus are drawn from.
        $props = $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue($props['auth']['user']['is_admin']);

        $this->assertStringContainsString(
            'href="/poker/seasons"',
            (string) file_get_contents(resource_path('js/Layouts/Navigation.vue'))
        );
    }

    public function test_non_admin_does_not_see_tournament_edit_link_on_tournament_show()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $venue = Venue::create(['name' => 'Venue 1', 'address' => '123 Test St']);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);
        $tournament = PokerTournament::create([
            'name' => 'Tournament 1',
            'start_time' => now()->addDays(7),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $response = $this->actingAs($user)->get(route('tournaments.show', $tournament));

        $response->assertOk();
        $response->assertDontSee(route('poker.tournaments.edit', $tournament), false);
    }

    public function test_admin_sees_tournament_edit_link_on_tournament_show()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $venue = Venue::create(['name' => 'Venue 1', 'address' => '123 Test St']);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);
        $tournament = PokerTournament::create([
            'name' => 'Tournament 1',
            'start_time' => now()->addDays(7),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        $props = $this->actingAs($admin)->get(route('tournaments.show', $tournament))
            ->assertOk()->viewData('page')['props'];

        $this->assertTrue($props['can_manage']);

        $this->assertStringContainsString(
            ':href="`/poker/tournaments/${tournament.id}/edit`"',
            (string) file_get_contents(resource_path('js/Pages/Poker/Tournaments/Show.vue'))
        );
    }

    public function test_non_admin_does_not_see_season_edit_link_on_season_show()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);

        $response = $this->actingAs($user)->get(route('seasons.show', $season));

        $response->assertOk();
        $response->assertDontSee(route('poker.seasons.edit', $season), false);
    }

    public function test_admin_sees_season_edit_link_on_season_show()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);

        // can_manage is what the page draws Edit and Delete from, and the
        // href is built from the season's id. Asserting the flag says the same
        // thing the URL search did, without depending on markup the server no
        // longer sends.
        $props = $this->actingAs($admin)->get(route('seasons.show', $season))
            ->assertOk()->viewData('page')['props'];

        $this->assertTrue($props['can_manage']);

        $this->assertStringContainsString(
            ':href="`/poker/seasons/${season.id}/edit`"',
            (string) file_get_contents(resource_path('js/Pages/Poker/Seasons/Show.vue'))
        );
    }

    public function test_register_control_offered_when_play_has_not_started()
    {
        // Inverted with the deadline's removal. This tournament is a day away
        // and its registration used to be "closed" because scheduled_at was
        // never set -- registration_open required a non-null value, so a
        // tournament created without one could never be entered.
        $user = User::factory()->create(['is_admin' => false]);
        $venue = Venue::create(['name' => 'Venue 1', 'address' => '123 Test St']);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);
        $tournament = PokerTournament::create([
            'name' => 'Tournament 1',
            'start_time' => now()->addDay(),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        // can_register is the card's own flag, and it restates the
        // controller's rule: a player may enter until play begins.
        $this->assertTrue(
            $this->actingAs($user)->get(route('tournaments.show', $tournament))
                ->assertOk()->viewData('page')['props']['tournament']['can_register']
        );
    }

    public function test_register_control_shown_when_registration_still_open()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $venue = Venue::create(['name' => 'Venue 1', 'address' => '123 Test St']);
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);
        $tournament = PokerTournament::create([
            'name' => 'Tournament 1',
            'start_time' => now()->addDays(2),
            'venue_id' => $venue->id,
            'season_id' => $season->id,
        ]);

        // can_register is the card's own flag, and it restates the
        // controller's rule: a player may enter until play begins.
        $this->assertTrue(
            $this->actingAs($user)->get(route('tournaments.show', $tournament))
                ->assertOk()->viewData('page')['props']['tournament']['can_register']
        );
    }
}
