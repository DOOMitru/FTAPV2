<?php

namespace Tests\Feature;

use Tests\Concerns\ScansTemplates;
use Tests\TestCase;

/**
 * Where a player is named, a player is pictured.
 *
 * A survey found ten lists that named players without initials beside them.
 * They were left because "several need their controllers reshaped to carry a
 * model instead of a name string" -- which turned out to be weaker than it
 * read: <x-monogram> takes a name STRING and derives the letters from it, and
 * a decorative monogram needs no accessible label because the name it stands
 * for is right beside it. Only the four native <select> forms are genuinely
 * out of reach, because an <option> cannot hold markup.
 *
 * This names the views rather than scanning for "a list of players", which no
 * regex can recognise. What it guards is that the ten do not quietly become
 * nine again.
 */
class MonogramCoverageTest extends TestCase
{
    use ScansTemplates;

    /**
     * Every screen that lists players, and must picture them.
     *
     * Paths are relative to `resources/`. An entry moves from `views/` to
     * `js/Pages/` the day its page converts -- the rule is about screens, not
     * about which half of the app they are written in.
     *
     * @var array<string, string> template => what it lists
     */
    private const LISTS = [
        'views/poker/seasons/show.blade.php' => 'the season standings',
        'views/poker/tournaments/show.blade.php' => 'the registered players panel',
        'js/Pages/Poker/VenuePoints/Create.vue' => 'the venue points player picker',
        'views/rules/points-structure.blade.php' => 'Current Season Leaders',
        'views/home.blade.php' => 'the landing page leader cards',
        'views/events.blade.php' => 'the archive podium',
        'js/Pages/Users/Index.vue' => 'the players list',
        'js/Pages/Users/Show.vue' => 'a player profile',
    ];

    public function test_every_list_of_players_pictures_them(): void
    {
        $missing = [];

        foreach (self::LISTS as $view => $what) {
            $source = (string) file_get_contents(resource_path($view));

            // Either spelling of the component, or its classes where the rows
            // are built in the browser and no component can run.
            if (! str_contains($source, 'x-monogram')
                && ! str_contains($source, '<Monogram')
                && ! str_contains($source, 'class="monogram')) {
                $missing[] = $view.' — '.$what;
            }
        }

        $this->assertSame([], $missing, implode("\n  ", array_merge(
            ['A list names players without picturing them:'], $missing
        )));
    }

    public function test_the_letters_have_one_definition(): void
    {
        // The register dialog builds its rows in the browser from a payload
        // PHP assembles, so the letters cannot come from the component there.
        // Both callers go through initials() instead, because two derivations
        // would be free to disagree.
        $component = file_get_contents(resource_path('views/components/monogram.blade.php'));

        // The CALL, not the word: an earlier version of this looked for the
        // substring "initials(", which the comment above the call also
        // contains -- so replacing the call with an inline derivation left the
        // test green and the duplication in place.
        $this->assertMatchesRegularExpression(
            '/=\s*initials\(/', $component,
            'The component should derive its letters from the shared helper.'
        );

        $this->assertStringNotContainsString(
            'mb_substr', $component,
            'The component cuts letters out of the name itself instead of calling initials().'
        );

        $this->assertStringContainsString(
            "'initials' => initials(",
            file_get_contents(app_path('Http/Controllers/Poker/PokerTournamentController.php')),
            'The register dialog payload should carry letters from the same helper.'
        );
    }

    public function test_no_view_derives_initials_for_itself(): void
    {
        // The failure this prevents is silent: a second derivation renders
        // something plausible and drifts the first time either is corrected.
        $offenders = [];

        foreach ($this->templates() as $relative => $source) {
            // mb_substr in Blade; charAt, slice or substring in Vue, which is
            // the same second derivation written in the other language.
            if (preg_match('/mb_substr\s*\(\s*\$\w+(\[|->|\s*,)/', $source)
                || preg_match('/\.(?:charAt|slice|substring)\s*\(\s*0\s*,?\s*1?\s*\)/', $source)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['A template cuts letters out of a name itself instead of calling initials():'], $offenders
        )));
    }
}
