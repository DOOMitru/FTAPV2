<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * No page nests one link inside another.
 *
 * <a> may not contain <a>: it is interactive content, and the content model of
 * an anchor excludes it. Nothing complains. The server sends exactly what the
 * template says, every test passes, and the BROWSER is where it goes wrong --
 * on the nested start tag the parser acts as though it had seen </a>, closes
 * the outer link, and leaves the rest of what was inside it as a SIBLING.
 *
 * On the events page that turned each archive card into three: a truncated
 * card, a loose podium and a stray "Full results" foot, each landing in the
 * grid as its own cell. Forty-five cards became a hundred and thirty-five
 * items in four columns.
 *
 * It arrived when player names were linked throughout the app. The archive
 * card is a whole-card anchor, so a link on a name inside it was a link inside
 * a link -- and only when signed in, because a guest is shown "Sign in to see
 * the results" instead of the podium and so never nests anything.
 *
 * libxml parses the markup as WRITTEN rather than applying the browser's
 * recovery, which is what makes this checkable at all: the test sees the fault
 * the template committed, not the shape the browser rescued it into.
 *
 * It also means the CONSEQUENCE cannot be asserted here. Server-side the card
 * still contains its podium; only a real parser takes it apart. That half was
 * verified in a browser -- 45 cards, 135 grid children, heights of 244, 228
 * and 34px in a row -- and the nesting below is what stands guard.
 */
class NestedAnchorTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private const SKIPPED = ['up', 'storage/{path}'];

    private User $admin;

    /** @var array<string, string> */
    private array $bindings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        $season = PokerSeason::create([
            'name' => 'Season 1', 'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(), 'is_current' => true,
        ]);

        // PAST and fully scored, so the events page actually has archive cards
        // with a podium on them. A future tournament renders no archive at all
        // and the page would pass without ever building the markup at fault.
        $tournament = PokerTournament::create([
            'name' => 'Weekly Freezeout', 'start_time' => now()->subWeek(),
            'venue_id' => $venue->id, 'season_id' => $season->id,
        ]);

        $players = User::factory()->count(3)->create(['approval_status' => 'approved']);

        foreach ($players as $index => $player) {
            $this->enter($tournament, $player);
            $this->score($tournament, $player, $index + 1, 100 - $index * 10);
        }

        $this->bindings = [
            'user' => $this->admin->id,
            'player' => $players[0]->id,
            'venue' => $venue->id,
            'season' => $season->id,
            'tournament' => $tournament->id,
        ];
    }

    public function test_no_get_route_nests_a_link_inside_a_link(): void
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (in_array($uri, self::SKIPPED, true) || str_starts_with($uri, '_')) {
                continue;
            }

            $unbound = false;

            $resolved = preg_replace_callback('/\{(\w+)\??\}/', function (array $m) use (&$unbound) {
                if (! array_key_exists($m[1], $this->bindings)) {
                    $unbound = true;

                    return $m[0];
                }

                return $this->bindings[$m[1]];
            }, $uri);

            if ($unbound) {
                continue;
            }

            $response = $this->actingAs($this->admin)->get('/'.ltrim($resolved, '/'));

            if ($response->getStatusCode() !== 200
                || ! str_contains((string) $response->headers->get('content-type'), 'text/html')) {
                continue;
            }

            $nested = $this->xpath($response->getContent())->query('//a//a');

            foreach ($nested as $node) {
                $offenders[] = sprintf('/%s — <a href="%s"> inside another link',
                    ltrim($resolved, '/'), $node->getAttribute('href'));
            }
        }

        $this->assertSame([], $offenders, "A link inside a link. The browser closes the outer one and\n"
            ."spills the rest of it into the parent as siblings:\n  ".implode("\n  ", array_unique($offenders))."\n");
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
