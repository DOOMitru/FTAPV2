<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * One definition of "the current season".
 *
 * There were two. The home page asked which season's date range contained
 * today, falling back to the most recent; the dashboard, the tournament forms
 * and the points structure read the is_current flag. They agree right up until
 * a season ends without anyone moving the flag, or a flag is set on a season
 * that has not started -- and then which season a player is told about depends
 * on which page they opened.
 *
 * The flag wins: it is a decision somebody makes and can see, where a date
 * range quietly re-answers itself as time passes.
 */
class CurrentSeasonTest extends TestCase
{
    use RefreshDatabase;

    private function season(string $name, string $start, string $end, bool $current = false): PokerSeason
    {
        return PokerSeason::create([
            'name' => $name,
            'start_date' => $start,
            'end_date' => $end,
            'is_current' => $current,
        ]);
    }

    public function test_the_flag_decides_even_when_the_dates_disagree(): void
    {
        // The case that separates the two rules: a season flagged current whose
        // dates ended last month. By the dates, the other one is running. The
        // flag is the answer, and every page must give the same one.
        $this->season('Season 39', now()->subMonths(6)->toDateString(), now()->subMonth()->toDateString());

        $flagged = PokerSeason::orderBy('start_date')->first();
        PokerSeason::query()->update(['is_current' => false]);
        $flagged->update(['is_current' => true]);

        $this->season('Season 40', now()->subDays(3)->toDateString(), now()->addMonths(3)->toDateString());
        // Creating that one claimed the flag, so take it back for the test.
        PokerSeason::query()->update(['is_current' => false]);
        $flagged->refresh()->update(['is_current' => true]);

        $this->assertSame($flagged->id, PokerSeason::current()->id);

        $this->get('/')->assertOk()->assertViewHas(
            'currentSeason',
            fn ($season) => $season !== null && $season->id === $flagged->id
        );
    }

    public function test_a_season_running_today_is_not_current_unless_it_is_flagged(): void
    {
        $running = $this->season('Season 40', now()->subDay()->toDateString(), now()->addMonth()->toDateString());

        PokerSeason::query()->update(['is_current' => false]);

        $this->assertNull(PokerSeason::current());

        $this->get('/')->assertOk()->assertViewHas('currentSeason', null);
        $this->assertNotNull($running->id);
    }

    public function test_a_league_between_seasons_is_told_so_rather_than_shown_the_last_one(): void
    {
        // The home page used to fall back to the most recent season, which is
        // the same guess the flag exists to remove.
        $this->season('Season 39', now()->subYear()->toDateString(), now()->subMonths(6)->toDateString());
        PokerSeason::query()->update(['is_current' => false]);

        $this->get('/')->assertOk()
            ->assertSee('Season Launching Soon')
            ->assertDontSee('Season 39 is Here');
    }

    public function test_every_page_names_the_same_season(): void
    {
        $old = $this->season('Season 39', now()->subYear()->toDateString(), now()->subMonths(6)->toDateString());
        // Flagged explicitly: the helper passes is_current through, so the
        // model's "new seasons are current" default does not apply here.
        $current = $this->season('Season 40', now()->subMonth()->toDateString(), now()->addMonths(2)->toDateString(), current: true);

        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        foreach (['/' => 'currentSeason', route('rules.points-structure') => 'currentSeason'] as $url => $key) {
            $this->actingAs($admin)->get($url)->assertOk()->assertViewHas(
                $key,
                fn ($season) => $season !== null && $season->id === $current->id
            );
        }

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertViewHas(
            'currentSeason',
            fn ($season) => $season !== null && $season->id === $current->id
        );

        $this->assertNotSame($current->id, $old->id);
    }

    public function test_nothing_asks_the_question_a_second_way(): void
    {
        // The rule lives in one method. A second lookup somewhere else is how
        // the two definitions got here in the first place.
        $offenders = [];

        foreach (Finder::create()->files()->in([app_path(), base_path('routes')])->name('*.php') as $file) {
            $path = str_replace(base_path().'/', '', $file->getRealPath());

            // The model owns the flag: current() reads it, and saving a season
            // clears it from the others.
            if ($path === 'app/Models/PokerSeason.php') {
                continue;
            }

            $content = file_get_contents($file->getRealPath());

            if (str_contains($content, "is_current', true")) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['These ask for the current season directly instead of through PokerSeason::current():'],
            $offenders,
        )));
    }
}
