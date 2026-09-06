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
 * it IS the details page, and an Unregister control on every card that says
 * you are registered.
 * Both arrive through the component's props and slots, and both are the kind of
 * thing a later edit to the shared card can quietly drop.
 */
class EventCardTest extends TestCase
{
    use RefreshDatabase;

    private function recordAFinish(PokerTournament $tournament, User $player): void
    {
        \App\Models\PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'place' => 1,
            'points' => 0,
        ]);
    }

    private function tournament(): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 12',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Ironclad Invitational',
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
            ->assertSee('Registered')
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

    public function test_unregister_is_not_offered_once_a_finish_is_recorded(): void
    {
        // The controller refuses it then, so offering the control would be a
        // button that fails. Nothing about the clock enters into it: this
        // tournament is four days away and the way out is still shut, because a
        // recorded place describes a field of a particular size.
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player);

        $this->actingAs($player)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Registered')
            ->assertDontSee('Unregister')
            // The row is still drawn, because the badge is in it now -- but
            // with no button group inside. The details page always passes the
            // slot and fills it only when unregistering is possible, so the
            // card has to test the slot's CONTENT: isset() alone would render
            // an empty group and a gap beside the badge.
            ->assertDontSee('p-event__actions-end', false);
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

    public function test_the_start_date_renders_as_a_calendar_leaf(): void
    {
        $tournament = $this->tournament();
        $starts = $tournament->start_time;

        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertSee('<span class="p-event__month">'.$starts->format('M').'</span>', false)
            ->assertSee('<span class="p-event__day">'.$starts->format('j').'</span>', false)
            ->assertSee('<span class="p-event__weekday">'.$starts->format('D').'</span>', false)
            ->assertSee($starts->format('g:i A'));
    }

    public function test_the_whole_start_date_is_still_announced(): void
    {
        // The leaf is aria-hidden -- three fragments read out as "Nov 30 Tue"
        // is worse than nothing -- so the full date lives in a visually-hidden
        // line beside it. Drop that and a screen reader gets no date at all
        // while the page looks completely correct.
        $tournament = $this->tournament();

        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertSee($tournament->start_time->format('l j F Y, g:i A'));
    }

    public function test_the_public_cards_let_a_registered_player_leave(): void
    {
        // Wherever a card is willing to tell you that you are registered, it is
        // willing to let you undo it. This used to be the details page alone,
        // which meant seeing "Registered" on the home page and having to go
        // looking for the way out.
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->register($tournament, $player);

        foreach (['/', route('events')] as $url) {
            $html = $this->actingAs($player)->get($url)->assertOk()
                ->assertSee('Registered')
                ->assertSee('Unregister')
                ->getContent();

            $row = substr($html, strpos($html, 'p-event__actions'));

            $this->assertStringContainsString('Registered', $row, "Badge not in the row on {$url}.");
            $this->assertStringContainsString(
                route('tournaments.unregister', $tournament),
                $row,
                "No way out on {$url}."
            );

            // Never both: someone registered cannot also be offered Register.
            $this->assertStringNotContainsString('>Register<', $row);
        }
    }

    public function test_the_badge_stands_alone_once_a_finish_is_recorded(): void
    {
        // The same on the home page's copy of the card, which builds its own
        // query -- so a page that forgot the results count would show a
        // different answer from the details page for the same tournament.
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player);

        $this->actingAs($player)->get('/')->assertOk()
            ->assertSee('Registered')
            ->assertDontSee('Unregister')
            ->assertDontSee('p-event__actions-end', false);
    }

    public function test_a_card_with_nothing_to_say_draws_no_row(): void
    {
        // The rule and the padding are only worth drawing around something. A
        // guest is offered no button and has no status, so the card ends at
        // its facts.
        $this->tournament();

        $this->get('/')->assertOk()->assertDontSee('p-event__actions', false);
    }

    public function test_the_action_row_holds_register_alone(): void
    {
        $this->tournament();

        $html = $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()->getContent();

        $this->assertStringContainsString('p-event__actions', $html);

        // The row is the last thing in the card body, so everything from it
        // onwards is the row and the closing tags.
        $row = substr($html, strpos($html, 'p-event__actions'));

        $this->assertStringContainsString('Register', $row);
        $this->assertStringNotContainsString('Season Standings', $row);
        $this->assertStringNotContainsString('Details', $row);
    }

    public function test_the_other_actions_moved_into_the_card_menu(): void
    {
        // Details and Season Standings are menu entries now, not buttons.
        $tournament = $this->tournament();

        $html = $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()->getContent();

        $this->assertStringContainsString('More actions', $html);
        $this->assertStringContainsString(
            '<a class="dropdown__item" href="'.route('tournaments.show', $tournament).'">Details</a>',
            $html
        );
        $this->assertStringContainsString(
            '<a class="dropdown__item" href="'.route('seasons.show', $tournament->season).'">Season Standings</a>',
            $html
        );
    }

    public function test_unregister_stands_where_register_would(): void
    {
        // It arrives through the card's slot, which lands in the action row, so
        // the control is a button again rather than a menu item -- and it is
        // not in the menu, which is the half of this that could regress
        // silently.
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->register($tournament, $player);

        $html = $this->actingAs($player)->get(route('tournaments.show', $tournament))
            ->assertOk()->getContent();

        $this->assertStringContainsString('p-event__actions', $html);

        $row = substr($html, strpos($html, 'p-event__actions'));
        $this->assertStringContainsString('Unregister', $row);

        // Never both: someone registered cannot also be offered Register.
        $this->assertStringNotContainsString('>Register<', $row);

        // And no longer a menu item.
        $this->assertStringNotContainsString('class="dropdown__item">Unregister', $html);
    }
}
