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
 * What the dashboard's upcoming list lets you do about a tournament.
 *
 * The row used to say "Sign Up" where the rest of the app says Register, and a
 * registered player got a badge and nothing else -- so the only way out of a
 * tournament was to find the details page. Every condition here is the
 * controller's; the row offers a control exactly when the request behind it
 * would be honoured.
 */
class DashboardTournamentActionsTest extends TestCase
{
    use RefreshDatabase;

    private function player(bool $approved = true): User
    {
        return User::factory()->create([
            'approval_status' => $approved ? 'approved' : 'pending',
            'approval_decided_at' => $approved ? now() : null,
        ]);
    }

    /**
     * Play starts at $startsIn.
     *
     * The two are separate dates and this test file turns on the difference:
     * the dashboard lists on start_time, so a tournament whose registration has
     * closed is still ON the page right up until it is played.
     */
    private function tournament(string $startsIn = '+4 days'): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 40',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(2),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Wednesday Night Poker',
            'start_time' => now()->modify($startsIn),
            'venue_id' => Venue::create(['name' => 'Diamond Club', 'address' => '1 Card Street'])->id,
            'season_id' => $season->id,
        ]);
    }

    /**
     * The row the dashboard is sent for one tournament.
     *
     * Every question this file asks -- which control is offered, and why -- is
     * answered by a flag on the row now rather than by a button in the
     * response. The controller decides all three: it refuses a registration
     * from an unapproved account and a withdrawal once a finish is recorded,
     * so the row says what it will accept rather than the template guessing.
     *
     * @return array<string, mixed>
     */
    private function row(User $viewer, PokerTournament $tournament): array
    {
        $upcoming = $this->actingAs($viewer)->get(route('dashboard'))->assertOk()
            ->viewData('page')['props']['upcoming'];

        $row = collect($upcoming)->firstWhere('id', $tournament->id);

        $this->assertNotNull($row, 'The tournament is missing from Upcoming Tournaments.');

        return $row;
    }

    /** The dashboard's markup. */
    private function page(): string
    {
        return (string) file_get_contents(resource_path('js/Pages/Dashboard.vue'));
    }

    private function register(PokerTournament $tournament, User $player): void
    {
        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'registered_at' => now(),
        ]);
    }

    public function test_the_button_says_register(): void
    {
        $tournament = $this->tournament();

        $this->assertTrue($this->row($this->player(), $tournament)['can_register']);

        $page = $this->page();

        $this->assertStringContainsString('>Register</Btn>', $page);
        $this->assertStringNotContainsString('Sign Up', $page);
    }

    public function test_a_registered_player_keeps_the_badge_and_gains_a_way_out(): void
    {
        $tournament = $this->tournament();
        $player = $this->player();
        $this->register($tournament, $player);

        $row = $this->row($player, $tournament);

        $this->assertTrue($row['is_registered']);
        $this->assertTrue($row['can_unregister']);
        $this->assertFalse($row['can_register'], 'A registered player was offered Register.');

        $page = $this->page();

        $this->assertStringContainsString('>Unregister</Btn>', $page);

        // The path the button calls, which is not /register under another verb.
        $this->assertStringContainsString('/unregister`', $page);
    }

    public function test_unregistering_from_the_dashboard_works(): void
    {
        // The row posts to the same action the details page does, and lands
        // back where it started.
        $tournament = $this->tournament();
        $player = $this->player();
        $this->register($tournament, $player);

        $this->actingAs($player)
            ->from(route('dashboard'))
            ->delete(route('tournaments.unregister', $tournament))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertSame(0, $tournament->registrants()->count());
    }

    public function test_the_way_out_closes_when_a_finish_is_recorded(): void
    {
        // The controller refuses a withdrawal then, so past that point the badge
        // stands alone rather than offering a button that would be turned away.
        // Play has not started -- the clock is not what shuts this.
        $tournament = $this->tournament(startsIn: '+2 hours');
        $player = $this->player();
        $this->register($tournament, $player);

        \App\Models\PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'player_name' => $player->first_name.' '.$player->last_name,
            'place' => 1,
            'points' => 0,
        ]);

        $row = $this->row($player, $tournament);

        // The badge stands alone, which is the truth of it: the controller
        // refuses the withdrawal once a finish is recorded, because a place is
        // a position in a field.
        $this->assertTrue($row['is_registered']);
        $this->assertFalse($row['can_unregister']);

        $this->assertStringContainsString('v-if="tournament.can_unregister"', $this->page());
    }

    public function test_an_unapproved_player_is_told_why_rather_than_offered_a_button(): void
    {
        $tournament = $this->tournament();

        $row = $this->row($this->player(approved: false), $tournament);

        $this->assertFalse($row['can_register'],
            'An unapproved account was offered a click the controller refuses.');

        // And the row says why instead.
        $this->assertStringContainsString('>Awaiting approval</Badge>', $this->page());
    }

    public function test_an_unapproved_registration_is_refused_by_the_controller(): void
    {
        // What the badge above is standing in for. If this ever starts
        // succeeding, the badge is a lie and the button should come back.
        $tournament = $this->tournament();

        $this->actingAs($this->player(approved: false))
            ->post(route('tournaments.register', $tournament))
            ->assertSessionHas('error');

        $this->assertSame(0, $tournament->registrants()->count());
    }

    public function test_one_word_for_one_state_across_the_app(): void
    {
        // The dashboard row and the event card describe the same fact, and
        // said it two different ways -- "Registered" here, "You're registered"
        // there. A reader crossing between them has to work out whether those
        // are the same state.
        $tournament = $this->tournament();
        $player = $this->player();
        $this->register($tournament, $player);

        // The dashboard is Vue and the tournament page is still Blade, so the
        // one word is checked in each half's own source.
        $this->actingAs($player)->get(route('dashboard'))->assertOk();

        $dashboard = $this->page();

        $this->assertStringContainsString('>Registered</Badge>', $dashboard);
        $this->assertStringNotContainsString("You're registered", $dashboard);

        $this->actingAs($player)->get(route('tournaments.show', $tournament))->assertOk()
            ->assertSee('Registered')
            ->assertDontSee("You're registered");
    }
}
