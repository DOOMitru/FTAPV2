<?php

namespace Tests\Concerns;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Route;

/**
 * Renders every GET route once, for the checks that have to look at all of them.
 *
 * Two of those exist now -- a link inside a link, and a duplicate element id --
 * and both share the same awkward requirement: the fixture has to make each
 * page actually BUILD the markup at fault. A tournament in the future renders
 * no archive card at all, and the events page then passes a check it never ran.
 * So the tournament here is past and fully scored.
 *
 * A trait rather than a base class, for the reason BuildsTournaments gives: a
 * parent's test methods run again inside every subclass.
 */
trait RendersEveryPage
{
    /** Routes a plain GET cannot stand in for. */
    private const NOT_A_PAGE = ['up', 'storage/{path}'];

    protected User $pageAdmin;

    /** @var array<string, string> route parameter => concrete value */
    protected array $pageBindings = [];

    /** Build the records every page needs to have something to render. */
    protected function seedEveryPage(): void
    {
        $this->pageAdmin = User::factory()->create([
            'is_admin' => true, 'approval_status' => 'approved',
        ]);

        $venue = Venue::create(['name' => 'The Grand Card Room', 'address' => '100 Casino Blvd']);

        $season = PokerSeason::create([
            'name' => 'Season 1', 'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(), 'is_current' => true,
        ]);

        // PAST and scored, so the archive, the podiums and the standings are
        // all on the page rather than skipped by an @if nobody notices.
        $tournament = PokerTournament::create([
            'name' => 'Weekly Freezeout', 'start_time' => now()->subWeek(),
            'venue_id' => $venue->id, 'season_id' => $season->id,
        ]);

        $players = User::factory()->count(3)->create(['approval_status' => 'approved']);

        foreach ($players as $index => $player) {
            $this->enter($tournament, $player);
            $this->score($tournament, $player, $index + 1, 100 - $index * 10);
        }

        $this->pageBindings = [
            'user' => $this->pageAdmin->id,
            'player' => $players[0]->id,
            'venue' => $venue->id,
            'season' => $season->id,
            'tournament' => $tournament->id,
        ];
    }

    /**
     * Every GET route that answers an admin with an HTML 200.
     *
     * Routes whose parameters have no fixture are skipped rather than failed:
     * RouteSmokeTest already fails on an unbound parameter, and two tests
     * reporting the same gap says it twice.
     *
     * @return array<string, string> path => rendered HTML
     */
    protected function everyPage(): array
    {
        $pages = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (in_array($uri, self::NOT_A_PAGE, true) || str_starts_with($uri, '_')) {
                continue;
            }

            $unbound = false;

            $resolved = preg_replace_callback('/\{(\w+)\??\}/', function (array $matches) use (&$unbound) {
                if (! array_key_exists($matches[1], $this->pageBindings)) {
                    $unbound = true;

                    return $matches[0];
                }

                return $this->pageBindings[$matches[1]];
            }, $uri);

            if ($unbound) {
                continue;
            }

            $path = '/'.ltrim($resolved, '/');
            $response = $this->actingAs($this->pageAdmin)->get($path);

            if ($response->getStatusCode() !== 200
                || ! str_contains((string) $response->headers->get('content-type'), 'text/html')) {
                continue;
            }

            $pages[$path] = $response->getContent();
        }

        return $pages;
    }

    /**
     * The page as a queryable tree.
     *
     * libxml parses what the template WROTE rather than applying a browser's
     * error recovery, which is the point: these checks are looking for the
     * fault committed, not the shape a browser rescued it into.
     */
    protected function dom(string $html): DOMXPath
    {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
