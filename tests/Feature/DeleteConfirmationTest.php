<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a delete confirmation says it is about to delete.
 *
 * Three listings hold rows that are ABOUT a person without BEING one -- venue
 * points, a tournament entry, a recorded finish -- and all three asked "Delete
 * Ada Lovelace? This cannot be undone." That is a truthful description of a
 * different and much worse button, offered at the moment someone is deciding
 * whether to press it.
 *
 * So each of these checks the sentence names the record. The person's name is
 * still in it, because it is how you tell one row from another; it is just not
 * the only thing in it.
 */
class DeleteConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    private function tournament(): PokerTournament
    {
        $season = PokerSeason::create([
            'name' => 'Season 40',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        return PokerTournament::create([
            'name' => 'Wednesday Night Poker',
            'start_time' => now()->subHour(),
            'venue_id' => Venue::create(['name' => 'Diamond Club', 'address' => '1 Card Street'])->id,
            'season_id' => $season->id,
        ]);
    }

    /** The confirmation attached to the only delete form on a page. */
    private function confirmationOn(string $url): string
    {
        return $this->confirmationsOn($url)[0];
    }

    /**
     * Every confirmation on a page, not just the first.
     *
     * The users listing orders by first name and this class seeds an admin from
     * the factory, which picks one at random. Taking the first form meant the
     * assertion only held while that random name happened to sort after the
     * person the test had created -- so roughly one run in fifty, when the
     * factory rolled an "Aaron" or an "Abigail", the first confirmation on the
     * page belonged to the admin and the test failed with nothing to explain
     * it. That is the intermittent failure recorded in RESUME-HERE.
     *
     * @return list<string>
     */
    private function confirmationsOn(string $url): array
    {
        // Emphasis markers stripped: this file is about what the sentences
        // SAY, and the markers are invisible, so a mismatch would print two
        // identical-looking strings.
        $html = $this->withoutEmphasis(
            $this->actingAs($this->admin())->get($url)->assertOk()->getContent()
        );

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        $forms = (new \DOMXPath($dom))->query('//form[@data-confirm]');

        $this->assertGreaterThan(0, $forms->length, "No confirmable form on {$url}.");

        $confirmations = [];

        foreach ($forms as $form) {
            $confirmations[] = $form->getAttribute('data-confirm');
        }

        return $confirmations;
    }

    public function test_venue_points_say_they_are_points_and_not_a_player(): void
    {
        $venue = Venue::create(['name' => 'Diamond Club', 'address' => '1 Card Street']);

        VenuePoints::create([
            'venue_id' => $venue->id,
            'user_id' => User::factory()->create()->id,
            'user_name' => 'Ada Lovelace',
            'event_date' => '2026-08-14',
            'amount' => 5,
        ]);

        $this->assertSame(
            'Delete 5 venue points for Ada Lovelace at Diamond Club on Aug 14, 2026? This cannot be undone.',
            $this->confirmationOn(route('poker.venue-points.index'))
        );
    }

    public function test_a_registration_says_it_is_a_registration(): void
    {
        $tournament = $this->tournament();

        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id,
            'user_id' => User::factory()->create()->id,
            'player_name' => 'Ada Lovelace',
            'registered_at' => now(),
        ]);

        $this->assertSame(
            'Remove Ada Lovelace from Wednesday Night Poker? This cannot be undone.',
            $this->confirmationOn(route('poker.registrants.index'))
        );
    }

    public function test_a_result_says_it_is_a_finish(): void
    {
        $tournament = $this->tournament();

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id,
            'user_id' => User::factory()->create()->id,
            'player_name' => 'Ada Lovelace',
            'place' => 3,
            'points' => 75,
        ]);

        $this->assertSame(
            'Delete the 3rd place finish for Ada Lovelace in Wednesday Night Poker, worth 75 points? This cannot be undone.',
            $this->confirmationOn(route('poker.results.index'))
        );
    }

    public function test_deleting_an_actual_person_still_says_so(): void
    {
        // The counterpart. On the user listing the row IS the person, so
        // "Delete Ada Lovelace?" is exactly right and must not be reworded
        // along with the others.
        User::factory()->create([
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'approval_status' => 'approved',
        ]);

        // Asked of every confirmation on the page rather than the first: the
        // list is ordered by first name and the admin's is random, so "first"
        // was a coin toss this test used to lose about once in fifty runs.
        $this->assertContains(
            'Delete Ada Lovelace? This cannot be undone.',
            $this->confirmationsOn(route('users.index'))
        );
    }
}
