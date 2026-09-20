<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\ScansTemplates;
use Tests\TestCase;

/**
 * A row that is a link should look like one row, not three.
 *
 * Nothing in this system strips the browser's default underline from an <a>,
 * and .entry holds three pieces of text -- title, date, season. So a linked row
 * arrived with all three underlined, and the venue page's ten of them read as a
 * wall of thirty links.
 */
class LinkedRowTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase, ScansTemplates;

    public function test_the_venue_page_marks_its_rows_as_links(): void
    {
        $this->tournament('Autumn Showdown');
        $venue = Venue::first();

        PokerTournament::create([
            'name' => 'Winter Open', 'start_time' => now()->subWeek(),
            'venue_id' => $venue->id, 'season_id' => PokerSeason::first()->id,
        ]);

        // Two rows at this venue, so the page has a list to draw.
        $this->assertCount(
            2,
            $this->actingAs($this->admin())->get(route('poker.venues.show', $venue))
                ->assertOk()->viewData('page')['props']['tournaments']
        );

        $page = (string) file_get_contents(resource_path('js/Pages/Poker/Venues/Show.vue'));

        $this->assertStringContainsString('entry entry--link', $page);

        // The chevron is the affordance the underlines were doing badly, and it
        // is always drawn -- a hover state says nothing on a touch screen.
        $this->assertStringContainsString('entry__go', $page);
    }

    public function test_no_view_links_a_row_without_marking_it(): void
    {
        // An <a class="entry"> with no entry--link is the exact fault this
        // fixes, and it is invisible in a test that only checks the page loads.
        $offenders = [];

        foreach ($this->templates() as $relative => $body) {
            // <Link> as well as <a>: Inertia's component renders an anchor, so
            // a linked row written in Vue is underlined three times over in
            // exactly the same way.
            if (preg_match_all('/<(?:a|Link)\b[^>]*class="([^"]*\bentry\b[^"]*)"/', $body, $matches)) {
                foreach ($matches[1] as $classes) {
                    if (! str_contains($classes, 'entry--link')) {
                        $offenders[] = $relative;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['A linked row needs entry--link, or every word in it is underlined:'],
            $offenders,
        )));
    }
}
