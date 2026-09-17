<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
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
    /**
     * Every view that lists players, and must picture them.
     *
     * @var array<string, string> view => what it lists
     */
    private const LISTS = [
        'poker/seasons/show.blade.php' => 'the season standings',
        'poker/tournaments/show.blade.php' => 'the registered players panel',
        'poker/registrants/index.blade.php' => 'the admin registrants list',
        'poker/venue-points/create.blade.php' => 'the venue points player picker',
        'rules/points-structure.blade.php' => 'Current Season Leaders',
        'home.blade.php' => 'the landing page leader cards',
        'events.blade.php' => 'the archive podium',
        'users/index.blade.php' => 'the players list',
        'users/show.blade.php' => 'a player profile',
    ];

    /**
     * Native <select> forms. An <option> may contain text and nothing else, so
     * a monogram is not merely unimplemented here -- it is not expressible.
     *
     * @var array<int, string>
     */
    private const OUT_OF_REACH = [
        'poker/registrants/create.blade.php',
        'poker/registrants/edit.blade.php',
    ];

    public function test_every_list_of_players_pictures_them(): void
    {
        $missing = [];

        foreach (self::LISTS as $view => $what) {
            $source = file_get_contents(resource_path('views/'.$view));

            // Either the component, or its classes where the rows are built in
            // the browser and the component cannot run.
            if (! str_contains($source, 'x-monogram') && ! str_contains($source, 'class="monogram')) {
                $missing[] = $view.' — '.$what;
            }
        }

        $this->assertSame([], $missing, implode("\n  ", array_merge(
            ['A list names players without picturing them:'], $missing
        )));
    }

    public function test_the_select_forms_are_still_selects(): void
    {
        // If one of these ever becomes a picker, it joins the list above. This
        // is what makes the exemption a recorded decision rather than an
        // oversight nobody revisits.
        foreach (self::OUT_OF_REACH as $view) {
            $this->assertStringContainsString(
                '<select', file_get_contents(resource_path('views/'.$view)),
                $view.' is no longer a native select, so it can picture its players now.'
            );
        }
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
        $root = resource_path('views').DIRECTORY_SEPARATOR;

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            if (preg_match('/mb_substr\s*\(\s*\$\w+(\[|->|\s*,)/', $file->getContents())) {
                $offenders[] = str_replace($root, '', $file->getRealPath());
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['A view cuts letters out of a name itself instead of calling initials():'], $offenders
        )));
    }
}
