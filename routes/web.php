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
    $currentSeason = \App\Models\PokerSeason::where('start_date', '<=', now())
        ->where('end_date', '>=', now())
        ->first() ?? \App\Models\PokerSeason::orderBy('start_date', 'desc')->first();

    $nextTournament = \App\Models\PokerTournament::with(['venue', 'season'])
        ->where('start_time', '>=', now())
        ->orderBy('start_time', 'asc')
        ->first();

    return view('home', compact('currentSeason', 'nextTournament'));
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

    Route::get('/texas-holdem', function () {
        return view('rules.texas-holdem');
    })->name('texas-holdem');

    Route::get('/points-structure', function () {
        $pointsStructure = \App\Models\PointsStructure::orderBy('place')->get();

        // Fetch top 3 performers of the current season for a live preview
        $currentSeason = \App\Models\PokerSeason::where('is_current', true)->first();
        $topPerformers = collect();
        
        if ($currentSeason) {
            $topPerformers = \App\Models\User::withSum(['tournamentResults' => function($query) use ($currentSeason) {
                $query->whereHas('tournament', function($q) use ($currentSeason) {
                    $q->where('season_id', $currentSeason->id);
                });
            }], 'points')
            ->orderByDesc('tournament_results_sum_points')
            ->take(3)
            ->get();
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
        ->where('start_time', '>=', now())
        ->orderBy('start_time', 'asc')
        ->paginate(2)
        ->withQueryString();

    $pastTournaments = \App\Models\PokerTournament::with(['venue', 'season', 'results'])
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
        Route::patch('users/{user}/reject', [\App\Http\Controllers\UserController::class, 'reject'])->name('users.reject');
    });
});

require __DIR__.'/auth.php';
