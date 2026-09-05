<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Searching the user management table.
 *
 * The list is paginated at 15, so on a real league the person you want is
 * usually not on the page you are looking at. The search covers first name,
 * last name, nickname and email.
 *
 * Every assertion here is on a row's own users.show URL rather than on a name.
 * The page echoes the search term back into the input's value, so asserting
 * assertSee('Lovelace') after searching "Lovelace" passes on the search box
 * alone, with an empty table underneath -- and the matching assertDontSee
 * fails on a page that is perfectly correct. Both were live: the second one
 * failed this suite before the assertions were changed.
 */
class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'first_name' => 'Sitting',
            'last_name' => 'Admin',
            'nickname' => null,
            'email' => 'sitting.admin@example.test',
        ]);
    }

    private function subject(): User
    {
        return User::factory()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'nickname' => 'Countess',
            'email' => 'ada@analytical.test',
        ]);
    }

    private function decoy(): User
    {
        return User::factory()->create([
            'first_name' => 'Blaise',
            'last_name' => 'Pascal',
            'nickname' => 'Triangle',
            'email' => 'blaise@wager.test',
        ]);
    }

    /** @return array<string, array{string}> */
    public static function matchingTerms(): array
    {
        return [
            'first name' => ['Ada'],
            'last name' => ['Lovelace'],
            'nickname' => ['Countess'],
            'email' => ['ada@analytical'],
            'partial' => ['ovela'],
            // Honest note: this one cannot fail on SQLite, whose LIKE already
            // folds ASCII case -- verified by removing the controller's lower()
            // and watching it still pass. It pins the intended behaviour for
            // the day the driver is not SQLite, rather than guarding it today.
            'wrong case' => ['lOvElAcE'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('matchingTerms')]
    public function test_a_search_finds_a_user_by(string $term): void
    {
        $subject = $this->subject();
        $decoy = $this->decoy();

        $this->actingAs($this->admin())
            ->get(route('users.index', ['search' => $term]))
            ->assertOk()
            ->assertSee(route('users.show', $subject), false)
            // The decoy is what makes this a search rather than a page load:
            // without it every assertion here passes on an unfiltered list.
            ->assertDontSee(route('users.show', $decoy), false);
    }

    public function test_a_full_name_matches_across_two_columns(): void
    {
        // The case a single LIKE cannot do. "Ada Lovelace" is in no one column,
        // so each word has to be matched separately and both have to hit.
        $subject = $this->subject();
        $decoy = $this->decoy();

        $this->actingAs($this->admin())
            ->get(route('users.index', ['search' => 'Ada Lovelace']))
            ->assertOk()
            ->assertSee(route('users.show', $subject), false)
            ->assertDontSee(route('users.show', $decoy), false);
    }

    public function test_every_word_has_to_match_not_just_one(): void
    {
        // Guards the AND. If the terms were OR-ed, this would return Ada --
        // her first name matches -- and the search would feel broken in the
        // way that makes people stop typing surnames.
        $subject = $this->subject();
        $decoy = $this->decoy();

        $this->actingAs($this->admin())
            ->get(route('users.index', ['search' => 'Ada Pascal']))
            ->assertOk()
            ->assertDontSee(route('users.show', $subject), false)
            ->assertDontSee(route('users.show', $decoy), false);
    }

    public function test_an_empty_search_lists_everyone(): void
    {
        $subject = $this->subject();
        $decoy = $this->decoy();

        $this->actingAs($this->admin())
            ->get(route('users.index', ['search' => '   ']))
            ->assertOk()
            ->assertSee(route('users.show', $subject), false)
            ->assertSee(route('users.show', $decoy), false);
    }

    public function test_a_search_with_no_matches_says_which_term_failed(): void
    {
        // "No users found." under a search reads as an empty database rather
        // than an empty result.
        $subject = $this->subject();

        $this->actingAs($this->admin())
            ->get(route('users.index', ['search' => 'Babbage']))
            ->assertOk()
            ->assertSee('No users match')
            ->assertSee('Babbage')
            ->assertDontSee(route('users.show', $subject), false);
    }

    public function test_paging_a_search_keeps_the_search(): void
    {
        // withQueryString. Without it page 2 of a search is page 2 of
        // everybody, which looks like the search silently gave up.
        $this->admin();

        // 20 matches, so the 15-per-page list has a second page.
        User::factory()->count(20)->create(['last_name' => 'Searchable']);
        User::factory()->count(5)->create(['last_name' => 'Unrelated']);

        $body = $this->actingAs(User::where('is_admin', true)->first())
            ->get(route('users.index', ['search' => 'Searchable']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'search=Searchable',
            $body,
            'The pagination links must carry the search term.'
        );
    }

    public function test_a_non_admin_still_cannot_reach_the_list_by_searching(): void
    {
        $this->subject();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('users.index', ['search' => 'Ada']))
            ->assertForbidden();
    }

    public function test_the_echoed_search_term_is_escaped(): void
    {
        // The term comes back twice -- in the input's value and in the empty
        // state's title -- so this page reflects user input by design. Both go
        // through {{ }}, and this is what keeps it that way.
        $this->subject();

        $this->actingAs($this->admin())
            ->get(route('users.index', ['search' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_the_icon_only_controls_still_have_names(): void
    {
        // Both controls are a bare SVG. Their accessible name comes from a
        // visually-hidden span, and losing it is invisible -- the page looks
        // identical and a screen reader is left announcing "button".
        $subject = $this->subject();

        $this->actingAs($this->admin())
            ->get(route('users.index', ['search' => 'Ada']))
            ->assertOk()
            ->assertSee('<span class="u-visually-hidden">Search</span>', false)
            ->assertSee('<span class="u-visually-hidden">Clear search</span>', false)
            ->assertSee(route('users.show', $subject), false);
    }
}
