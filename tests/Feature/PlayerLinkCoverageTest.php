<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * A player's name is a way to their figures.
 *
 * The profile page is only worth having if it can be reached from the places a
 * reader meets a name, so the lists that name players link them. Named rather
 * than scanned, because no regex recognises "a list of players" -- what this
 * guards is that the set does not quietly shrink.
 *
 * <x-player-link> is the one definition of WHEN a name becomes a link: only
 * where there is an account behind it. user_id is nullable with nullOnDelete,
 * so a departed player leaves a name and nothing to point at, and a link to
 * players.show for a deleted user is a 404 with somebody's name on it.
 */
class PlayerLinkCoverageTest extends TestCase
{
    /** @var array<string, string> view => the list it draws */
    private const LINKED = [
        'poker/seasons/show.blade.php' => 'the season standings',
        'poker/tournaments/show.blade.php' => 'the registered players panel',
        'poker/registrants/index.blade.php' => 'the admin registrants list',
        'poker/venues/show.blade.php' => 'the venue leaderboard',
        'rules/points-structure.blade.php' => 'Current Season Leaders',
        'home.blade.php' => 'the landing page leader cards',
        'events.blade.php' => 'the archive podium',
        'users/index.blade.php' => 'the admin players list, both tables',
    ];

    public function test_every_list_of_players_links_them(): void
    {
        $missing = [];

        foreach (self::LINKED as $view => $what) {
            if (! str_contains(file_get_contents(resource_path('views/'.$view)), 'x-player-link')) {
                $missing[] = $view.' — '.$what;
            }
        }

        $this->assertSame([], $missing, implode("\n  ", array_merge(
            ['A list names players without linking them to their figures:'], $missing
        )));
    }

    public function test_no_view_builds_the_link_itself(): void
    {
        // The failure this prevents is a 404 with somebody's name on it: a
        // hand-rolled route('players.show', ...) has no reason to check that
        // the account still exists, and the one place that does check is the
        // component.
        $offenders = [];
        $root = resource_path('views').DIRECTORY_SEPARATOR;

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            if ($file->getFilename() === 'player-link.blade.php') {
                continue;
            }

            if (str_contains($file->getContents(), "route('players.show'")) {
                $offenders[] = str_replace($root, '', $file->getRealPath());
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['A view builds a player link itself instead of using <x-player-link>:'], $offenders
        )));
    }

    public function test_the_component_refuses_to_link_a_name_with_no_account(): void
    {
        $component = file_get_contents(resource_path('views/components/player-link.blade.php'));

        $this->assertStringContainsString('@if ($user)', $component);
        $this->assertStringContainsString('@else', $component);
        $this->assertStringContainsString('<span', $component,
            'A name with no account behind it must still render, as plain text.');
    }
}
