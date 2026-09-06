<?php

use App\Http\Controllers\ProfileController;
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
    $currentSeason = \App\Models\PokerSeason::current();

    $nextTournament = \App\Models\PokerTournament::with(['venue', 'season'])
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

    if (auth()->check() && $currentSeason) {
        $standings = $currentSeason->results()->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => [
                'name' => $rows->first()->player_name,
                'points' => $rows->sum('points'),
                'wins' => $rows->where('place', 1)->count(),
            ])
            ->values();

        $topByPoints = $standings->sortByDesc('points')->take(3)->values();

        // Anyone on nil wins is not "leading on wins"; an empty list says that
        // honestly, where three names on zero would not.
        $topByWins = $standings->where('wins', '>', 0)->sortByDesc('wins')->take(3)->values();
    }

    // ordered() -- the same scope the admin list uses, so what an
    // administrator arranges is what this page renders.
    $sponsors = \App\Models\Sponsor::ordered()->get();

    return view('home', compact('currentSeason', 'nextTournament', 'sponsors', 'topByPoints', 'topByWins'));
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

    Route::get('/texas-holdem', function (\App\Support\PokerHandSampler $sampler) {
        // Dealt per request. The hierarchy is about the SHAPE of a hand, and a
        // fixed picture teaches the suit along with it.
        return view('rules.texas-holdem', ['hands' => $sampler->hierarchy()]);
    })->name('texas-holdem');

    Route::get('/points-structure', function () {
        $pointsStructure = \App\Models\PointsStructure::orderBy('place')->get();

        // Fetch top 3 performers of the current season for a live preview
        $currentSeason = \App\Models\PokerSeason::current();
        $topPerformers = collect();
        
        if ($currentSeason) {
            $topPerformers = \App\Models\User::withSum(['tournamentResults' => function($query) use ($currentSeason) {
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
    $upcomingTournaments = \App\Models\PokerTournament::with(['venue', 'season'])
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
    $pastTournaments = \App\Models\PokerTournament::with(['venue', 'season', 'results'])
        ->withCount('registrants')
        ->where('start_time', '<', now())
        ->orderBy('start_time', 'desc')
        ->get();

    return view('events', compact('upcomingTournaments', 'pastTournaments'));
})->name('events');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::post('/contact', [\App\Http\Controllers\ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Player-facing tournament and season views. Deliberately outside the
    // /poker prefix, which is admin-only.
    Route::get('/tournaments/{tournament}', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'show'])
        ->name('tournaments.show');
    Route::post('/tournaments/{tournament}/register', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'register'])
        ->name('tournaments.register');
    Route::delete('/tournaments/{tournament}/unregister', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'unregister'])
        ->name('tournaments.unregister');
    Route::get('/seasons/{season}', [\App\Http\Controllers\Poker\PokerSeasonController::class, 'show'])
        ->name('seasons.show');

    Route::middleware('admin')->prefix('poker')->name('poker.')->group(function () {
        Route::resource('seasons', \App\Http\Controllers\Poker\PokerSeasonController::class)->except(['show']);
        Route::resource('venues', \App\Http\Controllers\Poker\VenueController::class);
        Route::resource('tournaments', \App\Http\Controllers\Poker\PokerTournamentController::class)->except(['show']);

        // Recording a knockout, not editing a tournament, so it sits beside the
        // resource rather than inside it. Admin-only by this group.
        Route::post('tournaments/{tournament}/eliminate', [\App\Http\Controllers\Poker\PokerTournamentController::class, 'eliminate'])
            ->name('tournaments.eliminate');
        Route::resource('results', \App\Http\Controllers\Poker\PokerTournamentResultController::class)->except(['show']);
        Route::resource('registrants', \App\Http\Controllers\Poker\PokerTournamentRegistrantController::class)->except(['show']);
        Route::resource('venue-points', \App\Http\Controllers\Poker\VenuePointsController::class)->except(['show']);
        Route::resource('points-structure', \App\Http\Controllers\Poker\PointsStructureController::class)->except(['show']);
    });

    Route::middleware('admin')->group(function () {
        // create and store were removed in Phase 0 as dead routes: the
        // controller had no matching methods and both returned HTTP 500. They
        // return here with real ones, for the Register Player flow.
        Route::resource('users', \App\Http\Controllers\UserController::class);

        // Admission to the league. Inside the admin group, which is what makes
        // a player's attempt a 403 without a separate check in the controller.
        Route::patch('users/{user}/approve', [\App\Http\Controllers\UserController::class, 'approve'])->name('users.approve');
        // Sponsors shown on the home page. Not under /poker: that prefix is
        // league operations, and this is site content.
        Route::resource('sponsors', \App\Http\Controllers\SponsorController::class)->except(['show']);

        Route::patch('users/{user}/reject', [\App\Http\Controllers\UserController::class, 'reject'])->name('users.reject');

        // Re-issuing the two links a player needs to get in. Separate from
        // verification.send, which acts on the authenticated user: an
        // administrator acting on someone else's account is a different
        // operation and cannot reuse it.
        Route::post('users/{user}/invite', [\App\Http\Controllers\UserController::class, 'sendInvite'])->name('users.invite');
        Route::post('users/{user}/verification', [\App\Http\Controllers\UserController::class, 'sendVerification'])->name('users.verification');
    });
});

require __DIR__.'/auth.php';
