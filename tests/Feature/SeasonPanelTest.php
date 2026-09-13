<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The player's standing in the season now being played.
 *
 * The dashboard used to show career figures -- career points, every event ever
 * played, every podium. The league runs in seasons and the standings reset with
 * them, so "how am I doing" means "this season", and a career total answers a
 * question nobody on this page is asking.
 *
 * Season Rank is points per EVENT ENTERED, not points. A player who turns up to
 * half the nights and scores the same as somebody who turned up to all of them
 * is not behind them. That makes the rank a division, and a division has two
 * ways to be wrong: these tests pin both sides of it.
 */
class SeasonPanelTest extends TestCase
{
    use RefreshDatabase;

    private function season(string $name = 'Season 9', bool $current = true): PokerSeason
    {
        return PokerSeason::create([
            'name' => $name,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => $current,
        ]);
    }

    private function tournament(PokerSeason $season, string $name = 'Night'): PokerTournament
    {
        return PokerTournament::create([
            'name' => $name,
            'start_time' => now()->subDays(3),
            'venue_id' => Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St'])->id,
            'season_id' => $season->id,
        ]);
    }

    private function player(string $first = 'Wanda'): User
    {
        return User::factory()->create([
            'first_name' => $first, 'last_name' => 'Reeve', 'approval_status' => 'approved',
        ]);
    }

    private function enter(PokerTournament $t, User $u): void
    {
        PokerTournamentRegistrant::create([
            'tournament_id' => $t->id, 'user_id' => $u->id,
            'player_name' => $u->first_name.' '.$u->last_name, 'registered_at' => now(),
        ]);
    }

    private function score(PokerTournament $t, User $u, int $place, int $points): void
    {
        PokerTournamentResult::create([
            'tournament_id' => $t->id, 'user_id' => $u->id,
            'player_name' => $u->first_name.' '.$u->last_name,
            'place' => $place, 'points' => $points,
        ]);
    }

    /** @return array<string, mixed> */
    private function figures(User $as): array
    {
        return $this->actingAs($as)->get(route('dashboard'))->assertOk()->viewData('season');
    }

    public function test_events_played_counts_entries_not_results(): void
    {
        // Entered three, scored in one. Events Played is three: turning up is
        // what it measures, and it is also the denominator of the rank.
        $season = $this->season();
        $player = $this->player();

        foreach (range(1, 3) as $i) {
            $t = $this->tournament($season, 'Night '.$i);
            $this->enter($t, $player);

            if ($i === 1) {
                $this->score($t, $player, 1, 100);
            }
        }

        $this->assertSame(3, $this->figures($player)['events']);
    }

    public function test_the_figures_count_only_the_current_season(): void
    {
        // The whole point of the rewrite. An old season's results must not
        // appear in any of these.
        $current = $this->season();
        $old = $this->season('Season 8', current: false);
        $player = $this->player();

        $now = $this->tournament($current, 'This Season');
        $this->enter($now, $player);
        $this->score($now, $player, 2, 85);

        $then = $this->tournament($old, 'Last Season');
        $this->enter($then, $player);
        $this->score($then, $player, 1, 100);

        $figures = $this->figures($player);

        $this->assertSame(1, $figures['events']);
        $this->assertSame(85, $figures['points']);
        $this->assertSame(0, $figures['first'], "Last season's win must not count.");
        $this->assertSame(1, $figures['second']);
    }

    public function test_the_podium_counts_are_per_place(): void
    {
        $season = $this->season();
        $player = $this->player();

        foreach ([1, 1, 2, 3, 3, 3, 7] as $i => $place) {
            $t = $this->tournament($season, 'Night '.$i);
            $this->enter($t, $player);
            $this->score($t, $player, $place, 10);
        }

        $figures = $this->figures($player);

        $this->assertSame(2, $figures['first']);
        $this->assertSame(1, $figures['second']);
        $this->assertSame(3, $figures['third']);
    }

    public function test_venue_points_come_from_the_stored_season(): void
    {
        // season_id, not the season's date range. Venue points carry the season
        // they were earned in so that editing a season's dates cannot move them
        // between seasons -- inferring it from event_date was the bug that put
        // the column there.
        $current = $this->season();
        $old = $this->season('Season 8', current: false);
        $player = $this->player();
        $venue = Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St']);

        VenuePoints::create([
            'venue_id' => $venue->id, 'user_id' => $player->id, 'user_name' => 'Wanda Reeve',
            'event_date' => now()->subWeek(), 'amount' => 40, 'season_id' => $current->id,
        ]);

        // Dated inside the current season, but earned in the last one. The date
        // range would count this; the stored season does not.
        VenuePoints::create([
            'venue_id' => $venue->id, 'user_id' => $player->id, 'user_name' => 'Wanda Reeve',
            'event_date' => now()->subWeek(), 'amount' => 500, 'season_id' => $old->id,
        ]);

        $this->assertSame(40, $this->figures($player)['venuePoints']);
    }

    public function test_rank_is_points_per_event_not_points(): void
    {
        // The reason the rank is a ratio. The grinder scores more in total; the
        // sharp scores more per night and ranks above them.
        $season = $this->season();
        $grinder = $this->player('Grinder');
        $sharp = $this->player('Sharp');

        // Everyone enters BEFORE anyone is scored. Registering into a
        // tournament that already has finishes is a late entry: the field grows
        // and the shift hook reprices every recorded result to match, which
        // silently rewrote this fixture's points when it was the other way
        // round.
        $nights = [];

        foreach (range(1, 4) as $i) {
            $nights[$i] = $this->tournament($season, 'Night '.$i);
            $this->enter($nights[$i], $grinder);
        }

        $this->enter($nights[1], $sharp);

        foreach (range(1, 4) as $i) {
            $this->score($nights[$i], $grinder, 5, 50);  // 200 over 4 = 50.0
        }

        $this->score($nights[1], $sharp, 1, 100);        // 100 over 1 = 100.0

        $this->assertSame(200, $this->figures($grinder)['points']);
        $this->assertSame(100, $this->figures($sharp)['points']);

        $this->assertSame(1, $this->figures($sharp)['rank'], 'The higher average ranks first.');
        $this->assertSame(2, $this->figures($grinder)['rank']);
    }

    public function test_a_player_who_has_not_entered_is_not_ranked(): void
    {
        // No denominator, so no ratio. Zero would put them level with somebody
        // who entered and scored nothing, which is a different thing.
        $this->season();
        $player = $this->player();

        $figures = $this->figures($player);

        $this->assertNull($figures['rank']);
        $this->assertNull($figures['perEvent']);
        $this->assertSame(0, $figures['events']);
    }

    public function test_entering_without_scoring_is_ranked_last_rather_than_unranked(): void
    {
        $season = $this->season();
        $scorer = $this->player('Scorer');
        $blank = $this->player('Blank');

        $t = $this->tournament($season);

        foreach ([$scorer, $blank] as $p) {
            $this->enter($t, $p);
        }

        $this->score($t, $scorer, 1, 100);

        $this->assertSame(2, $this->figures($blank)['rank']);
        $this->assertSame(0.0, $this->figures($blank)['perEvent']);
    }

    public function test_a_tie_on_the_average_is_broken_by_total_points(): void
    {
        // Same ratio, different volume. Without a tie-break the order is
        // whatever the database returned, which changes between drivers.
        $season = $this->season();
        $more = $this->player('More');
        $less = $this->player('Less');

        foreach (range(1, 2) as $i) {
            $t = $this->tournament($season, 'Night '.$i);
            $this->enter($t, $more);
            $this->score($t, $more, 1, 100);            // 200 over 2 = 100.0
        }

        $single = $this->tournament($season, 'Single');
        $this->enter($single, $less);
        $this->score($single, $less, 1, 100);            // 100 over 1 = 100.0

        $this->assertSame(1, $this->figures($more)['rank']);
        $this->assertSame(2, $this->figures($less)['rank']);
    }

    public function test_the_heading_is_the_season_name(): void
    {
        $this->season('Season 12');

        $this->actingAs($this->player())->get(route('dashboard'))->assertOk()
            ->assertSee('Season 12')
            ->assertDontSee('Current Season');
    }

    public function test_the_panel_says_so_when_no_season_is_running(): void
    {
        // Every figure is scoped to a season, so without one there is nothing
        // to show and a row of zeroes would be a lie.
        $this->actingAs($this->player())->get(route('dashboard'))->assertOk()
            ->assertSee('No season is running.')
            ->assertDontSee('pts per event');
    }

    public function test_a_player_yet_to_enter_is_told_how_to_be_ranked(): void
    {
        $this->season();

        $this->actingAs($this->player())->get(route('dashboard'))->assertOk()
            ->assertSee('Not entered yet')
            ->assertSee('Enter a tournament to be ranked.');
    }

    public function test_the_career_figures_are_gone_from_the_page(): void
    {
        // What this replaced. A career total on a page about the season now
        // being played is the wrong question answered precisely.
        $season = $this->season();
        $player = $this->player();
        $t = $this->tournament($season);
        $this->enter($t, $player);
        $this->score($t, $player, 1, 100);

        $this->actingAs($player)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Career Points')
            ->assertDontSee('Podium Finishes');
    }

    /** The season panel's markup, and nothing else on the page. */
    private function panel(User $as): string
    {
        $html = $this->actingAs($as)->get(route('dashboard'))->assertOk()->getContent();

        $at = strpos($html, 'season__grid');
        $this->assertNotFalse($at, 'The season panel is not on the dashboard.');

        return substr($html, $at, (int) (strpos($html, 'Upcoming Tournaments', $at) ?: strlen($html)) - $at);
    }

    public function test_the_finishes_are_labelled_and_counted_like_the_totals(): void
    {
        // Three labelled counts beside three other labelled counts. They were a
        // row of medal-and-count pairs, which made the same kind of figure look
        // like a different kind of thing.
        //
        // The labels are user-facing copy. If a later change renames one, this
        // fails -- which is intended: that should be a decision, not a side
        // effect of restyling.
        $season = $this->season();
        $player = $this->player();

        foreach ([1, 1, 2, 3, 3, 3] as $i => $place) {
            $t = $this->tournament($season, 'Night '.$i);
            $this->enter($t, $player);
            $this->score($t, $player, $place, 10);
        }

        $text = preg_replace('/\s+/', ' ', strip_tags($this->panel($player)));

        $this->assertStringContainsString('Tournament Wins 2', $text);
        $this->assertStringContainsString('2nd Place Finishes 1', $text);
        $this->assertStringContainsString('3rd Place Finishes 3', $text);

        // Read the same way as the figures beside them.
        // Order matters as well as wording: events first, because it is the
        // denominator of the rank above it, then the two point totals.
        $this->assertStringContainsString('Events Played 6', $text);
        $this->assertStringContainsString('Season Points 60', $text);
        $this->assertStringContainsString('Venue Points 0', $text);

        $this->assertMatchesRegularExpression(
            '/Events Played 6 Season Points 60 Venue Points 0/',
            $text,
            'The totals are out of order.'
        );
    }

    public function test_the_medal_badges_carry_no_numeral(): void
    {
        // The badge used to hold the place number, and the label beside it said
        // the same thing again -- a gold 1 next to the words "Tournament Wins".
        // What the badge is for is the colour.
        $this->season();
        $panel = $this->panel($this->player());

        foreach ([1, 2, 3] as $place) {
            $this->assertMatchesRegularExpression(
                '/<span class="rank rank--'.$place.'" aria-hidden="true">\s*<\/span>/',
                $panel,
                "The {$place} badge must be empty."
            );
        }

        // And the medal colours are the standings', not a second set: rank--1
        // is where gold is defined for the whole app.
        $this->assertStringContainsString('rank--1', $panel);
    }

    public function test_the_rank_label_sits_above_the_figure_and_its_reasoning_to_the_right(): void
    {
        // Label, then the figure with the reasoning across from it -- the shape
        // every other row here has, with the two ends swapped because the big
        // figure is the subject and the words are the gloss.
        $season = $this->season();
        $player = $this->player();
        $t = $this->tournament($season);
        $this->enter($t, $player);
        $this->score($t, $player, 1, 100);

        $panel = $this->panel($player);

        $label = strpos($panel, 'season__rank-label');
        $value = strpos($panel, 'season__rank-value');
        $ratio = strpos($panel, 'season__ratio');

        $this->assertNotFalse($label);
        $this->assertNotFalse($value);
        $this->assertNotFalse($ratio);

        $this->assertLessThan($value, $label, 'The label must come before the figure.');
        $this->assertLessThan($ratio, $value, 'The reasoning must come after the figure.');

        // Both inside the row that puts them at opposite ends.
        $this->assertStringContainsString('season__standing-row', $panel);
    }
}
