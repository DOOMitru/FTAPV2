<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\Poker\PointsStructureController;
use App\Http\Controllers\Poker\PokerSeasonController;
use App\Http\Controllers\Poker\PokerTournamentController;
use App\Http\Controllers\Poker\PokerTournamentRegistrantController;
use App\Http\Controllers\Poker\VenueController;
use App\Http\Controllers\Poker\VenuePointsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SponsorController;
use App\Http\Controllers\UserController;
use App\Models\PointsStructure;
use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\Sponsor;
use App\Models\User;
use App\Support\PokerHandSampler;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // The flag, like every other page. This asked which season's dates
    // contained today and fell back to the most recent, so the home page could
    // name a different season from the one the dashboard called current.
    $currentSeason = PokerSeason::current();

    $nextTournament = PokerTournament::with(['venue', 'season'])
        // Same withExists the events page uses, so the shared card can say
        // "You're registered" instead of offering a button the controller
        // would refuse. One exists() rather than loading every registrant.
        ->when(auth()->check(), fn ($query) => $query->withExists([
            'registrants as viewer_registered' => fn ($r) => $r->where('user_id', auth()->id()),
        ]))
        // The card asks hasRecordedResults() before offering to withdraw, and
        // that falls back to a query per tournament without this.
        ->withCount('results')
        ->where('start_time', '>=', now())
        ->orderBy('start_time', 'asc')
        ->first();

    // Season standings, for signed-in players only. Grouped the same way
    // PokerSeasonController@show groups them -- points summed, a win being
    // place 1 -- so the home page and the season page cannot disagree about
    // what either word means.
    $topByPoints = collect();
    $topByWins = collect();
    $topByRank = collect();

    if (auth()->check() && $currentSeason) {
        $standings = $currentSeason->results()->with('user')->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => [
                // The account as well as the name: these cards link each
                // player to their figures, and a result whose player has since
                // been deleted has a name and no account to point at.
                'user' => $rows->first()->user,
                'name' => $rows->first()->player_name,
                'points' => $rows->sum('points'),
                'wins' => $rows->where('place', 1)->count(),
            ])
            ->values();

        $topByPoints = $standings->sortByDesc('points')->take(3)->values();

        // Anyone on nil wins is not "leading on wins"; an empty list says that
        // honestly, where three names on zero would not.
        $topByWins = $standings->where('wins', '>', 0)->sortByDesc('wins')->take(3)->values();

        // The same board the dashboard puts a player's own rank against, so the
        // two cannot disagree about what a rank is: points per tournament
        // ENTERED, not points.
        // rankings() carries user_id rather than the model, so the three that
        // are actually shown are resolved here -- three finds, not eighty.
        $ranked = $currentSeason->rankings()->take(3)->values();
        $rankedUsers = User::whereIn('id', $ranked->pluck('user_id'))->get()->keyBy('id');

        $topByRank = $ranked->map(fn (array $row) => [
            ...$row,
            'user' => $rankedUsers[$row['user_id']] ?? null,
        ]);
    }

    // ordered() -- the same scope the admin list uses, so what an
    // administrator arranges is what this page renders.
    $sponsors = Sponsor::ordered()->get();

    return view('home', compact('currentSeason', 'nextTournament', 'sponsors', 'topByRank', 'topByWins', 'topByPoints'));
})->name('home');

Route::prefix('about')->name('about.')->group(function () {
    Route::get('/', function () {
        return view('about.index');
    })->name('index');

    // Redirect old routes to the new combined about page
    Route::redirect('/mission', '/about')->name('mission');
    Route::redirect('/sponsors', '/about#become-a-sponsor')->name('sponsors');
});

Route::prefix('rules')->name('rules.')->group(function () {
    Route::get('/regulations', function () {
        return view('rules.tournament');
    })->name('tournament');

    Route::redirect('/tournament', '/rules/regulations')->name('old-tournament');
    Route::redirect('/final-tournament', '/rules/regulations#final-stakes')->name('final-tournament');

    Route::get('/conduct', function () {
        return view('rules.betting');
    })->name('betting');

    Route::redirect('/betting', '/rules/conduct')->name('old-betting');
    Route::redirect('/behaviour', '/rules/conduct#conduct-rules')->name('behaviour');

    Route::get('/texas-holdem', function (PokerHandSampler $sampler) {
        // Dealt per request. The hierarchy is about the SHAPE of a hand, and a
        // fixed picture teaches the suit along with it.
        return view('rules.texas-holdem', ['hands' => $sampler->hierarchy()]);
    })->name('texas-holdem');

    Route::get('/points-structure', function () {
        $pointsStructure = PointsStructure::orderBy('place')->get();

        // Fetch top 3 performers of the current season for a live preview
        $currentSeason = PokerSeason::current();
        $topPerformers = collect();
        
        if ($currentSeason) {
            $topPerformers = User::withSum(['tournamentResults' => function($query) use ($currentSeason) {
                $query->whereHas('tournament', function($q) use ($currentSeason) {
                    $q->where('season_id', $currentSeason->id);
                });
            }], 'points')
            ->orderByDesc('tournament_results_sum_points')
            ->take(3)
            ->get()
            // Only players who have actually scored. Without this the query
            // returns the first three rows of the users table with a null sum
            // apiece, and the panel announces three season leaders on nought
            // points -- which is what it did for every day of a season before
            // its first result was recorded.
            //
            // Filtering after take(3) rather than inside the query is safe
            // because the rows are already ordered by that same sum: if the
            // third has none, nobody below it has any either. Doing it in SQL
            // would mean HAVING on a correlated subquery alias with no GROUP
            // BY, which is not something to rely on across drivers.
            ->filter(fn ($performer) => $performer->tournament_results_sum_points > 0)
            ->values();
        }

        return view('rules.points-structure', compact('pointsStructure', 'topPerformers', 'currentSeason'));
    })->name('points-structure');
});

Route::get('/events', function () {
    // Two at a time: an upcoming event card carries a map and is tall, so a
    // long season scrolls forever otherwise. withQueryString keeps any future
    // filters across page links.
    $upcomingTournaments = PokerTournament::with(['venue', 'season'])
        // Lets the card show "You are registered" instead of offering a button
        // the controller would reject. One exists() per row, not an N+1.
        ->when(auth()->check(), fn ($query) => $query->withExists([
            'registrants as viewer_registered' => fn ($r) => $r->where('user_id', auth()->id()),
        ]))
        // Likewise for the withdrawal guard: one count per row, not a query
        // per card.
        ->withCount('results')
        ->where('start_time', '>=', now())
        ->orderBy('start_time', 'asc')
        ->paginate(2)
        ->withQueryString();

    // withCount('registrants'): podium() needs the size of the field to know
    // which places are settled, and this page draws one podium per card.
    //
    // results, not results.user: the podium prints the snapshotted
    // player_name and does not link it -- the card is already a link, and a
    // link inside a link is taken apart by the browser.
    $pastTournaments = PokerTournament::with(['venue', 'season', 'results'])
        ->withCount('registrants')
        ->where('start_time', '<', now())
        ->orderBy('start_time', 'desc')
        ->get();

    return view('events', compact('upcomingTournaments', 'pastTournaments'));
})->name('events');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // The literal path is declared FIRST. Behind /notifications/{notification}
    // it would be matched as an id and 404 -- and because ids are UUIDs, no
    // real notification could ever shadow it, so the bug would be invisible.
    Route::delete('/notifications/read', [NotificationController::class, 'clearRead'])
        ->name('notifications.clear-read');
    Route::patch('/notifications/{notification}', [NotificationController::class, 'update'])
        ->name('notifications.update');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy');

    // Player-facing tournament and season views. Deliberately outside the
    // /poker prefix, which is admin-only.
    Route::get('/tournaments/{tournament}', [PokerTournamentController::class, 'show'])
        ->name('tournaments.show');
    Route::post('/tournaments/{tournament}/register', [PokerTournamentController::class, 'register'])
        ->name('tournaments.register');
    Route::delete('/tournaments/{tournament}/unregister', [PokerTournamentController::class, 'unregister'])
        ->name('tournaments.unregister');
    Route::get('/seasons/{season}', [PokerSeasonController::class, 'show'])
        ->name('seasons.show');

    // One player's figures, readable by any signed-in player. Inside the auth
    // group and nowhere near /poker: this is not administration, it is the
    // league looking at itself.
    Route::get('/players/{player}', [PlayerController::class, 'show'])
        ->name('players.show');

    // The league's records, readable by anyone signed in. A player has a
    // reason to look these up -- where the league plays, what is scheduled,
    // how a season went -- and none of them expose anything a player should
    // not see.
    //
    // Only the three INDEXES. Everything that changes a record, and the venue
    // detail page (takings, leaderboards, per-player histories), stays in the
    // admin group below. The names keep their poker. prefix so every existing
    // link still resolves.
    Route::prefix('poker')->name('poker.')->group(function () {
        Route::get('seasons', [PokerSeasonController::class, 'index'])
            ->name('seasons.index');
        Route::get('venues', [VenueController::class, 'index'])
            ->name('venues.index');
        Route::get('tournaments', [PokerTournamentController::class, 'index'])
            ->name('tournaments.index');
    });

    Route::middleware('admin')->prefix('poker')->name('poker.')->group(function () {
        Route::resource('seasons', PokerSeasonController::class)->except(['show', 'index']);
        Route::resource('venues', VenueController::class)->except(['index']);
        Route::resource('tournaments', PokerTournamentController::class)->except(['show', 'index']);

        // Recording a knockout, not editing a tournament, so it sits beside the
        // resource rather than inside it. Admin-only by this group.
        Route::post('tournaments/{tournament}/eliminate', [PokerTournamentController::class, 'eliminate'])
            ->name('tournaments.eliminate');

        // Declaring results final. Not part of the tournaments resource: it
        // sends messages to players and closes the record, which is not what
        // "update a tournament" means.
        Route::post('tournaments/{tournament}/publish', [PokerTournamentController::class, 'publish'])
            ->name('tournaments.publish');
        Route::delete('tournaments/{tournament}/publish', [PokerTournamentController::class, 'unpublish'])
            ->name('tournaments.unpublish');
        // destroy alone. The listing and its forms are gone -- entries are made
        // from the tournament's own Register players dialog -- but removing a
        // mistaken one is offered on that same page, which is where an
        // administrator is standing when they notice it.
        Route::resource('registrants', PokerTournamentRegistrantController::class)->only(['destroy']);
        // create and store alone. The listing is gone -- venue points are read
        // on the venue they were earned at -- and the form is reached from
        // that page with the venue already chosen.
        Route::resource('venue-points', VenuePointsController::class)->only(['create', 'store']);
        Route::resource('points-structure', PointsStructureController::class)->except(['show']);
    });

    Route::middleware('admin')->group(function () {
        // create and store were removed in Phase 0 as dead routes: the
        // controller had no matching methods and both returned HTTP 500. They
        // return here with real ones, for the Register Player flow.
        Route::resource('users', UserController::class);

        // Admission to the league. Inside the admin group, which is what makes
        // a player's attempt a 403 without a separate check in the controller.
        Route::patch('users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
        // Sponsors shown on the home page. Not under /poker: that prefix is
        // league operations, and this is site content.
        Route::resource('sponsors', SponsorController::class)->except(['show']);

        Route::patch('users/{user}/reject', [UserController::class, 'reject'])->name('users.reject');

        // Re-issuing the two links a player needs to get in. Separate from
        // verification.send, which acts on the authenticated user: an
        // administrator acting on someone else's account is a different
        // operation and cannot reuse it.
        Route::post('users/{user}/invite', [UserController::class, 'sendInvite'])->name('users.invite');
        Route::post('users/{user}/verification', [UserController::class, 'sendVerification'])->name('users.verification');
    });
});

require __DIR__.'/auth.php';
