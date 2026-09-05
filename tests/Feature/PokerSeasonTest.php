<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PokerSeasonTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_season_is_current_by_default()
    {
        $season = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
        ]);

        $this->assertTrue($season->is_current);
    }

    public function test_only_one_season_can_be_current_on_creation()
    {
        $season1 = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
        ]);

        $this->assertTrue($season1->is_current);

        $season2 = PokerSeason::create([
            'name' => 'Season 2',
            'start_date' => now()->addMonths(4),
            'end_date' => now()->addMonths(7),
        ]);

        $this->assertTrue($season2->is_current);
        $this->assertFalse($season1->refresh()->is_current);
    }

    public function test_updating_a_season_to_current_unsets_others()
    {
        $season1 = PokerSeason::create([
            'name' => 'Season 1',
            'start_date' => now()->subMonths(4),
            'end_date' => now()->subMonths(1),
            'is_current' => true,
        ]);

        $this->assertTrue($season1->is_current);

        $season2 = PokerSeason::create([
            'name' => 'Season 2',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'is_current' => false,
        ]);

        $this->assertFalse($season2->refresh()->is_current);
        $this->assertTrue($season1->refresh()->is_current);

        $season2->update(['is_current' => true]);

        $this->assertTrue($season2->is_current);
        $this->assertFalse($season1->refresh()->is_current);
    }

    public function test_controller_store_sets_current_correctly()
    {
        $user = User::factory()->create(['is_admin' => true]);
        
        $response = $this->actingAs($user)->post(route('poker.seasons.store'), [
            'name' => 'Season 1',
            'start_date' => '2024-01-01',
            'end_date' => '2024-03-31',
            'is_current' => '1',
        ]);

        $response->assertRedirect(route('poker.seasons.index'));
        $this->assertTrue(PokerSeason::first()->is_current);
    }

    /**
     * The two dates share a line.
     *
     * A start and an end are one range, and reading them apart is reading half
     * a fact. .l-grid does the pairing and drops to a single column when two
     * 16rem tracks no longer fit, so the breakpoint is the card's real width
     * rather than a number guessed at here -- which is also why this asserts
     * the STRUCTURE and not the widths. The project has no browser tests, so a
     * media query is not a thing that can be checked; that the two inputs hang
     * off one grid is.
     */
    public function test_both_season_forms_pair_the_dates_on_one_line(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $season = PokerSeason::create([
            'name' => 'Season 41',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        $pages = [
            route('poker.seasons.create'),
            route('poker.seasons.edit', $season),
        ];

        foreach ($pages as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $dom = new \DOMDocument();
            @$dom->loadHTML($html);

            $paired = (new \DOMXPath($dom))->query(
                '//div[contains(@class, "l-grid")]'
                .'[.//input[@name="start_date"]][.//input[@name="end_date"]]'
            );

            $this->assertSame(
                1,
                $paired->length,
                "Both dates must sit inside one .l-grid on {$url}."
            );
        }
    }
}
