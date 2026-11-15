<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finding yourself in the season standings.
 *
 * Eighty players in one table, and the row the reader came for is one of them.
 * It is tinted, which is a thing only a sighted reader gets, so the same row
 * also names the viewer in text that is clipped rather than removed -- the
 * information is never carried by colour alone.
 */
class SeasonStandingsSelfRowTest extends TestCase
{
    use RefreshDatabase;

    private const SELF = 'season-show__self-row';

    protected function setUp(): void
    {
        parent::setUp();

        // finish() enters and scores one player at a time, and entering a
        // night that already has results reprices them from the points
        // structure. With no structure that is nil -- and since 2026-11-14 a
        // player on nil is not listed, so the viewer's own row vanished from
        // the table these tests look for it in. A structure keeps every
        // finish worth something, as it is in production.
        foreach ([1 => 100, 2 => 85, 3 => 70] as $place => $points) {
            \App\Models\PointsStructure::create(['place' => $place, 'points' => $points]);
        }
    }

    private function season(): PokerSeason
    {
        return PokerSeason::create([
            'name' => 'Season 9',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);
    }

    private function night(PokerSeason $season): PokerTournament
    {
        return PokerTournament::create([
            'name' => 'Night',
            'start_time' => now()->subDays(3),
            'venue_id' => Venue::firstOrCreate(['name' => 'Hall'], ['address' => '1 St'])->id,
            'season_id' => $season->id,
        ]);
    }

    private function player(string $first, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'first_name' => $first, 'last_name' => 'Player', 'approval_status' => 'approved',
        ], $extra));
    }

    /** Entry first, then the finish: the standings only carry players who entered. */
    private function finish(PokerTournament $t, ?User $u, int $place, int $points, string $name = 'Ghost Player'): void
    {
        if ($u !== null) {
            PokerTournamentRegistrant::create([
                'tournament_id' => $t->id, 'user_id' => $u->id,
                'player_name' => $u->first_name.' '.$u->last_name, 'registered_at' => now(),
            ]);
        }

        PokerTournamentResult::create([
            'tournament_id' => $t->id,
            'user_id' => $u?->id,
            'player_name' => $u ? $u->first_name.' '.$u->last_name : $name,
            'place' => $place, 'points' => $points,
        ]);
    }

    /**
     * The standings rows the page is sent.
     *
     * Which row is the viewer's is a per-row flag now rather than a class on a
     * <tr>. The old version had to find the standings rows among every <tr> on
     * the page by looking for the rank cell; there is nothing to disentangle
     * in a list of props.
     *
     * @return array<int, array<string, mixed>>
     */
    private function standingsFor(User $viewer, PokerSeason $season): array
    {
        $rows = $this->actingAs($viewer)
            ->get(route('seasons.show', $season))->assertOk()
            ->viewData('page')['props']['standings'];

        $this->assertNotEmpty($rows, 'The fixture should have produced standings rows.');

        return $rows;
    }

    /**
     * The rows marked as the viewer's own.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function marked(array $rows): array
    {
        return array_values(array_filter($rows, fn (array $r) => $r['is_self'] === true));
    }

    /** The page's own markup, for the questions that are about the template. */
    private function page(): string
    {
        return (string) file_get_contents(resource_path('js/Pages/Poker/Seasons/Show.vue'));
    }

    public function test_the_viewers_own_row_is_marked(): void
    {
        $season = $this->season();
        $night = $this->night($season);

        $me = $this->player('Mine');
        $other = $this->player('Theirs');

        $this->finish($night, $me, 2, 85);
        $this->finish($night, $other, 1, 100);

        $marked = $this->marked($this->standingsFor($me, $season));

        $this->assertCount(1, $marked, 'Exactly one row is the viewer.');
        $this->assertSame('Mine Player', $marked[0]['shown_name']);

        // And the tint is applied from that flag.
        $this->assertStringContainsString(
            "'season-show__self-row': row.is_self", $this->page()
        );
    }

    public function test_nobody_elses_row_is_marked(): void
    {
        // The same page, read by the other player: the mark follows the
        // viewer, not a fixed row. A rule keyed to anything but the session --
        // the leader, the admin, the first row -- passes the test above and
        // fails this one.
        $season = $this->season();
        $night = $this->night($season);

        $me = $this->player('Mine');
        $other = $this->player('Theirs');

        $this->finish($night, $me, 2, 85);
        $this->finish($night, $other, 1, 100);

        $marked = $this->marked($this->standingsFor($other, $season));

        $this->assertCount(1, $marked);
        $this->assertSame('Theirs Player', $marked[0]['shown_name']);
    }

    public function test_a_viewer_who_is_not_in_the_standings_marks_no_row(): void
    {
        $season = $this->season();
        $night = $this->night($season);

        $this->finish($night, $this->player('Played'), 1, 100);

        $bystander = $this->player('Bystander', ['is_admin' => true]);

        $this->assertSame([], $this->marked($this->standingsFor($bystander, $season)));
    }

    public function test_a_result_with_no_account_is_not_read_as_the_viewer(): void
    {
        // A result with no account never reaches these standings at all: it
        // has no registration behind it, and the table is scoped to players
        // who entered -- SeasonStandingsScopeTest holds that rule.
        //
        // This used to loop over the rows looking for one named "Nobody At
        // All" and assert it was unmarked. That loop had stopped matching
        // anything when the scope rule landed, so the only assertion doing
        // work was the count below; porting it to the props is what showed
        // that, because an absent row is null rather than a silent no-op.
        //
        // The null guard in the page is still NOT what this catches -- removing
        // it leaves every test here green, because auth()->id() cannot be null
        // on a route inside the auth group. That guard is for the day the route
        // leaves it, and it is untestable until then.
        $season = $this->season();
        $night = $this->night($season);

        $me = $this->player('Mine');
        $this->finish($night, $me, 2, 85);
        $this->finish($night, null, 1, 100, name: 'Nobody At All');

        $rows = $this->standingsFor($me, $season);

        $this->assertNull(
            collect($rows)->firstWhere('shown_name', 'Nobody At All'),
            'A finish with no registration behind it is not in the standings.'
        );

        $this->assertCount(1, $this->marked($rows));
    }

    public function test_the_marked_row_names_the_viewer_without_colour(): void
    {
        // Clipped, not display:none -- the latter would take the text out of
        // the accessibility tree and leave the tint carrying the meaning alone.
        $season = $this->season();
        $night = $this->night($season);

        $me = $this->player('Mine');
        $this->finish($night, $me, 2, 85);
        $this->finish($night, $this->player('Theirs'), 1, 100);

        $marked = $this->marked($this->standingsFor($me, $season));

        $this->assertCount(1, $marked);

        // The words are drawn from the same flag as the tint, and clipped
        // rather than display:none -- the latter would take the text out of
        // the accessibility tree and leave the tint carrying the meaning
        // alone.
        $this->assertMatchesRegularExpression(
            '/<span v-if="row\.is_self" class="u-visually-hidden">\(you\)<\/span>/',
            $this->page()
        );
    }

    public function test_the_tint_is_scoped_hard_enough_to_beat_the_hover_rule(): void
    {
        // `.table tbody tr:hover` in _table.css is (0,2,2). A bare
        // `.season-show__self-row` is (0,1,0) and loses, so the row would drop
        // its tint under the pointer -- on the one row a reader is most likely
        // to point at. Nothing in a PHP suite can measure a computed style, so
        // the selector itself is what is checked.
        $css = file_get_contents(resource_path('css/4-pages/_season-show.css'));

        $this->assertStringContainsString(
            '.season-show__standings .table tbody .season-show__self-row {',
            $css,
            'The resting tint must stay scoped under the wrapper and the table.'
        );

        $this->assertStringContainsString(
            '.season-show__standings .table tbody .season-show__self-row:hover {',
            $css,
            'The row must still answer the pointer, in its own wash.'
        );
    }

    /**
     * Relative luminance, WCAG 2.x. Kept here rather than in the app: nothing
     * in production needs it, and a helper in app/ would be dead code.
     */
    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $c = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function contrast(string $a, string $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function token(string $name): string
    {
        $tokens = file_get_contents(resource_path('css/1-base/_tokens.css'));

        $this->assertSame(1, preg_match(
            '/^\s*'.preg_quote($name, '/').':\s*(#[0-9A-Fa-f]{6});/m', $tokens, $m
        ), $name.' should be defined exactly once as a literal colour.');

        return $m[1];
    }

    public function test_the_mark_is_never_fainter_than_the_hover_it_replaces(): void
    {
        // The rule this encodes: a mark that PERSISTS cannot be harder to see
        // than one that appears only while the pointer is over the row. The
        // house hover shade is the floor, in both themes.
        //
        // Written because the first draft failed it -- an 8% wash measured
        // 1.13:1 against white where --c-surface-raised measures 1.18:1, and
        // it looked fine in the markup and in every green test. Only a
        // computed-style probe showed it. A hex in a token file is not
        // self-checking; this is what makes it so.
        foreach ([
            ['--c-surface', '--c-surface-raised', '--c-self', '--c-self-hover'],
            ['--dark-surface', '--dark-surface-raised', '--dark-self', '--dark-self-hover'],
        ] as [$surface, $raised, $self, $selfHover]) {
            $floor = $this->contrast($this->token($raised), $this->token($surface));

            $resting = $this->contrast($this->token($self), $this->token($surface));
            $hovered = $this->contrast($this->token($selfHover), $this->token($surface));

            $this->assertGreaterThan($floor, $resting, sprintf(
                '%s is %.3f:1 on %s, fainter than the %.3f:1 hover shade it has to outlast.',
                $self, $resting, $surface, $floor
            ));

            $this->assertGreaterThan($resting, $hovered, sprintf(
                '%s must be a visible step up from %s, or the row goes inert under the pointer.',
                $selfHover, $self
            ));
        }
    }

    public function test_the_text_on_both_tints_stays_legible(): void
    {
        // The tints sit under --c-text-muted, which is the smallest type in
        // the row -- the stat run on a phone. AA body text is the bar.
        foreach ([
            ['--c-text', '--c-text-muted', '--c-self', '--c-self-hover'],
            ['--dark-text', '--dark-text-muted', '--dark-self', '--dark-self-hover'],
        ] as [$text, $muted, $self, $selfHover]) {
            foreach ([$self, $selfHover] as $ground) {
                foreach ([$text, $muted] as $ink) {
                    $ratio = $this->contrast($this->token($ink), $this->token($ground));

                    $this->assertGreaterThanOrEqual(4.5, $ratio, sprintf(
                        '%s on %s is %.2f:1, below AA for body text.', $ink, $ground, $ratio
                    ));
                }
            }
        }
    }

    public function test_both_tints_are_defined_in_both_themes(): void
    {
        // A background-color reading an undefined custom property falls back to
        // transparent: the row simply stops being marked, with no error.
        $tokens = file_get_contents(resource_path('css/1-base/_tokens.css'));

        foreach (['--c-self', '--c-self-hover'] as $token) {
            $this->assertMatchesRegularExpression(
                '/^\s*'.preg_quote($token, '/').':\s*#[0-9A-Fa-f]{6};/m',
                $tokens,
                $token.' needs a light value.'
            );

            $this->assertMatchesRegularExpression(
                '/^\s*'.preg_quote($token, '/').':\s*var\(--dark-self/m',
                $tokens,
                $token.' needs to be remapped in the dark theme.'
            );
        }

        foreach (['--dark-self', '--dark-self-hover'] as $token) {
            $this->assertMatchesRegularExpression(
                '/^\s*'.preg_quote($token, '/').':\s*#[0-9A-Fa-f]{6};/m',
                $tokens,
                $token.' needs a dark value.'
            );
        }
    }
}
