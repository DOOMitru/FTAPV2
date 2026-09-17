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
        'poker/venues/show.blade.php' => 'the venue leaderboard',
        'rules/points-structure.blade.php' => 'Current Season Leaders',
        'home.blade.php' => 'the landing page leader cards',
        'users/index.blade.php' => 'the admin players list, both tables',
    ];

    /**
     * Lists that name players and deliberately do NOT link them.
     *
     * One entry, one reason, and the reason has to be a rule the app cannot
     * bend rather than a preference. This is the record that the set above
     * shrank on purpose.
     */
    private const NOT_LINKED = [
        'events.blade.php' => 'the archive podium — the card is itself one big <a> to the tournament, '
            .'and an <a> may not contain an <a>. A link here is not a worse link, it is a card the '
            .'browser takes apart: it closes the outer anchor and spills the podium and the foot into '
            .'the grid as separate cells. NestedAnchorTest holds that. Nothing is lost -- the card '
            .'leads to the tournament, where every name is a link.',
    ];

    public function test_every_list_of_players_links_them(): void
    {
        $missing = [];

        foreach (self::LINKED as $view => $what) {
            if (! $this->usesTheComponent($view)) {
                $missing[] = $view.' — '.$what;
            }
        }

        $this->assertSame([], $missing, implode("\n  ", array_merge(
            ['A list names players without linking them to their figures:'], $missing
        )));
    }

    public function test_a_list_exempted_from_linking_really_does_not_link(): void
    {
        // The exemption is a decision, not a hole. If a name in one of these
        // becomes a link again, the entry above is a lie and the page is
        // broken in a browser -- so say it here too, where the reason is
        // written down, rather than only in NestedAnchorTest.
        $linking = [];

        foreach (self::NOT_LINKED as $view => $why) {
            if ($this->usesTheComponent($view)) {
                $linking[] = $view.' — '.$why;
            }
        }

        $this->assertSame([], $linking, implode("\n  ", array_merge(
            ['A list exempted from linking is linking again:'], $linking
        )));
    }

    public function test_the_two_lists_do_not_overlap(): void
    {
        // A view in both would make one of the two tests meaningless, and
        // which one depends on the order they happen to run in.
        $this->assertSame([], array_intersect_key(self::LINKED, self::NOT_LINKED));
    }

    /**
     * The view opens an <x-player-link> tag.
     *
     * An opening tag, not the substring: str_contains('x-player-link') is
     * satisfied by </x-player-link>, by a Blade comment discussing it, and --
     * as a mutation proved -- by <x-NOPE-player-link>. This project has been
     * caught by a class name reading as used inside a longer one three times
     * now; the boundary is the fix each time.
     */
    private function usesTheComponent(string $view): bool
    {
        return (bool) preg_match(
            '/<x-player-link(?![\w-])/',
            (string) file_get_contents(resource_path('views/'.$view))
        );
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
