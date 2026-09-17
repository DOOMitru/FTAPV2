<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tournaments listing shows one season: the current one.
 *
 * The page is worked rather than browsed -- it is where a night is scheduled
 * and where an administrator goes to open the one being played -- so a league's
 * whole history in it was a list to scroll past. Seasons already gone are read
 * from their own page, which lists the tournaments held in them.
 */
class TournamentsIndexSeasonScopeTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Hall', 'address' => '1 St']);
    }

    /**
     * A season, and whether it is the current one.
     *
     * Built oldest-first by the caller where it matters: creating a season
     * marked current unsets the others, so the order they are made in decides
     * which one ends up wearing the crown.
     */
    private function season(string $name, bool $current, string $from, string $to): PokerSeason
    {
        return PokerSeason::create([
            'name' => $name, 'start_date' => $from, 'end_date' => $to, 'is_current' => $current,
        ]);
    }

    private function night(string $name, PokerSeason $season, string $when): PokerTournament
    {
        return PokerTournament::create([
            'name' => $name, 'start_time' => $when,
            'venue_id' => $this->venue->id, 'season_id' => $season->id,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    /** @return array<int, string> */
    private function listed(): array
    {
        $paginator = $this->actingAs($this->admin())
            ->get(route('poker.tournaments.index'))->assertOk()->viewData('tournaments');

        return collect($paginator->items())->pluck('name')->all();
    }

    public function test_only_the_current_seasons_nights_are_listed(): void
    {
        $old = $this->season('Season 8', false, '2025-01-01', '2025-12-31');
        $now = $this->season('Season 9', true, '2026-01-01', '2026-12-31');

        $this->night('Last year', $old, '2025-06-01 19:00:00');
        $this->night('This year', $now, '2026-06-01 19:00:00');

        $this->assertSame(['This year'], $this->listed());
    }

    public function test_a_tournament_cannot_be_orphaned_in_the_first_place(): void
    {
        // The obvious worry about filtering on a season is a row with none.
        // It cannot happen: tournaments.season_id is NOT NULL and constrained,
        // so there is no orphan for a filter to miss or a null filter to
        // sweep up. Written down because the reverse -- venue points, results,
        // registrants -- all DO allow a null user, and the difference is easy
        // to carry over by habit.
        $now = $this->season('Season 9', true, '2026-01-01', '2026-12-31');
        $this->night('In the season', $now, '2026-06-01 19:00:00');

        $this->expectException(\Illuminate\Database\QueryException::class);

        PokerTournament::create([
            'name' => 'Orphan night', 'start_time' => '2026-07-01 19:00:00',
            'venue_id' => $this->venue->id, 'season_id' => null,
        ]);
    }

    public function test_no_current_season_lists_nothing_rather_than_everything(): void
    {
        // The failure this prevents: a null season silently becoming "no
        // filter", so the page claims one season and shows all of them.
        $old = $this->season('Season 8', false, '2025-01-01', '2025-12-31');

        $this->night('Last year', $old, '2025-06-01 19:00:00');

        $this->assertSame([], $this->listed());
    }

    public function test_it_says_which_nothing_it_is(): void
    {
        // An administrator can act on an unset season and cannot act on an
        // empty one, so the two must not read alike.
        $this->season('Season 8', false, '2025-01-01', '2025-12-31');

        $this->actingAs($this->admin())->get(route('poker.tournaments.index'))->assertOk()
            ->assertSee('No season is running.')
            ->assertSee('Mark a season as current');

        $this->season('Season 9', true, '2026-01-01', '2026-12-31');

        $this->actingAs($this->admin())->get(route('poker.tournaments.index'))->assertOk()
            ->assertSee('No tournaments in Season 9 yet.')
            ->assertDontSee('No season is running.');
    }

    public function test_the_page_names_the_season_it_is_showing(): void
    {
        $now = $this->season('Season 9', true, '2026-01-01', '2026-12-31');
        $this->night('This year', $now, '2026-06-01 19:00:00');

        // In the HEADER, and now only there: the Season column that repeated
        // it on every row is gone, so the heading is the one thing telling a
        // reader which season these nights belong to. Matched as markup
        // because a bare assertSee once passed on a page whose heading said
        // nothing -- the rows were answering for it.
        $this->actingAs($this->admin())->get(route('poker.tournaments.index'))->assertOk()
            ->assertSee('<p class="u-eyebrow">Season 9</p>', false);
    }

    public function test_the_season_is_named_once_and_not_repeated_down_every_row(): void
    {
        // The Season column went with the scoping that made it constant: every
        // row carried the same name, under a heading that already says it.
        // Three columns now -- name, venue, start time -- and the empty state
        // spans all three.
        $now = $this->season('Season 9', true, '2026-01-01', '2026-12-31');
        $this->night('This year', $now, '2026-06-01 19:00:00');
        $this->night('Also this year', $now, '2026-07-01 19:00:00');

        $html = $this->actingAs($this->admin())
            ->get(route('poker.tournaments.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Season 9'),
            'The season should be named in the heading and nowhere else.');
        $this->assertStringNotContainsString('tournaments-index__season', $html);
        $this->assertSame(3, substr_count($html, '<th scope="col"'));
    }

    public function test_the_empty_state_still_spans_every_column(): void
    {
        // One cell pretending to be a row. It went from four columns to three
        // with the Season column, and a colspan left at four pushes a phantom
        // column into the header row.
        $this->season('Season 9', true, '2026-01-01', '2026-12-31');

        $this->actingAs($this->admin())->get(route('poker.tournaments.index'))->assertOk()
            ->assertSee('colspan="3"', false);
    }

    public function test_an_older_season_is_still_reachable_from_its_own_page(): void
    {
        // What the scoping costs, and where it is paid back. Dropping the rest
        // of the league's history from this list is only reasonable because
        // the season page lists what it held.
        $old = $this->season('Season 8', false, '2025-01-01', '2025-12-31');
        $this->season('Season 9', true, '2026-01-01', '2026-12-31');

        $this->night('Last year', $old, '2025-06-01 19:00:00');

        $this->actingAs($this->admin())->get(route('seasons.show', $old))->assertOk()
            ->assertSee('Last year');
    }
}
