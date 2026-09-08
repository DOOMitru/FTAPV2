<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\Concerns\BuildsTournaments;
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
    use BuildsTournaments, RefreshDatabase;

    public function test_the_venue_page_marks_its_rows_as_links(): void
    {
        $this->tournament('Autumn Showdown');
        $venue = Venue::first();

        PokerTournament::create([
            'name' => 'Winter Open', 'start_time' => now()->subWeek(),
            'venue_id' => $venue->id, 'season_id' => PokerSeason::first()->id,
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('poker.venues.show', $venue))->assertOk()->getContent();

        $this->assertStringContainsString('entry entry--link', $html);

        // The chevron is the affordance the underlines were doing badly, and it
        // is always drawn -- a hover state says nothing on a touch screen.
        $this->assertStringContainsString('entry__go', $html);
    }

    public function test_no_view_links_a_row_without_marking_it(): void
    {
        // An <a class="entry"> with no entry--link is the exact fault this
        // fixes, and it is invisible in a test that only checks the page loads.
        $offenders = [];
        $root = resource_path('views').DIRECTORY_SEPARATOR;

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            $body = file_get_contents($file->getRealPath());

            if (preg_match_all('/<a\b[^>]*class="([^"]*\bentry\b[^"]*)"/', $body, $matches)) {
                foreach ($matches[1] as $classes) {
                    if (! str_contains($classes, 'entry--link')) {
                        $offenders[] = str_replace($root, '', $file->getRealPath());
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
