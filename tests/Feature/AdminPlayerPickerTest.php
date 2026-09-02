<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The administrator's player picker on the tournament details page, and the
 * hooks that order this page's panels on a narrow screen.
 *
 * It replaced a <select> of every eligible player, which could not be searched
 * past the browser's type-to-jump -- that matches the start of the label only,
 * so an administrator who knew a nickname or an email address had no way in.
 *
 * The filtering itself is Alpine and cannot be asserted here; it was verified
 * by seeding the query and screenshotting both a match and a miss. What IS
 * server-side is everything the filter reads and everything that still works
 * without it: which players are offered at all, the haystack each row carries,
 * and the confirmation on each row's form.
 */
class AdminPlayerPickerTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 30',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Picker Invitational',
            'scheduled_at' => now()->addDays(3),
            'start_time' => now()->addDays(4),
            'venue_id' => Venue::create(['name' => 'Picker Hall', 'address' => '3 Pick Street'])->id,
            'season_id' => $season->id,
        ]);
    }

    public function test_the_picker_offers_a_row_per_eligible_player(): void
    {
        $tournament = $this->tournament();
        $candidate = User::factory()->create([
            'first_name' => 'Ada', 'last_name' => 'Lovelace',
            'nickname' => 'Countess', 'email' => 'ada@analytical.test',
        ]);

        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('tournaments.show', $tournament))->assertOk();

        $response->assertSee('picker__btn', false);
        $response->assertSee('Ada Lovelace (Countess)');
        $response->assertSee('ada@analytical.test');

        // The row carries its own haystack: name, nickname and email, lowered.
        $response->assertSee(
            'data-search="ada lovelace countess ada@analytical.test"',
            false
        );

        // And the confirmation, which is the whole point of choosing a row.
        // Matched with its attribute name: asserting the message alone passed
        // when data-confirm was renamed to something the handler ignores, since
        // the text was still sitting there in the markup.
        $response->assertSee(
            'data-confirm="Register Ada Lovelace for Picker Invitational?"',
            false
        );

        $this->assertNotNull($candidate->id);
    }

    public function test_an_already_registered_player_is_not_offered(): void
    {
        // register() refuses a duplicate, so offering one is offering a button
        // that fails.
        $tournament = $this->tournament();
        $registered = User::factory()->create(['first_name' => 'Already', 'last_name' => 'In']);

        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $registered->id,
            'player_name' => 'Already In',
            'registered_at' => now(),
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('data-search="already in', false);
    }

    public function test_an_unapproved_player_is_not_offered(): void
    {
        // Same reasoning: register() refuses an unapproved target.
        $tournament = $this->tournament();
        User::factory()->create([
            'first_name' => 'Pending', 'last_name' => 'Person',
            'approval_status' => 'pending',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('data-search="pending person', false);
    }

    public function test_the_picker_says_so_when_everyone_is_registered(): void
    {
        // The admin is the only user, and registering them empties the list.
        $tournament = $this->tournament();
        $admin = User::factory()->create(['is_admin' => true]);

        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $admin->id,
            'player_name' => 'The Admin',
            'registered_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSee('Everyone is registered')
            ->assertDontSee('picker__btn', false);
    }

    public function test_a_player_never_sees_the_picker(): void
    {
        $tournament = $this->tournament();
        User::factory()->create(['first_name' => 'Some', 'last_name' => 'Body']);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('tournaments.show', $tournament))->assertOk()
            ->assertDontSee('picker__btn', false);
    }

    public function test_the_panels_carry_the_hooks_that_order_them(): void
    {
        // Below 60rem the .l-sidebar columns dissolve and these cards are
        // ordered individually, so the register panel sits above the list of
        // players it adds to instead of below all of them. The order is CSS and
        // cannot be asserted here; these class names are what it keys off, and
        // losing one silently restores the old order on phones while the page
        // stays perfect on a desktop.
        $tournament = $this->tournament();
        User::factory()->create();

        // Points at Stake renders only for an upcoming tournament that has a
        // points structure to show. Without this row the card is absent and its
        // hook cannot be asserted -- which is how this test first failed.
        PointsStructure::create(['place' => 1, 'points' => 100]);

        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('tournaments.show', $tournament))->assertOk();

        $response->assertSee('tshow__panels', false);

        foreach (['tshow__register', 'tshow__players', 'tshow__points'] as $hook) {
            $response->assertSee($hook, false);
        }
    }
}
