<?php

/**
 * Seeds the sweep's database and prints the URLs to visit, as JSON.
 *
 * Run through `artisan tinker`, so it has the whole framework: the routing
 * table, the models, and Tests\Support\RouteFixtures -- the same fixtures and
 * the same parameter map RouteSmokeTest uses, because two maps would be a
 * second definition free to drift.
 *
 * It prints and does not assert. bin/sweep.mjs does the looking; this only
 * decides what there is to look at.
 */

use App\Models\Sponsor;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RouteFixtures;

/**
 * URIs a plain GET cannot sweep, for the reasons RouteSmokeTest gives.
 */
const SKIPPED = [
    'up',                   // health check, no view
    'storage/{path}',       // static file serving
];

['admin' => $admin, 'player' => $player, 'bindings' => $bindings] = RouteFixtures::seed();

/*
 * A known password on both, because the sweep signs in through the real form
 * rather than through a back door. The old harness added a temporary route to
 * routes/web.php and restored it from a .sweepbak afterwards, which is how
 * probe instrumentation once reached a commit.
 *
 * Assigned rather than passed to create(): the password cast hashes on the way
 * in, so a Hash::make() value would be hashed twice and no login would work --
 * which cost a while to find the first time.
 */
foreach ([$admin, $player] as $account) {
    $account->password = 'sweep-password';
    $account->forceFill([
        'approval_status' => 'approved',
        'approval_decided_at' => now(),
        'email_verified_at' => now(),
    ])->save();
}

/*
 * A real file behind the seeded sponsor's logo_path.
 *
 * Without it the storage route answers 403 for every page that draws a
 * sponsor, and the sweep reported a console error on the home page that had
 * nothing to do with the home page. A fixture that points at a file which does
 * not exist is a fixture that reports itself.
 *
 * One transparent pixel, written to the public disk the controller stores
 * logos on -- which is the project's own, because that is where the server
 * this runs against will look. It is the only thing the sweep writes outside
 * its work directory, it is named for the fixture rather than for anything a
 * real sponsor might be called, and bin/sweep.sh removes it on the way out.
 */
Storage::disk('public')->put(
    Sponsor::first()->logo_path,
    (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/58BAwAI/AL+4d0AAAAASUVORK5CYII='
    )
);

$urls = [];
$unbound = [];

foreach (Route::getRoutes() as $route) {
    if (! in_array('GET', $route->methods(), true)) {
        continue;
    }

    $uri = $route->uri();

    if (in_array($uri, SKIPPED, true) || str_starts_with($uri, '_')) {
        continue;
    }

    $resolved = preg_replace_callback(
        '/\{(\w+)\??\}/',
        function (array $matches) use ($bindings, $uri, &$unbound) {
            if (! array_key_exists($matches[1], $bindings)) {
                $unbound[] = $uri.' {'.$matches[1].'}';

                return $matches[0];
            }

            return (string) $bindings[$matches[1]];
        },
        $uri
    );

    $urls[] = ['name' => $route->getName() ?? $uri, 'url' => '/'.ltrim((string) $resolved, '/')];
}

// Reported rather than skipped, so a new {thing} in a URI cannot quietly go
// unvisited. RouteSmokeTest fails the suite on the same condition.
echo json_encode([
    'urls' => $urls,
    'unbound' => $unbound,
    'admin' => $admin->email,
    'player' => $player->email,
    'password' => 'sweep-password',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
