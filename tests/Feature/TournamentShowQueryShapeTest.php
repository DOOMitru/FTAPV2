<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * The tournament page costs the same whatever the size of the field.
 *
 * This is the page a player opens to register, so it is the one that gets
 * opened most. An N+1 here is invisible in every other test -- the page still
 * renders, the assertions still pass, and the only symptom is that a night
 * with thirty entrants is slower than one with five.
 *
 * Counting queries against a FIXED number would guard the same thing and go
 * off every time a legitimate query is added. Comparing two field sizes tests
 * the property that actually matters: the cost must not grow with the rows.
 */
class TournamentShowQueryShapeTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    /** Render the page for a field of $size scored players; return the query count. */
    private function cost(int $size): int
    {
        $season = PokerSeason::create([
            'name' => 'Season 9', 'start_date' => now()->subMonths(2),
            'end_date' => now()->addMonths(6), 'is_current' => true,
        ]);

        $venue = Venue::create(['name' => 'Hall', 'address' => '1 St']);

        $tournament = PokerTournament::create([
            'name' => 'Night', 'start_time' => now()->subDay(),
            'venue_id' => $venue->id, 'season_id' => $season->id,
        ]);

        $players = User::factory()->count($size)->create(['approval_status' => 'approved']);

        foreach ($players as $index => $player) {
            $this->enter($tournament, $player);
            $this->score($tournament, $player, $index + 1, max(1, 100 - $index * 4));
        }

        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_page_costs_the_same_for_five_players_as_for_thirty(): void
    {
        $small = $this->cost(5);

        // RefreshDatabase gives each test one transaction, not each call, so
        // the second field is built on top of the first. A fresh tournament
        // and season are made for it, and the page is scoped to its own
        // tournament, so the extra rows are not in the query either way.
        $large = $this->cost(30);

        $this->assertSame($small, $large,
            "The page ran {$small} queries for five players and {$large} for thirty. ".
            'Something in it is asking per row.');
    }

    public function test_isComplete_is_asked_once_and_carried(): void
    {
        // Two queries a call, and the page needs the answer in four places:
        // the header's publish prompt, the primary button's variant, the
        // admin panel, and the standings title. Asked four times it was eight
        // queries for one fact.
        //
        // The view is given the ANSWER, not the model's method, because
        // isComplete() deliberately queries through the relation methods so a
        // caller in a write request sees what it just recorded. Caching that
        // on the model would break eliminate(); PublishOfferTest holds it.
        $season = PokerSeason::create([
            'name' => 'Season 9', 'start_date' => now()->subMonths(2),
            'end_date' => now()->addMonths(6), 'is_current' => true,
        ]);

        $venue = Venue::create(['name' => 'Hall', 'address' => '1 St']);

        $tournament = PokerTournament::create([
            'name' => 'Night', 'start_time' => now()->subDay(),
            'venue_id' => $venue->id, 'season_id' => $season->id,
        ]);

        $player = User::factory()->create(['approval_status' => 'approved']);
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get(route('tournaments.show', $tournament))->assertOk();

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertTrue($response->viewData('isComplete'),
            'The view is handed the answer; without it the template asks the model.');

        // The second half of isComplete(): a distinct count of scored players.
        // It is the half with a shape nothing else on the page shares.
        $asked = collect($log)->filter(
            fn ($query) => str_contains($query['query'], 'count(distinct')
                && str_contains($query['query'], 'tournament_results')
        )->count();

        $this->assertSame(1, $asked,
            "isComplete() was asked {$asked} times. It is two queries a call and the answer does not change mid-render.");
    }
}
