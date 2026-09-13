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
 * The administrator's register-players dialog.
 *
 * This was a card in the page's side column, listing every player NOT already
 * in the tournament. It is a dialog now, and it stays open across
 * registrations, because entering a league night is a dozen actions rather than
 * one and the card closed the list and returned you to the page after each.
 *
 * Two rules inverted with the move, and both are asserted below:
 *
 *   - players already in the tournament are shown rather than hidden. The card
 *     left them out because register() refuses them and a button that fails is
 *     worse than no button, but absence is ambiguous -- an administrator
 *     looking for somebody who is not listed cannot tell "already entered" from
 *     "not approved", and the first is the common case. They are shown inert
 *     with a message instead.
 *   - the list is capped at ten and ordered by how many tournaments a player
 *     has entered, so a league's regulars are on top.
 *
 * The cap, the search and the ordering of what is DRAWN are Alpine, which this
 * project cannot assert. What is server-side is the payload Alpine reads: who
 * is in it, in what order, carrying what. That is what these tests hold.
 */
class AdminRegisterDialogTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(string $name = 'Picker Invitational'): PokerTournament
    {
        $season = PokerSeason::firstOrCreate(['name' => 'Season 30'], [
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => $name,
            'start_time' => now()->addDays(4),
            'venue_id' => Venue::firstOrCreate(['name' => 'Picker Hall'], ['address' => '3 Pick Street'])->id,
            'season_id' => $season->id,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'is_admin' => true, 'first_name' => 'Zed', 'last_name' => 'Admin',
            'approval_status' => 'approved',
        ]);
    }

    private function enter(PokerTournament $tournament, User $user): void
    {
        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'player_name' => $user->first_name.' '.$user->last_name,
            'registered_at' => now(),
        ]);
    }

    /**
     * The candidate payload Alpine reads.
     *
     * Read from the view data rather than parsed back out of the page. It is
     * bound to the response's ROOT view, so assertViewHas can see it -- unlike
     * anything a composer binds to an @included partial -- and the alternative
     * is a regex over Js::from's output, which renders as JSON.parse() with
     * every quote written \u0022 to survive an HTML attribute. Asserting the
     * payload is the point; asserting one escaping of it is not.
     *
     * The rows themselves are built by x-for and cannot be asserted here, so
     * this array is the contract: its order is the list's order, and its flags
     * are what each row draws.
     */
    private function candidates(\Illuminate\Testing\TestResponse $response): array
    {
        return collect($response->viewData('registerCandidates'))->all();
    }

    private function show(PokerTournament $tournament, ?User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->admin())
            ->get(route('tournaments.show', $tournament))->assertOk();
    }

    public function test_an_admin_is_offered_the_dialog_and_its_trigger(): void
    {
        $html = $this->show($this->tournament())->getContent();

        $this->assertStringContainsString('Register players', $html);
        $this->assertStringContainsString('registerDialog', $html);
    }

    public function test_a_player_gets_neither(): void
    {
        $tournament = $this->tournament();
        User::factory()->create(['first_name' => 'Some', 'last_name' => 'Body', 'approval_status' => 'approved']);

        $html = $this->show($tournament, User::factory()->create([
            'is_admin' => false, 'approval_status' => 'approved',
        ]))->getContent();

        $this->assertStringNotContainsString('registerDialog', $html);
        $this->assertStringNotContainsString('picker__btn', $html);
    }

    public function test_a_published_tournament_offers_no_way_in(): void
    {
        // register() refuses a published tournament, so the control would be a
        // button that fails.
        $tournament = $this->tournament();
        $tournament->forceFill(['published_at' => now()])->save();

        $html = $this->show($tournament)->getContent();

        $this->assertStringNotContainsString('registerDialog', $html);
        $this->assertStringNotContainsString('Register players', $html);
    }

    public function test_candidates_are_ordered_by_tournaments_entered(): void
    {
        // The names run BACKWARDS against the counts on purpose. An earlier
        // version of this test named them Ann, Bob and Cal in descending order
        // of tournaments played, so ordering by name alone produced the same
        // list and deleting the count ordering did not fail anything.
        $tournament = $this->tournament();

        $regular = User::factory()->create(['first_name' => 'Yvonne', 'last_name' => 'Regular', 'approval_status' => 'approved']);
        $sometimes = User::factory()->create(['first_name' => 'Mary', 'last_name' => 'Sometimes', 'approval_status' => 'approved']);
        $never = User::factory()->create(['first_name' => 'Abe', 'last_name' => 'Never', 'approval_status' => 'approved']);

        foreach (range(1, 3) as $i) {
            $other = $this->tournament('Other '.$i);
            $this->enter($other, $regular);

            if ($i === 1) {
                $this->enter($other, $sometimes);
            }
        }

        $names = array_column($this->candidates($this->show($tournament)), 'name');

        // By count: Yvonne (3), Mary (1), then the two on nothing in name
        // order. By name alone it would be Abe, Mary, Yvonne, Zed.
        $this->assertSame(['Yvonne Regular', 'Mary Sometimes', 'Abe Never', 'Zed Admin'], $names);
        $this->assertNotNull($never->id);
    }

    public function test_name_breaks_a_tie_on_the_count(): void
    {
        $tournament = $this->tournament();

        foreach ([['Cara', 'Young'], ['Abe', 'Zephyr'], ['Bo', 'Ash']] as [$first, $last]) {
            User::factory()->create(['first_name' => $first, 'last_name' => $last, 'approval_status' => 'approved']);
        }

        $names = array_column($this->candidates($this->show($tournament)), 'name');

        // Everyone has entered nothing, so first name decides -- and last name
        // would give Ash, Young, Zephyr, which is a different order.
        $this->assertSame(['Abe Zephyr', 'Bo Ash', 'Cara Young', 'Zed Admin'], $names);
    }

    public function test_a_candidate_carries_the_number_of_tournaments_entered(): void
    {
        $tournament = $this->tournament();
        $player = User::factory()->create(['first_name' => 'Ann', 'last_name' => 'Regular', 'approval_status' => 'approved']);

        $this->enter($this->tournament('Other 1'), $player);
        $this->enter($this->tournament('Other 2'), $player);

        $row = collect($this->candidates($this->show($tournament)))->firstWhere('name', 'Ann Regular');

        $this->assertSame(2, $row['played']);
    }

    public function test_the_count_includes_this_tournament_once_entered(): void
    {
        // The figure is "tournaments this player has entered", not "other
        // tournaments" -- so registering somebody moves them up the list rather
        // than leaving the number they were sorted by behind.
        $tournament = $this->tournament();
        $player = User::factory()->create(['first_name' => 'Ann', 'last_name' => 'Regular', 'approval_status' => 'approved']);
        $this->enter($tournament, $player);

        $row = collect($this->candidates($this->show($tournament)))->firstWhere('name', 'Ann Regular');

        $this->assertSame(1, $row['played']);
    }

    public function test_a_player_already_in_the_tournament_is_listed_and_flagged(): void
    {
        // The inversion. The card left them out; the dialog shows them inert,
        // because "not in the list" and "already entered" looked identical.
        $tournament = $this->tournament();
        $inside = User::factory()->create(['first_name' => 'Already', 'last_name' => 'In', 'approval_status' => 'approved']);
        $outside = User::factory()->create(['first_name' => 'Still', 'last_name' => 'Out', 'approval_status' => 'approved']);

        $this->enter($tournament, $inside);

        $rows = collect($this->candidates($this->show($tournament)))->keyBy('name');

        $this->assertTrue($rows['Already In']['registered']);
        $this->assertFalse($rows['Still Out']['registered']);
    }

    public function test_the_dialog_says_why_a_listed_player_cannot_be_picked(): void
    {
        $tournament = $this->tournament();
        $this->enter($tournament, User::factory()->create([
            'first_name' => 'Already', 'last_name' => 'In', 'approval_status' => 'approved',
        ]));

        $html = $this->show($tournament)->getContent();

        $this->assertStringContainsString('Already registered for this tournament', $html);
        $this->assertStringContainsString('picker__btn--inert', $html);
    }

    public function test_an_unapproved_player_is_listed_and_flagged(): void
    {
        // This asserted the opposite, on the reasoning that register() refuses
        // an unapproved target and there is nothing useful to show. There is:
        // an administrator hunting somebody who signed up last week and finding
        // nothing learns that the player is missing, not that the account is
        // waiting -- and waiting for THEM, since they are who approves it.
        $tournament = $this->tournament();
        User::factory()->create([
            'first_name' => 'Pending', 'last_name' => 'Person', 'approval_status' => 'pending',
        ]);

        $rows = collect($this->candidates($this->show($tournament)))->keyBy('name');

        $this->assertTrue($rows->has('Pending Person'));
        $this->assertFalse($rows['Pending Person']['approved']);
        $this->assertTrue($rows['Zed Admin']['approved']);
    }

    public function test_the_dialog_says_why_an_unapproved_player_cannot_be_picked(): void
    {
        $tournament = $this->tournament();
        User::factory()->create([
            'first_name' => 'Pending', 'last_name' => 'Person', 'approval_status' => 'pending',
        ]);

        $html = $this->show($tournament)->getContent();

        $this->assertStringContainsString('Waiting for approval', $html);
        $this->assertStringContainsString('approve the account first', $html);
    }

    public function test_the_server_still_refuses_an_unapproved_player(): void
    {
        // The flagged row is a courtesy, not the rule.
        $tournament = $this->tournament();
        $pending = User::factory()->create(['approval_status' => 'pending']);

        $this->actingAs($this->admin())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament), ['user_id' => $pending->id])
            ->assertSessionHas('error');

        $this->assertSame(0, $tournament->registrants()->count());
    }

    public function test_a_candidate_carries_a_haystack_covering_name_nickname_and_email(): void
    {
        // The search is Alpine and reads this field. Building it server-side is
        // what keeps the four fields it covers in one place.
        $tournament = $this->tournament();
        User::factory()->create([
            'first_name' => 'Ann', 'last_name' => 'Regular', 'nickname' => 'Acer',
            'email' => 'ann@example.test', 'approval_status' => 'approved',
        ]);

        $row = collect($this->candidates($this->show($tournament)))->firstWhere('name', 'Ann Regular');

        foreach (['ann', 'regular', 'acer', 'ann@example.test'] as $needle) {
            $this->assertStringContainsString($needle, $row['search']);
        }
    }

    public function test_every_candidate_is_sent_even_though_ten_are_drawn(): void
    {
        // The cap is on what is DRAWN. Sending only ten would make the search
        // box able to find nobody past the tenth, which is most of a league.
        $tournament = $this->tournament();

        foreach (range(1, 14) as $i) {
            User::factory()->create([
                'first_name' => 'Player', 'last_name' => sprintf('%02d', $i),
                'approval_status' => 'approved',
            ]);
        }

        $this->assertCount(15, $this->candidates($this->show($tournament)));
    }

    public function test_the_dialog_says_that_only_ten_are_shown(): void
    {
        $html = $this->show($this->tournament())->getContent();

        $this->assertStringContainsString('Showing the ten who have entered the most tournaments', $html);
        $this->assertStringContainsString('matches.length > 10', $html);
    }

    public function test_a_row_does_not_ask_for_confirmation(): void
    {
        // The dialog exists to add several players in a row; a confirmation on
        // each defeats it. Safe because registering is reversible from the same
        // page while no result exists.
        $tournament = $this->tournament();
        User::factory()->create(['first_name' => 'Some', 'last_name' => 'Body', 'approval_status' => 'approved']);

        $html = $this->show($tournament)->getContent();

        // Bounded at the dialog's own close. The layout renders the global
        // confirm dialog at the end of every page, so a slice that runs to the
        // end of the document finds data-confirm-message there and fails for a
        // reason that has nothing to do with this dialog.
        $at = strpos($html, 'registerDialog');
        $dialog = substr($html, $at, (int) strpos($html, '</dialog>', $at) - $at);

        $this->assertStringNotContainsString('data-confirm', $dialog);
    }

    public function test_the_payload_actually_reaches_the_page(): void
    {
        // The tests above read view data, which would keep passing if the
        // dialog stopped rendering it. Js::from writes every quote as \u0022
        // so the JSON survives an HTML attribute, so this looks for the id
        // rather than for readable JSON.
        $tournament = $this->tournament();
        $player = User::factory()->create([
            'first_name' => 'Ann', 'last_name' => 'Regular', 'approval_status' => 'approved',
        ]);

        $html = $this->show($tournament)->getContent();

        $this->assertStringContainsString('JSON.parse', $html);
        $this->assertStringContainsString($player->id, $html);
    }

    public function test_registering_from_the_dialog_leaves_it_open(): void
    {
        // The whole point of the move. Each row posts a normal form and the
        // page reloads, so "stays open" is a server fact: register() flashes
        // register_open and the dialog reopens itself on the way back.
        $tournament = $this->tournament();
        $player = User::factory()->create([
            'first_name' => 'Ann', 'last_name' => 'Regular', 'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament), ['user_id' => $player->id]);

        $response->assertRedirect(route('tournaments.show', $tournament))
            ->assertSessionHas('register_open', true);

        $this->assertDatabaseHas('tournament_registrants', [
            'tournament_id' => $tournament->id, 'user_id' => $player->id,
        ]);

        // And the page it lands on opens it.
        $this->assertStringContainsString(
            'x-init="$el.showModal()"',
            $this->followRedirects($response)->getContent()
        );
    }

    public function test_a_refusal_leaves_the_dialog_open_too(): void
    {
        // Closing it here would hide the list the administrator was working
        // through behind the message explaining what went wrong.
        $tournament = $this->tournament();
        $player = User::factory()->create([
            'first_name' => 'Ann', 'last_name' => 'Regular', 'approval_status' => 'approved',
        ]);
        $this->enter($tournament, $player);

        $this->actingAs($this->admin())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament), ['user_id' => $player->id])
            ->assertSessionHas('register_open', true)
            ->assertSessionHas('error');
    }

    public function test_a_player_registering_themselves_does_not_open_it(): void
    {
        // Self-registration comes from the event card on the same page and from
        // the public site. Neither has a dialog to reopen, and flashing the key
        // would make a player's own registration pop one open for them.
        $tournament = $this->tournament();
        $player = User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);

        $this->actingAs($player)
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament))
            ->assertSessionMissing('register_open');
    }

    public function test_an_admin_registering_themselves_does_not_open_it_either(): void
    {
        // An admin clicking Register on the event card is registering
        // themselves, which posts no user_id -- the flag keys off that, not off
        // the role.
        $tournament = $this->tournament();

        $this->actingAs($this->admin())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament))
            ->assertSessionMissing('register_open');
    }

    public function test_an_ordinary_page_load_does_not_open_it(): void
    {
        $this->assertStringNotContainsString(
            'x-init="$el.showModal()"',
            $this->show($this->tournament())->getContent()
        );
    }

    public function test_the_server_still_refuses_what_the_dialog_greys_out(): void
    {
        // The inert row is a courtesy, not the rule. A hand-posted duplicate is
        // still refused, and the field does not grow.
        $tournament = $this->tournament();
        $player = User::factory()->create(['approval_status' => 'approved']);
        $this->enter($tournament, $player);

        $this->actingAs($this->admin())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament), ['user_id' => $player->id])
            ->assertSessionHas('error');

        $this->assertSame(1, $tournament->registrants()->count());
    }

    public function test_the_default_list_leaves_out_anybody_who_cannot_be_picked(): void
    {
        // The filtering is Alpine and cannot be asserted from here, so this
        // pins the expression that does it. Behaviour was verified in a browser
        // at the same time: with an empty box the dialog drew ten rows and none
        // of them inert; searching a registered player's surname drew them,
        // inert.
        //
        // Reason: the list is capped at ten and ordered by tournaments entered,
        // so the regulars on top are the very people most likely to be entered
        // already -- the default view was spending its slots on rows that could
        // not be clicked.
        $tournament = $this->tournament();
        $this->enter($tournament, User::factory()->create([
            'first_name' => 'Already', 'last_name' => 'In', 'approval_status' => 'approved',
        ]));

        $html = $this->show($tournament)->getContent();

        $this->assertStringContainsString('filter(p => ! p.registered && p.approved)', $html);
    }

    public function test_a_search_still_reaches_players_already_registered(): void
    {
        // The other half. They are hidden from the default list, not from the
        // dialog: being told somebody is already in is the answer when you go
        // looking for one named person.
        $tournament = $this->tournament();
        $this->enter($tournament, User::factory()->create([
            'first_name' => 'Already', 'last_name' => 'In', 'approval_status' => 'approved',
        ]));

        $rows = collect($this->candidates($this->show($tournament)))->keyBy('name');

        $this->assertTrue($rows->has('Already In'), 'A registered player must stay searchable.');
        $this->assertTrue($rows['Already In']['registered']);
    }

    public function test_the_dialog_says_who_it_just_registered(): void
    {
        // The page's own alert is behind the backdrop while the dialog is open,
        // so without this each registration was announced to nobody -- and
        // entering a dozen players in a row is exactly when you want to see
        // that the last one landed.
        $tournament = $this->tournament();
        $player = User::factory()->create([
            'first_name' => 'Wanda', 'last_name' => 'Reeve', 'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin())
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.register', $tournament), ['user_id' => $player->id]);

        $response->assertSessionHas('registered_name', 'Wanda Reeve');

        $html = $this->followRedirects($response)->getContent();

        $this->assertStringContainsString('register__flash--done', $html);
        $this->assertStringContainsString('has been registered.', $html);
    }

    public function test_the_name_is_set_apart_from_the_sentence(): void
    {
        // "Highlight the name" is the requirement, and it is the requirement
        // because after eight of these the sentence is wallpaper and the name
        // is the only part still being read.
        $tournament = $this->tournament();
        $player = User::factory()->create([
            'first_name' => 'Wanda', 'last_name' => 'Reeve', 'approval_status' => 'approved',
        ]);

        $html = $this->followRedirects(
            $this->actingAs($this->admin())
                ->from(route('tournaments.show', $tournament))
                ->post(route('tournaments.register', $tournament), ['user_id' => $player->id])
        )->getContent();

        $this->assertMatchesRegularExpression(
            '/<span class="register__flash-name">\s*Wanda Reeve\s*<\/span>/',
            $html,
            'The name must be marked up separately so it can be highlighted.'
        );
    }

    public function test_a_refusal_is_shown_inside_the_dialog_too(): void
    {
        $tournament = $this->tournament();
        $player = User::factory()->create(['approval_status' => 'approved']);
        $this->enter($tournament, $player);

        $html = $this->followRedirects(
            $this->actingAs($this->admin())
                ->from(route('tournaments.show', $tournament))
                ->post(route('tournaments.register', $tournament), ['user_id' => $player->id])
        )->getContent();

        $this->assertStringContainsString('register__flash--error', $html);
    }

    public function test_the_page_alert_is_not_also_drawn_behind_the_backdrop(): void
    {
        // Two copies of the same sentence, one of them unreachable under a
        // scrim. The dialog's copy is the one that can be read.
        $tournament = $this->tournament();
        $player = User::factory()->create(['approval_status' => 'approved']);

        $html = $this->followRedirects(
            $this->actingAs($this->admin())
                ->from(route('tournaments.show', $tournament))
                ->post(route('tournaments.register', $tournament), ['user_id' => $player->id])
        )->getContent();

        $this->assertStringNotContainsString('alert--success', $html);
    }

    public function test_a_player_registering_themselves_still_gets_the_page_alert(): void
    {
        // The suppression keys off the dialog reopening, not off success. A
        // player has no dialog and must still be told what happened.
        $tournament = $this->tournament();
        $player = User::factory()->create(['is_admin' => false, 'approval_status' => 'approved']);

        $html = $this->followRedirects(
            $this->actingAs($player)
                ->from(route('tournaments.show', $tournament))
                ->post(route('tournaments.register', $tournament))
        )->getContent();

        $this->assertStringContainsString('alert--success', $html);
        $this->assertStringNotContainsString('register__flash--done', $html);
    }
}
