<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a tournament stands: still to come, over and owing results, or done.
 *
 * Owner's request, 2026-11-14 (docs/superpowers/specs/2026-11-14-tournament-status-design.md):
 * the tournaments list and a venue's Recent Tournaments tell the three apart at
 * a glance. PokerTournament::status() is the one definition; the pages only
 * carry it.
 */
class TournamentStatusTest extends TestCase
{
    use RefreshDatabase;

    private PokerSeason $season;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = PokerSeason::create([
            'name' => 'Season 9', 'start_date' => now()->subYear(),
            'end_date' => now()->addYear(), 'is_current' => true,
        ]);

        $this->venue = Venue::create(['name' => 'Hall', 'address' => '1 St']);
    }

    private function night(string $name, mixed $when, bool $published = false): PokerTournament
    {
        $night = PokerTournament::create([
            'name' => $name, 'start_time' => $when,
            'venue_id' => $this->venue->id, 'season_id' => $this->season->id,
        ]);

        if ($published) {
            // Not fillable, on purpose: publishing is not a form field.
            $night->forceFill(['published_at' => now()])->save();
        }

        return $night;
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    public function test_a_night_still_to_come_is_upcoming(): void
    {
        $this->assertSame('upcoming', $this->night('Friday', now()->addDay())->status());
    }

    public function test_a_night_that_has_started_is_awaiting_results(): void
    {
        // The minute it starts, not the minute after: a night being played is
        // not "upcoming" any more.
        $this->travelTo(now()->startOfMinute());

        $this->assertSame('awaiting', $this->night('Tonight', now())->status());
        $this->assertSame('awaiting', $this->night('Last week', now()->subWeek())->status());
    }

    public function test_a_past_night_with_results_out_is_published(): void
    {
        $this->assertSame('published', $this->night('Monday', now()->subWeek(), published: true)->status());
    }

    public function test_published_wins_over_a_future_date(): void
    {
        // A start time edited after publishing still has its results out.
        $this->assertSame('published', $this->night('Moved', now()->addWeek(), published: true)->status());
    }

    /** One of each, named for what it should read as. */
    private function oneOfEach(): void
    {
        $this->night('Next', now()->addWeek());
        $this->night('Owing', now()->subDay());
        $this->night('Done', now()->subWeek(), published: true);
    }

    /** @return array<string, string> name => status, from a page's rows */
    private function statuses(array $rows): array
    {
        return collect($rows)->pluck('status', 'name')->all();
    }

    public function test_the_tournaments_list_sends_each_rows_status(): void
    {
        $this->oneOfEach();

        $props = $this->actingAs($this->admin())
            ->get(route('poker.tournaments.index'))->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(
            ['Next' => 'upcoming', 'Owing' => 'awaiting', 'Done' => 'published'],
            $this->statuses($props['tournaments']['data']),
        );
    }

    public function test_the_venue_page_sends_each_rows_status(): void
    {
        $this->oneOfEach();

        $props = $this->actingAs($this->admin())
            ->get(route('poker.venues.show', $this->venue))->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(
            ['Next' => 'upcoming', 'Owing' => 'awaiting', 'Done' => 'published'],
            $this->statuses($props['tournaments']),
        );
    }
}
