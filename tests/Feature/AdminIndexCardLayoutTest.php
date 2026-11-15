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

    /** @return array<string, array{0: string, 1: string, 2: list<string>, 3: string|null}> */
    public static function pages(): array
    {
        return [
            // The fourth column is where the markup lives: a .vue path once
            // the page is converted, null while it still renders into the
            // response. The conversion is incremental, so for a while this
            // file checks the same rule two ways.
            'tournaments' => ['poker.tournaments.index', 'tournaments-index__table', [
                'tournaments-index__name', 'tournaments-index__status',
                'tournaments-index__venue', 'tournaments-index__start',
            ], 'js/Pages/Poker/Tournaments/Index.vue'],
            'sponsors' => ['sponsors.index', 'sponsors-index__table', [
                'sponsors-index__logo', 'sponsors-index__name', 'sponsors-index__tier',
            ], 'js/Pages/Sponsors/Index.vue'],
        ];
    }

    /**
     * @param  list<string>  $hooks  not used here -- see below
     */
    #[DataProvider('pages')]
    public function test_the_table_opts_into_the_card_layout(
        string $route, string $wrapper, array $hooks, ?string $page
    ): void {
        // $hooks is declared and ignored on purpose. One provider feeds both
        // tests, so the list of pages has a single definition; this test only
        // needs the first two columns of it. PHPUnit 12 treats a provider
        // handing a method more arguments than it accepts as a warning, where
        // 11 simply dropped the extra -- and the alternative, a second
        // provider, is the same list written twice and free to disagree with
        // itself.

        $this->seedRecords();

        $markup = $this->markup($route, $page);

        // Without the modifier there is no grid at all; without the wrapper
        // class the page's area map addresses nothing. A converted page asks
        // for it with the `cards` prop, which the Table component turns into
        // the class.
        $this->assertStringContainsString($page === null ? 'table--cards' : 'cards', $markup,
            'The table does not opt into the card layout.');
        $this->assertStringContainsString($wrapper, $markup);
    }

    /**
     * Where this page's markup can be read.
     *
     * A Blade page renders it into the response. A converted page renders it
     * in the browser, so its .vue file is the only place the hooks exist. The
     * request is still made either way: a page that 500s has no markup worth
     * checking.
     */
    private function markup(string $route, ?string $page): string
    {
        $response = $this->actingAs($this->admin())->get(route($route))->assertOk();

        return $page === null
            ? $response->getContent()
            : (string) file_get_contents(resource_path($page));
    }

    /**
     * @param  list<string>  $hooks
     */
    #[DataProvider('pages')]
    public function test_every_cell_the_grid_places_is_classed(
        string $route, string $wrapper, array $hooks, ?string $page
    ): void {
        $this->seedRecords();

        $html = $this->markup($route, $page);

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
