<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who won is for the league, not for the internet.
 *
 * The events archive was the one page in the app where a signed-out visitor
 * read a player's name, and a name on a public page is a name a search engine
 * indexes -- which is not what somebody agreed to by turning up to a bar on a
 * Wednesday. The league's other public surfaces name nobody: the landing page's
 * leader cards and the points-structure page's Current Season Leaders are both
 * behind @auth already.
 *
 * The EVENT stays public. The archive exists to show a visitor that the league
 * runs, where it plays and how often, and that argument never needed names.
 */
class PublicArchivePrivacyTest extends TestCase
{
    use RefreshDatabase;

    private PokerTournament $tournament;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $season = PokerSeason::create([
            'name' => 'Season 9', 'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(), 'is_current' => true,
        ]);

        $this->tournament = PokerTournament::create([
            'name' => 'Wednesday Night Poker',
            'start_time' => now()->subDays(7),
            'venue_id' => Venue::create(['name' => 'The Grand Card Room', 'address' => '1 St'])->id,
            'season_id' => $season->id,
            'published_at' => now()->subDays(6),
        ]);

        $this->player = User::factory()->create([
            'first_name' => 'Wanda', 'last_name' => 'Reeve', 'approval_status' => 'approved',
        ]);

        PokerTournamentRegistrant::create([
            'tournament_id' => $this->tournament->id, 'user_id' => $this->player->id,
            'player_name' => 'Wanda Reeve', 'registered_at' => now()->subDays(7),
        ]);

        PokerTournamentResult::create([
            'tournament_id' => $this->tournament->id, 'user_id' => $this->player->id,
            'player_name' => 'Wanda Reeve', 'place' => 1, 'points' => 500,
        ]);
    }

    public function test_a_guest_reads_no_player_name_on_the_archive(): void
    {
        $response = $this->get(route('events'))->assertOk();

        $response->assertDontSee('Wanda');
        $response->assertDontSee('Reeve');
    }

    public function test_a_guest_reads_no_initials_either(): void
    {
        // Initials are a weaker disclosure than a name, not no disclosure: two
        // letters beside a rank on a page that also names the venue and the
        // date narrows a person considerably in a league this size.
        $this->get(route('events'))->assertOk()->assertDontSee('class="monogram', false);
    }

    public function test_the_event_itself_stays_public(): void
    {
        // The point of the archive, and the reason it is not simply hidden.
        $response = $this->get(route('events'))->assertOk();

        $response->assertSee('Wednesday Night Poker');
        $response->assertSee('The Grand Card Room');
    }

    public function test_a_guest_is_told_the_results_exist(): void
    {
        // Said, not silently omitted: a card that stopped after the venue would
        // read as a tournament nobody finished.
        $this->get(route('events'))->assertOk()->assertSee('Sign in to see the results.');
    }

    public function test_a_signed_in_visitor_reads_the_podium(): void
    {
        $response = $this->actingAs($this->player)->get(route('events'))->assertOk();

        $response->assertSee('Wanda Reeve');
        $response->assertDontSee('Sign in to see the results.');
    }

    public function test_a_signed_in_visitor_reads_the_initials(): void
    {
        $this->actingAs($this->player)->get(route('events'))->assertOk()
            ->assertSee('class="monogram monogram--sm"', false);
    }

    public function test_the_other_public_pages_still_name_nobody(): void
    {
        // The two surfaces the handoff worried about. Both were already behind
        // @auth; this is what keeps them there.
        foreach ([route('home'), route('rules.points-structure')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Wanda');
        }
    }
}
