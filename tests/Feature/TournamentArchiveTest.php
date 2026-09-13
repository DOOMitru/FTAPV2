<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Tournament Archives cards on the public events page.
 *
 * Each card is one big link to the tournament's own page -- and that page is
 * behind the auth middleware. So for a guest the card was not "a Full results
 * link they could not follow", it was an entire card that bounced them to the
 * login screen wherever they clicked it. Hiding the line at its foot would have
 * taken away the signpost and left the trap.
 *
 * A guest gets the card as a plain block instead: no href, no lift, no foot.
 * Everything it actually tells them -- when, where, who came first -- is still
 * there, and none of it pretends to lead anywhere.
 */
class TournamentArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function pastTournament(string $name = 'Past Cup'): PokerTournament
    {
        $season = PokerSeason::firstOrCreate(['name' => 'Season 9'], [
            'start_date' => now()->subYear(), 'end_date' => now()->addMonth(), 'is_current' => true,
        ]);

        return PokerTournament::create([
            'name' => $name,
            'start_time' => now()->subWeek(),
            'venue_id' => Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St'])->id,
            'season_id' => $season->id,
        ]);
    }

    private function player(): User
    {
        return User::factory()->create(['approval_status' => 'approved']);
    }

    public function test_a_signed_in_player_gets_a_card_that_links(): void
    {
        $tournament = $this->pastTournament();

        $html = $this->actingAs($this->player())->get('/events')->assertOk()->getContent();

        $this->assertStringContainsString('Full results', $html);
        $this->assertStringContainsString(route('tournaments.show', $tournament), $html);
        $this->assertMatchesRegularExpression('/<a class="p-archive__card p-raised p-lift"/', $html);
    }

    public function test_a_guest_gets_a_card_that_does_not(): void
    {
        $tournament = $this->pastTournament();

        $html = $this->get('/events')->assertOk()->getContent();

        $this->assertStringNotContainsString('Full results', $html);
        $this->assertStringNotContainsString(route('tournaments.show', $tournament), $html);

        // A block, not an anchor. The class alone is not the point: the point
        // is that there is nothing to click.
        $this->assertMatchesRegularExpression('/<div class="p-archive__card p-raised"/', $html);
    }

    public function test_a_guest_card_does_not_rise_to_meet_the_pointer(): void
    {
        // p-lift is a promise. On a card that goes nowhere it is a lie told in
        // CSS, and the only one a mouse user would ever notice.
        $this->pastTournament();

        $html = $this->get('/events')->assertOk()->getContent();

        $this->assertStringNotContainsString('p-lift', $html);
    }

    public function test_a_guest_still_sees_what_the_card_is_for(): void
    {
        // Withholding the link is not withholding the record. The archive is
        // the league's public history and reads the same either way.
        $tournament = $this->pastTournament('Autumn Showdown');

        $this->get('/events')->assertOk()
            ->assertSee('Autumn Showdown')
            ->assertSee('Hall')
            ->assertSee('Completed');
    }

    public function test_the_date_carries_the_day(): void
    {
        // A league plays several nights a month, so "Sep 2026" named a handful
        // of these cards at once and told you which one you were looking at
        // only by its title.
        $tournament = $this->pastTournament();

        $this->get('/events')->assertOk()
            ->assertSee($tournament->start_time->format('M d, Y'))
            ->assertDontSee('>'.$tournament->start_time->format('M Y').'<', false);
    }

    public function test_the_day_is_shown_to_a_signed_in_player_too(): void
    {
        // The date is not part of what the auth gate withholds.
        $tournament = $this->pastTournament();

        $this->actingAs($this->player())->get('/events')->assertOk()
            ->assertSee($tournament->start_time->format('M d, Y'));
    }
}
