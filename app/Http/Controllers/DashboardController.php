<?php

namespace App\Http\Controllers;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use App\Support\SeasonFigures;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        // The next five, and how many there are altogether.
        //
        // take(5) on the QUERY, not on the collection it returns. Slicing after
        // the fact loaded every future tournament in the league to display five
        // of them, and a league that schedules a year ahead pays for all of it
        // on every dashboard load.
        $upcoming = PokerTournament::where('start_time', '>', now());
        $upcomingCount = (clone $upcoming)->count();

        $upcomingTournaments = $upcoming
            ->with(['venue', 'registrants'])
            // The row offers a withdrawal only while nothing is recorded, and
            // hasRecordedResults() queries per tournament without a count.
            ->withCount('results')
            ->orderBy('start_time', 'asc')
            ->take(5)
            ->get();

        // Career, and deliberately so: Recent Results is a history, not a
        // season. Everything else on this page is the season in front of you.
        $userResults = PokerTournamentResult::where('user_id', $user->id)
            ->with(['tournament.season'])
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'eyebrow' => trim($user->first_name.' '.$user->last_name),

            'upcoming' => $upcomingTournaments->map(function (PokerTournament $tournament) use ($user) {
                $isRegistered = $tournament->registrants->contains('user_id', $user->id);

                return [
                    'id' => $tournament->id,
                    'name' => $tournament->name,

                    // The date you PLAY. This chip once read the registration
                    // deadline, an hour earlier, so it could show the wrong
                    // day either side of midnight.
                    'month_label' => $tournament->start_time->format('M'),
                    'day_label' => $tournament->start_time->format('d'),
                    'time_label' => $tournament->start_time->format('g:i A'),
                    'venue_label' => $tournament->venue->name ?? __('TBD'),
                    'href' => route('tournaments.show', $tournament),
                    'is_registered' => $isRegistered,

                    // The controller refuses a withdrawal once a finish is
                    // recorded -- a place is a position in a field -- so past
                    // that point the badge stands alone, which is the truth of
                    // it.
                    'can_unregister' => $isRegistered && ! $tournament->hasRecordedResults(),
                    'unregister_confirm' => __('Unregister from :tournament? You can enter again any time before results are recorded.', [
                        'tournament' => emph($tournament->name),
                    ]),

                    // Approval is the controller's gate too, and it refuses an
                    // unapproved account. Offering the button anyway is
                    // offering a click that cannot work; saying why is what
                    // the event card does in the same situation.
                    'can_register' => ! $isRegistered && $user->isApproved(),
                ];
            })->values()->all(),

            // Only when the cap actually hides something. "Showing the next 5
            // of 3" is worse than saying nothing, and a standing note on a
            // list that is complete is noise.
            'upcoming_note' => $upcomingCount > $upcomingTournaments->count()
                ? trans_choice(
                    '{1}The next one of :count scheduled.|[2,*]The next :shown of :count scheduled.',
                    $upcomingTournaments->count(),
                    ['shown' => $upcomingTournaments->count(), 'count' => $upcomingCount]
                )
                : null,

            'results' => $userResults->take(5)->map(fn (PokerTournamentResult $result) => [
                'id' => $result->id,
                'tournament_name' => $result->tournament->name,
                'tournament_href' => route('tournaments.show', $result->tournament),
                'date_label' => $result->tournament->start_time->format('M d, Y'),
                'place' => $result->place,
                'points_label' => number_format($result->points),
            ])->values()->all(),
            'has_more_results' => $userResults->count() > 5,

            // The same builder a player's profile uses, so the two pages
            // cannot disagree about a player.
            ...SeasonFigures::props(PokerSeason::current(), $user->id, $user, self: true),
        ]);
    }
}
