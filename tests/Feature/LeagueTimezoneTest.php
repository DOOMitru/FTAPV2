<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Every date in this app is a Regina wall clock.
 *
 * A datetime-local input posts naive text with no zone -- "2026-09-09T19:00" --
 * so whatever the application timezone is, that is the zone the value lands in.
 * Under UTC it landed as 7pm UTC, which reads back as 7pm and therefore looks
 * right on every page, while being a real instant six hours before the one the
 * administrator meant. now() is a genuine moment, so every comparison between
 * the two was six hours out.
 *
 * That is the shape of fault this file exists for: nothing on screen was wrong,
 * and the whole suite passed. The config assertion at the bottom is the weakest
 * test here -- it would pass against an app that read the setting and then
 * ignored it -- so the ones above it work in wall-clock terms and would fail
 * under UTC for the right reason.
 */
class LeagueTimezoneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The evening of a league night, as a clock on a wall in Regina reads it --
     * handed back as a bare instant rather than a Regina-zoned Carbon.
     *
     * That distinction is the whole reason this helper exists.
     * Carbon::setTestNow() given a zoned instance makes every subsequent
     * Carbon::parse() adopt that instance's timezone, and Eloquent's datetime
     * cast goes through Carbon::parse(). So freezing the clock with a
     * Regina-zoned value made the model read its dates back in Regina no matter
     * what config/app.php said -- the test harness quietly repairing the exact
     * fault these tests exist to catch. Reverting the config and watching which
     * of them still passed is what turned it up.
     */
    private function reginaTime(string $time): Carbon
    {
        return Carbon::createFromTimestamp(
            Carbon::parse("2026-09-09 {$time}", 'America/Regina')->timestamp,
            // Carbon 3's createFromTimestamp() defaults to UTC, so leaving this
            // off swapped one imposed timezone for another and broke these
            // tests under the correct config. The frozen clock takes whatever
            // zone the application is running in, so these tests measure the
            // app's behaviour rather than dictating an answer to it.
            date_default_timezone_get()
        );
    }

    /** A tournament starting at 7pm that evening, entered the way the form enters it. */
    private function tournament(): PokerTournament
    {
        $season = PokerSeason::create(['name' => 'Season 40', 'start_date' => '2026-08-01',
            'end_date' => '2026-10-31', 'is_current' => true]);

        return PokerTournament::create([
            'name' => 'Wednesday Night Poker',
            // Exactly what a datetime-local field posts: no zone, no offset.
            'start_time' => '2026-09-09T19:00',
            'venue_id' => Venue::create(['name' => 'The Copper Kettle', 'address' => '1 Card Street'])->id,
            'season_id' => $season->id,
        ]);
    }

    public function test_a_tournament_is_still_upcoming_in_the_afternoon(): void
    {
        // 1pm on the day. Under UTC this was the exact minute the tournament
        // dropped off every upcoming list in the app.
        Carbon::setTestNow($this->reginaTime('13:00'));

        $tournament = $this->tournament();
        $player = User::factory()->create(['approval_status' => 'approved']);

        $this->actingAs($player)->get(route('dashboard'))->assertOk()
            ->assertViewHas('upcomingTournaments',
                fn ($t) => $t->contains('id', $tournament->id));

        $this->actingAs($player)->get(route('events'))->assertOk()
            ->assertViewHas('upcomingTournaments',
                fn ($t) => $t->contains('id', $tournament->id));
    }

    public function test_a_tournament_is_still_upcoming_one_minute_before_it_starts(): void
    {
        // The boundary itself, from the near side.
        Carbon::setTestNow($this->reginaTime('18:59'));

        $tournament = $this->tournament();

        $this->actingAs(User::factory()->create(['approval_status' => 'approved']))
            ->get(route('dashboard'))->assertOk()
            ->assertViewHas('upcomingTournaments', fn ($t) => $t->contains('id', $tournament->id));
    }

    public function test_a_tournament_has_started_once_the_clock_reaches_it(): void
    {
        // And from the far side. Without this the two above would pass against
        // an app that thought nothing had ever started.
        Carbon::setTestNow($this->reginaTime('19:01'));

        $tournament = $this->tournament();

        $this->actingAs(User::factory()->create(['approval_status' => 'approved']))
            ->get(route('dashboard'))->assertOk()
            ->assertViewHas('upcomingTournaments', fn ($t) => ! $t->contains('id', $tournament->id));
    }

    public function test_the_details_page_does_not_call_a_tournament_past_before_it_starts(): void
    {
        // $isPast drives the Final Standings section, which under UTC appeared
        // six hours before the first hand was dealt.
        //
        // 3pm, not 1pm. 1pm in Regina is 19:00 UTC, exactly the stored value,
        // and isPast() is a strict comparison -- so written at 1pm this test
        // sat on the boundary by a single minute and passed under UTC as well,
        // which is no test at all. Found by reverting the config and watching
        // which of these still passed.
        Carbon::setTestNow($this->reginaTime('15:00'));

        $tournament = $this->tournament();

        $this->actingAs(User::factory()->create(['approval_status' => 'approved']))
            ->get(route('tournaments.show', $tournament))->assertOk()
            ->assertViewHas('isPast', false);
    }

    public function test_a_time_survives_the_round_trip_as_the_same_real_moment(): void
    {
        // Typed 7pm, stored 7pm, and 7pm Regina is the instant meant -- not an
        // instant six hours earlier that happens to print as 7pm.
        $tournament = $this->tournament();

        $this->assertSame('7:00 PM', $tournament->fresh()->start_time->format('g:i A'));
        $this->assertSame(
            '2026-09-10 01:00:00',
            $tournament->fresh()->start_time->copy()->utc()->toDateTimeString(),
            '7pm in Regina is 01:00 UTC the next day.'
        );
    }

    public function test_today_does_not_roll_over_during_a_league_night(): void
    {
        // Under UTC, today() became tomorrow at 6pm Regina -- for the whole of
        // every poker evening. Nothing defaults a date from now() yet, which is
        // the only reason this was latent rather than live.
        Carbon::setTestNow($this->reginaTime('22:30'));

        $this->assertSame('2026-09-09', now()->toDateString());
    }

    public function test_the_league_timezone_is_configured(): void
    {
        $this->assertSame('America/Regina', config('app.timezone'));
        $this->assertSame('America/Regina', date_default_timezone_get());

        // Saskatchewan does not observe daylight saving, which is why storing a
        // local wall clock is safe here: there is no hour that happens twice
        // and none that never happens. Checked in both halves of the year,
        // because that is the assumption the whole approach rests on.
        foreach (['2026-01-15 19:00', '2026-07-15 19:00'] as $moment) {
            $this->assertSame('-06:00', Carbon::parse($moment, 'America/Regina')->format('P'));
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
