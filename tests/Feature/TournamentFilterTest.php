<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * Filtering the registrants list to one tournament.
 *
 * The list is a league's whole history in one table, and an administrator
 * working on a league night wants the night in front of them. The default is
 * the tournament NEAREST in time -- past or future, whichever is closer.
 *
 * It covered the results list too, from the other side of a game: registrants
 * before it, results after. That list is gone -- results are read on the
 * tournament page now -- and PokerTournament::nearest(), which both shared,
 * is still exercised directly by the first four tests below.
 */
class TournamentFilterTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    /** Three tournaments: one long past, one just played, one soon. */
    private function schedule(): array
    {
        $season = PokerSeason::firstOrCreate(['name' => 'Season 40'], [
            'start_date' => '2026-08-01', 'end_date' => '2026-10-31', 'is_current' => true,
        ]);
        $venue = Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St']);

        $make = fn (string $name, Carbon $when) => PokerTournament::create([
            'name' => $name, 'start_time' => $when,
            'venue_id' => $venue->id, 'season_id' => $season->id,
        ]);

        return [
            'old' => $make('Old Cup', now()->subMonths(2)),
            'recent' => $make('Recent Cup', now()->subDays(2)),
            'soon' => $make('Soon Cup', now()->addDays(9)),
        ];
    }

    private function enrol(PokerTournament $tournament, string $name): User
    {
        $player = User::factory()->create([
            'first_name' => $name, 'last_name' => 'Player', 'approval_status' => 'approved',
        ]);

        $this->enter($tournament, $player);

        return $player;
    }

    public function test_the_nearest_tournament_is_the_one_in_front_of_you(): void
    {
        $s = $this->schedule();

        // Two days behind beats nine days ahead.
        $this->assertSame($s['recent']->id, PokerTournament::nearest()?->id);
    }

    public function test_nearest_looks_forwards_as_well_as_back(): void
    {
        $s = $this->schedule();
        $s['recent']->forceFill(['start_time' => now()->subDays(20)])->save();

        // Now the soonest future one is closer than anything behind.
        $this->assertSame($s['soon']->id, PokerTournament::nearest()?->id);
    }

    public function test_nearest_works_when_nothing_has_been_played_yet(): void
    {
        $season = PokerSeason::firstOrCreate(['name' => 'Season 40'], [
            'start_date' => '2026-08-01', 'end_date' => '2026-10-31', 'is_current' => true,
        ]);

        $only = PokerTournament::create([
            'name' => 'First Ever', 'start_time' => now()->addWeek(),
            'venue_id' => Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St'])->id,
            'season_id' => $season->id,
        ]);

        $this->assertSame($only->id, PokerTournament::nearest()?->id);
    }

    public function test_nearest_is_null_when_there_are_no_tournaments(): void
    {
        $this->assertNull(PokerTournament::nearest());
    }

    public function test_registrants_default_to_the_nearest_tournament(): void
    {
        $s = $this->schedule();
        $this->enrol($s['recent'], 'Nearby');
        $this->enrol($s['old'], 'Ancient');

        $this->actingAs($this->admin())->get(route('poker.registrants.index'))->assertOk()
            ->assertSee('Nearby')
            ->assertDontSee('Ancient');
    }

    public function test_registrants_can_be_filtered_to_another_tournament(): void
    {
        $s = $this->schedule();
        $this->enrol($s['recent'], 'Nearby');
        $this->enrol($s['old'], 'Ancient');

        $this->actingAs($this->admin())
            ->get(route('poker.registrants.index', ['tournament' => $s['old']->id]))->assertOk()
            ->assertSee('Ancient')
            ->assertDontSee('Nearby');
    }

    /** @return array<string, array{0: string}> */
    public static function filteredPages(): array
    {
        return [
            'registrants' => ['poker.registrants.index'],
        ];
    }

    #[DataProvider('filteredPages')]
    public function test_the_picker_lists_every_tournament_to_choose_from(string $route): void
    {
        $this->schedule();

        $html = $this->actingAs($this->admin())->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('tournament-filter', $html);

        foreach (['Old Cup', 'Recent Cup', 'Soon Cup'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
    }

    #[DataProvider('filteredPages')]
    public function test_the_search_box_does_not_close_the_menu_it_lives_in(string $route): void
    {
        // x-dropdown closes its panel on any click inside it, which is right
        // for a menu of links and fatal for an input: the panel shut the moment
        // you clicked the search box. Only the markup is checkable here -- this
        // project has no browser tests -- but the attribute is the whole fix,
        // and without it the filter cannot be typed into at all.
        $this->schedule();

        $html = $this->actingAs($this->admin())->get(route($route))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*tournament-filter__search[^>]*x-on:click\.stop/s',
            $html,
            'The search box must stop its click reaching the dropdown.'
        );
    }

    #[DataProvider('filteredPages')]
    public function test_the_filter_does_not_label_itself(string $route): void
    {
        // The trigger already names the tournament in view; "Showing" in front
        // of it says nothing the button does not.
        $this->schedule();

        $this->actingAs($this->admin())->get(route($route))->assertOk()
            ->assertDontSee('tournament-filter__label', false);
    }

    #[DataProvider('filteredPages')]
    public function test_the_picker_names_the_tournament_in_view(string $route): void
    {
        $s = $this->schedule();

        $this->actingAs($this->admin())
            ->get(route($route, ['tournament' => $s['old']->id]))->assertOk()
            ->assertSee('Old Cup');
    }

    #[DataProvider('filteredPages')]
    public function test_an_unknown_tournament_falls_back_rather_than_erroring(string $route): void
    {
        // A stale bookmark, or a tournament deleted since. Falling over on a
        // query string is a worse answer than showing the default.
        $this->schedule();

        $this->actingAs($this->admin())
            ->get(route($route, ['tournament' => 'not-a-real-id']))->assertOk()
            ->assertSee('Recent Cup');
    }

    #[DataProvider('filteredPages')]
    public function test_a_page_with_no_tournaments_at_all_still_renders(string $route): void
    {
        // The empty league, before anything is scheduled.
        $this->actingAs($this->admin())->get(route($route))->assertOk();
    }
}
