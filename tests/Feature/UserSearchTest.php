<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    /**
     * The ids the list came back with.
     *
     * The assertions this replaces looked for each user's View URL in the
     * rendered page, and the docblock above says why they could not simply
     * look for the name: the search box echoes the term, so assertSee('Lovelace')
     * passes on the box alone with an empty table underneath.
     *
     * Props end that problem rather than working around it. The term and the
     * rows are separate keys, so asking which users came back is a direct
     * question with a direct answer.
     *
     * @return list<string>
     */
    private function listedIds(?string $term = null): array
    {
        $url = $term === null
            ? route('users.index')
            : route('users.index', ['search' => $term]);

        // An existing admin if there is one. admin() creates a user with a
        // fixed email, so calling this twice in one test violated the unique
        // constraint rather than answering the question twice.
        $admin = User::where('is_admin', true)->first() ?? $this->admin();

        $props = $this->actingAs($admin)->get($url)->assertOk()
            ->viewData('page')['props'];

        return collect($props['users']['data'])->pluck('id')->all();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('matchingTerms')]
    public function test_a_search_finds_a_user_by(string $term): void
    {
        $subject = $this->subject();
        $decoy = $this->decoy();

        $listed = $this->listedIds($term);

        $this->assertContains($subject->id, $listed);

        // The decoy is what makes this a search rather than a page load:
        // without it every assertion here passes on an unfiltered list.
        $this->assertNotContains($decoy->id, $listed);
    }

    public function test_a_full_name_matches_across_two_columns(): void
    {
        // The case a single LIKE cannot do. "Ada Lovelace" is in no one column,
        // so each word has to be matched separately and both have to hit.
        $subject = $this->subject();
        $decoy = $this->decoy();

        $listed = $this->listedIds('Ada Lovelace');

        $this->assertContains($subject->id, $listed);
        $this->assertNotContains($decoy->id, $listed);
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

        $listed = $this->listedIds('   ');

        $this->assertContains($subject->id, $listed);
        $this->assertContains($decoy->id, $listed);
    }

    public function test_a_search_with_no_matches_says_which_term_failed(): void
    {
        // "No users found." under a search reads as an empty database rather
        // than an empty result.
        $subject = $this->subject();

        $this->actingAs($this->admin())
            ->get(route('users.index', ['search' => 'Babbage']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // The term comes back, so the page can say which search
                // failed: "No users found." under a search reads as an empty
                // database rather than an empty result.
                ->where('search', 'Babbage')
                ->has('users.data', 0)
            );

        $this->assertNotContains($subject->id, $this->listedIds('Babbage'));
    }

    public function test_paging_a_search_keeps_the_search(): void
    {
        // withQueryString. Without it page 2 of a search is page 2 of
        // everybody, which looks like the search silently gave up.
        $this->admin();

        // 20 matches, so the 15-per-page list has a second page.
        User::factory()->count(20)->create(['last_name' => 'Searchable']);
        User::factory()->count(5)->create(['last_name' => 'Unrelated']);

        $props = $this->actingAs(User::where('is_admin', true)->first())
            ->get(route('users.index', ['search' => 'Searchable']))
            ->assertOk()->viewData('page')['props'];

        $urls = collect($props['users']['links'])->pluck('url')->filter()->implode(' ');

        $this->assertStringContainsString(
            'search=Searchable',
            $urls,
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
        // state's title -- so this page reflects user input by design.
        //
        // It travels as a prop now and is interpolated by Vue, which escapes
        // as {{ }} did. The claim worth asserting is the one that matters:
        // the script tag never reaches the document as markup. The positive
        // form is dropped because it depended on how the payload is encoded,
        // which is Inertia's business rather than this rule's.
        $this->subject();

        $response = $this->actingAs($this->admin())
            ->get(route('users.index', ['search' => '<script>alert(1)</script>']))
            ->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false);

        // And it survives intact as DATA, so the page can echo it back.
        $this->assertSame(
            '<script>alert(1)</script>',
            $response->viewData('page')['props']['search']
        );
    }

    public function test_the_icon_only_controls_still_have_names(): void
    {
        // Both controls are a bare SVG. Their accessible name comes from a
        // visually-hidden span, and losing it is invisible -- the page looks
        // identical and a screen reader is left announcing "button".
        $subject = $this->subject();

        $this->assertContains($subject->id, $this->listedIds('Ada'));

        // The names are in the page file now. Clear appears only when there is
        // something to clear, which is a v-if rather than a second render.
        $page = file_get_contents(resource_path('js/Pages/Users/Index.vue'));

        $this->assertStringContainsString('<span class="u-visually-hidden">Search</span>', $page);
        $this->assertStringContainsString('<span class="u-visually-hidden">Clear search</span>', $page);
    }
}
