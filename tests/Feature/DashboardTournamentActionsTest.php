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
     * Registration closes at $closesIn; play starts later.
     *
     * The two are separate dates and this test file turns on the difference:
     * the dashboard lists on start_time, so a tournament whose registration has
     * closed is still ON the page right up until it is played.
     */
    private function tournament(string $closesIn = '+3 days', string $startsIn = '+4 days'): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 40',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(2),
            'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => 'Wednesday Night Poker',
            'scheduled_at' => now()->modify($closesIn),
            'start_time' => now()->modify($startsIn),
            'venue_id' => Venue::create(['name' => 'Diamond Club', 'address' => '1 Card Street'])->id,
            'season_id' => $season->id,
        ]);
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
        $this->tournament();

        $this->actingAs($this->player())->get(route('dashboard'))->assertOk()
            ->assertSee('>Register<', false)
            ->assertDontSee('Sign Up');
    }

    public function test_a_registered_player_keeps_the_badge_and_gains_a_way_out(): void
    {
        $tournament = $this->tournament();
        $player = $this->player();
        $this->register($tournament, $player);

        $this->actingAs($player)->get(route('dashboard'))->assertOk()
            ->assertSee('Registered')
            ->assertSee('>Unregister<', false)
            ->assertSee(route('tournaments.unregister', $tournament))
            ->assertDontSee('>Register<', false);
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
            ->assertSessionHas('status');

        $this->assertSame(0, $tournament->registrants()->count());
    }

    public function test_the_way_out_closes_when_registration_does(): void
    {
        // The controller refuses a withdrawal once registration has closed, so
        // past that point the badge stands alone rather than offering a button
        // that would be turned away.
        $tournament = $this->tournament(closesIn: '-1 hour', startsIn: '+2 hours');
        $player = $this->player();
        $this->register($tournament, $player);

        $this->actingAs($player)->get(route('dashboard'))->assertOk()
            ->assertSee('Registered')
            ->assertDontSee('>Unregister<', false);
    }

    public function test_an_unapproved_player_is_told_why_rather_than_offered_a_button(): void
    {
        $this->tournament();

        $this->actingAs($this->player(approved: false))->get(route('dashboard'))->assertOk()
            ->assertSee('Awaiting approval')
            ->assertDontSee('>Register<', false);
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

        foreach ([route('dashboard'), route('tournaments.show', $tournament)] as $url) {
            $this->actingAs($player)->get($url)->assertOk()
                ->assertSee('Registered')
                ->assertDontSee("You're registered");
        }
    }
}
