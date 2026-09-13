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

    public function test_the_listing_cards_still_offer_details(): void
    {
        // The other side of the prop. If `details` ever defaults to false the
        // home and events pages lose their only route into a tournament, and
        // the test above would still pass.
        //
        // Signed in, which this did not used to be. tournaments.show is behind
        // the auth middleware, so offering Details to a guest was offering a
        // bounce to the login screen -- the menu already said as much about
        // Season Standings and the venue report, and Details was the entry that
        // had escaped the rule. Moving it into the action row is what showed
        // that up.
        $this->tournament();

        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertSee('Details');
    }

    public function test_a_guest_is_offered_neither_details_nor_a_menu(): void
    {
        $this->tournament();

        $this->get('/')->assertOk()
            ->assertDontSee('Details')
            // With nothing left in it for a guest the kebab goes too: a trigger
            // that opens an empty panel is worse than no trigger.
            ->assertDontSee('More actions');
    }

    public function test_details_sits_to_the_left_of_register(): void
    {
        // "To its left, or instead of it" -- and in a row laid out in document
        // order, left is first.
        $this->tournament();

        $html = $this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent();
        $row = substr($html, (int) strpos($html, 'p-event__actions-end'));

        $details = strpos($row, 'Details');
        $register = strpos($row, '>Register<');

        $this->assertNotFalse($details);
        $this->assertNotFalse($register);
        $this->assertLessThan($register, $details, 'Details must come before Register.');
    }

    public function test_details_stands_alone_when_there_is_nothing_to_register_for(): void
    {
        // The "instead of" half. A settled tournament offers no Register and no
        // Unregister; the row is not therefore empty.
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->register($tournament, $player);
        $this->recordAFinish($tournament, $player);

        $html = $this->actingAs($player)->get('/')->assertOk()->getContent();
        $row = substr($html, (int) strpos($html, 'p-event__actions-end'));

        $this->assertStringContainsString('Details', $row);
        $this->assertStringNotContainsString('>Register<', $row);
        $this->assertStringNotContainsString('Unregister', $row);
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
            // The row is not empty any more -- Details lives in it -- so what
            // this holds is that the badge has no BUTTON beside it.
            ->assertDontSee('>Register<', false);
    }

    public function test_a_card_with_nothing_to_say_draws_no_row(): void
    {
        // The rule and the padding are only worth drawing around something. A
        // guest is offered no button and has no status, so the card ends at
        // its facts.
        $this->tournament();

        $this->get('/')->assertOk()->assertDontSee('p-event__actions', false);
    }

    public function test_the_action_row_holds_details_and_register(): void
    {
        $this->tournament();

        $html = $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()->getContent();

        $this->assertStringContainsString('p-event__actions', $html);

        // The row is the last thing in the card body, so everything from it
        // onwards is the row and the closing tags.
        $row = substr($html, strpos($html, 'p-event__actions'));

        $this->assertStringContainsString('Register', $row);

        // Details joined it; Season Standings and the venue report did not.
        $this->assertStringContainsString('Details', $row);
        $this->assertStringNotContainsString('Season Standings', $row);
    }

    public function test_the_other_actions_moved_into_the_card_menu(): void
    {
        // Season Standings and the venue report are menu entries; Details is
        // a button in the action row.
        $tournament = $this->tournament();

        $html = $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()->getContent();

        $this->assertStringContainsString('More actions', $html);

        // Details is NOT in here any more: it was the entry most people opened
        // this menu for, and it was two clicks behind a kebab.
        $this->assertStringNotContainsString(
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

    public function test_the_details_page_draws_no_map(): void
    {
        // Removed 2026-09-12. The map is the public card's opening image, sold
        // to somebody deciding whether to come; the details page is reached
        // from inside the dashboard by people who already know where the league
        // plays, and the embed pushed the panels that page exists for down.
        $tournament = $this->tournament();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('tournaments.show', $tournament))->assertOk()->getContent();

        $this->assertStringNotContainsString('maps.google.com', $html);
        $this->assertStringNotContainsString('class="map"', $html);

        // The venue is still named. Hiding the map must not hide where it is.
        $this->assertStringContainsString($tournament->venue->name, $html);
    }

    public function test_the_public_cards_keep_their_map(): void
    {
        // The other half, and the one that could regress silently: `map` is a
        // prop with a default, so a typo in the default -- or in the one call
        // site that passes false -- takes the map off the events and home pages
        // too, where it is the first thing on the card.
        $this->tournament();

        foreach (['/events', '/'] as $path) {
            $this->assertStringContainsString(
                'maps.google.com',
                $this->get($path)->assertOk()->getContent(),
                $path.' lost its map.'
            );
        }
    }

    public function test_the_date_is_available_as_a_line_as_well_as_a_leaf(): void
    {
        // On a phone the calendar leaf becomes a line between the venue and the
        // time. Which of the two is drawn is CSS and cannot be asserted here; it
        // was measured -- leaf shown and line hidden at 1440 and 800, the
        // reverse at 375 and 320, with no horizontal overflow at any of them.
        //
        // What IS assertable is that both exist and sit in the right order,
        // because a stylesheet cannot move one between two others.
        $tournament = $this->tournament();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('tournaments.show', $tournament))->assertOk()->getContent();

        $venue = strpos($html, 'p-event__venue');
        $date = strpos($html, 'p-event__date');
        $time = strpos($html, 'p-event__time');

        $this->assertNotFalse($date, 'The card carries no date line.');
        $this->assertLessThan($date, $venue, 'The date must come after the venue.');
        $this->assertLessThan($time, $date, 'The date must come before the time.');
    }

    public function test_the_date_is_never_announced_twice(): void
    {
        // The leaf, the line and the time all carry the same date. Two of them
        // are decoration; the time line's visually-hidden span is the one a
        // screen reader reads, and it says the whole thing.
        $tournament = $this->tournament();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('tournaments.show', $tournament))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<p class="p-event__date" aria-hidden="true">/',
            $html,
            'The date line stands in for the leaf and must be hidden like it.'
        );

        // The line a screen reader actually reads, unchanged.
        $this->assertStringContainsString(
            $tournament->start_time->format('l j F Y, g:i A'),
            $html
        );
    }

    public function test_the_date_line_says_the_same_day_as_the_leaf(): void
    {
        // Two renderings of one moment. A format change to either that left the
        // other behind would show a card disagreeing with itself across a
        // breakpoint, which nobody would see on the width they work at.
        $tournament = $this->tournament();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('tournaments.show', $tournament))->assertOk()->getContent();

        $this->assertStringContainsString($tournament->start_time->format('D, M j'), $html);
        $this->assertStringContainsString('>'.$tournament->start_time->format('j').'<', $html);
    }
}
