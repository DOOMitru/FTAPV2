<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared <x-p-event> card, now drawn by three pages.
 *
 * It was extracted so the home and events pages could not drift apart; the
 * tournament details page joined them when its own card turned out to be laid
 * out with .l-sidebar inside a flush card -- the map took two thirds of the
 * width and the details sat in the rest with no padding, against the card's
 * edge, with the podium clipped off the bottom.
 *
 * That page needs two things the public pages do not: no Details button, since
 * it IS the details page, and an Unregister control, which exists nowhere else.
 * Both arrive through the component's props and slots, and both are the kind of
 * thing a later edit to the shared card can quietly drop.
 */
class EventCardTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(string $closesIn = '+3 days'): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 12',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Ironclad Invitational',
            // registration_open reads scheduled_at, not start_time.
            'scheduled_at' => now()->modify($closesIn),
            'start_time' => now()->addDays(4),
            'venue_id' => Venue::create(['name' => 'Ironclad Hall', 'address' => '9 Chip Row'])->id,
            'season_id' => $season->id,
        ]);
    }

    private function register(PokerTournament $tournament, User $user): void
    {
        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'player_name' => $user->first_name.' '.$user->last_name,
            'registered_at' => now(),
        ]);
    }

    public function test_the_details_page_does_not_offer_a_button_back_to_itself(): void
    {
        $tournament = $this->tournament();

        $this->actingAs(User::factory()->create())
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertDontSee('>Details<', false);
    }

    public function test_the_public_card_still_offers_details(): void
    {
        // The other side of the prop. If `details` ever defaults to false the
        // home and events pages lose their only route into a tournament, and
        // the test above would still pass.
        $this->tournament();

        $this->get('/')->assertOk()->assertSee('Details');
    }

    public function test_a_registered_player_is_told_so_on_the_details_page(): void
    {
        // Guards the controller's viewer_registered wiring. The shared card
        // reads that attribute, which the events and home pages load with
        // withExists; the details page computes the same fact under a different
        // name. Without the controller assigning it, the card falls back to
        // "not registered" and offers Register to someone already in the game.
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->register($tournament, $player);

        $this->actingAs($player)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee("You're registered")
            ->assertDontSee('>Register<', false);
    }

    public function test_a_registered_player_can_unregister_from_the_details_page(): void
    {
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->register($tournament, $player);

        $this->actingAs($player)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Unregister')
            ->assertSee(route('tournaments.unregister', $tournament), false);
    }

    public function test_unregister_is_not_offered_once_registration_has_closed(): void
    {
        // The controller refuses it, so offering the control would be a button
        // that fails. registration_open is false once scheduled_at is past,
        // which is not the same as play having begun.
        $tournament = $this->tournament(closesIn: '-1 day');
        $player = User::factory()->create();
        $this->register($tournament, $player);

        $this->actingAs($player)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee("You're registered")
            ->assertDontSee('Unregister');
    }

    public function test_an_unapproved_player_is_told_why_there_is_no_register_button(): void
    {
        $tournament = $this->tournament();
        $pending = User::factory()->create(['approval_status' => 'pending']);

        $this->actingAs($pending)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Awaiting approval')
            ->assertDontSee('>Register<', false);
    }

    public function test_a_signed_in_player_gets_the_season_standings_button(): void
    {
        // On the home page, not the details page -- the point of moving these
        // into the shared card was that every card offers the same way out.
        $tournament = $this->tournament();

        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertSee('Season Standings')
            ->assertSee(route('seasons.show', $tournament->season), false);
    }

    public function test_a_guest_is_not_offered_the_season_standings_button(): void
    {
        // seasons.show sits behind the auth middleware. Offering it to a guest
        // is offering a redirect to the login screen.
        $tournament = $this->tournament();

        $this->get('/')->assertOk()
            ->assertDontSee('Season Standings')
            ->assertDontSee(route('seasons.show', $tournament->season), false);
    }

    public function test_only_an_admin_is_offered_the_venue_report(): void
    {
        // poker.venues.show is inside the admin-only /poker prefix, so this
        // button on a player's card would be a 403 with a nice label.
        $tournament = $this->tournament();
        $venueReport = route('poker.venues.show', $tournament->venue);

        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/')->assertOk()
            ->assertDontSee($venueReport, false);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/')->assertOk()
            ->assertSee('Venue Report')
            ->assertSee($venueReport, false);
    }
}
