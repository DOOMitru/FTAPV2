<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * The hooks the card layouts are built on.
 *
 * Below 48rem every admin index reflows from a table into one card per record.
 * The shell -- grid row, clipped header, cell reset, actions held at the end --
 * is .table--cards; each page supplies only its own grid-template-areas.
 *
 * The layouts themselves are CSS, and this project has no browser tests, so
 * they are verified by screenshot. What CAN be checked is that the markup still
 * offers the CSS what it needs: drop the modifier or a cell class and the page
 * silently reverts to a five-column table that scrolls off a phone, while every
 * other test stays green.
 */
class AdminIndexCardLayoutTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    // NOT seed(): Illuminate\Foundation\Testing\TestCase::seed() is public, and a
    // private override is a fatal error at load time.
    private function seedRecords(): void
    {
        PointsStructure::create(['place' => 1, 'points' => 100]);

        $tournament = $this->tournament();
        $player = User::factory()->create([
            'first_name' => 'Wanda', 'last_name' => 'Reeve', 'approval_status' => 'approved',
        ]);

        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        // At the tournament's venue on its date. The venue-points list filters
        // to the nearest tournament by inferring that link, so a point awarded
        // on any other day would leave this page with no rows to class.
        VenuePoints::create([
            'user_id' => $player->id, 'user_name' => 'Wanda Reeve',
            'venue_id' => $tournament->venue_id, 'amount' => 40,
            'event_date' => $tournament->start_time->toDateString(),
            'season_id' => PokerSeason::first()->id,
        ]);

        Sponsor::create([
            'name' => 'Bushwakker', 'logo_path' => 'sponsors/x.png',
            'tier' => 'premium', 'sort_order' => 1,
        ]);
    }

    /** @return array<string, array{0: string, 1: string, 2: list<string>}> */
    public static function pages(): array
    {
        return [
            'tournaments' => ['poker.tournaments.index', 'tournaments-index__table', [
                'tournaments-index__name', 'tournaments-index__venue',
                'tournaments-index__start',
            ]],
            'sponsors' => ['sponsors.index', 'sponsors-index__table', [
                'sponsors-index__logo', 'sponsors-index__name', 'sponsors-index__tier',
            ]],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_table_opts_into_the_card_layout(string $route, string $wrapper): void
    {
        $this->seedRecords();

        $html = $this->actingAs($this->admin())->get(route($route))->assertOk()->getContent();

        // Without the modifier there is no grid at all; without the wrapper
        // class the page's area map addresses nothing.
        $this->assertStringContainsString('table--cards', $html);
        $this->assertStringContainsString($wrapper, $html);
    }

    /**
     * @param  list<string>  $hooks
     */
    #[DataProvider('pages')]
    public function test_every_cell_the_grid_places_is_classed(string $route, string $wrapper, array $hooks): void
    {
        $this->seedRecords();

        $html = $this->actingAs($this->admin())->get(route($route))->assertOk()->getContent();

        foreach ($hooks as $hook) {
            $this->assertStringContainsString($hook, $html, "The grid has no cell to place for {$hook}.");
        }

        // The pages no longer agree about actions, so this is not asserted
        // across them: the tournaments list moved its Edit and Delete to the
        // tournament's own page and has no actions column at all, while the
        // sponsors list still carries one. Each page's own cells are what the
        // hooks above check.
        $this->assertNotSame('', $wrapper);
    }
}
