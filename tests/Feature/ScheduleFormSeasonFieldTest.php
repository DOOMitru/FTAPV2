<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The season on the schedule form.
 *
 * The controller assigns it, so the form states it rather than offering a
 * choice. It was drawn with .row__value -- a class from the definition lists
 * on a profile page, where a value is right-aligned against its label -- which
 * in a form column pushed the season to the right edge while its own label sat
 * on the left, and gave it none of the control's height or offset.
 *
 * Measured in a browser rather than asserted here: at 1280 the static field
 * and the venue select beside it share a top, a height and a left edge, and
 * their labels line up; at 375 the grid stacks them, which is correct. What is
 * checked below is that the markup still asks for the treatment that produces
 * it.
 */
class ScheduleFormSeasonFieldTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The page component's own source.
     *
     * The form is Vue now, so the markup is here rather than in the response:
     * the server sends props and an empty shell. Two of these assertions ask
     * what shape the season is drawn in, and this is where that is written.
     */
    private function page(): string
    {
        $path = resource_path('js/Pages/Poker/Tournaments/Create.vue');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * What the controller sends the page.
     *
     * The value the season box shows is a prop now, which is a better thing to
     * assert than the old "does this string appear anywhere in the HTML" --
     * that passed if the name turned up in a nav link.
     *
     * @return array<string, mixed>
     */
    private function props(bool $withSeason = true): array
    {
        if ($withSeason) {
            PokerSeason::create([
                'name' => 'Season 9', 'start_date' => now()->subMonth(),
                'end_date' => now()->addMonth(), 'is_current' => true,
            ]);
        }

        Venue::create(['name' => 'Hall', 'address' => '1 St']);

        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);

        return $this->actingAs($admin)
            ->get(route('poker.tournaments.create'))->assertOk()
            ->viewData('page')['props'];
    }

    public function test_the_season_is_shaped_like_the_control_beside_it(): void
    {
        $this->assertStringContainsString(
            'class="field__control field__control--static"',
            $this->page()
        );

        $this->assertSame('Season 9', $this->props()['season_label']);
    }

    public function test_it_no_longer_borrows_the_profile_pages_value_class(): void
    {
        // .row__value is text-align: end. That is right for the definition
        // lists it was written for and wrong in a form column, which is the
        // misalignment this fixes.
        $this->assertStringNotContainsString('row__value', $this->page());
    }

    public function test_the_static_field_reads_as_uneditable_without_being_disabled(): void
    {
        // A raised ground says it cannot be typed into. The value keeps full
        // strength text: it is real information, not a greyed-out input.
        $css = file_get_contents(resource_path('css/3-components/_form.css'));

        $this->assertMatchesRegularExpression(
            '/\.field__control--static \{[^}]*background-color: var\(--c-surface-raised\);/s',
            $css
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\.field__control--static \{[^}]*color: var\(--c-text-muted\);/s',
            $css,
            'The value is information, not a disabled control.'
        );
    }

    public function test_it_does_not_answer_the_pointer_like_an_editable_field(): void
    {
        // .field__control:hover moves the border. The two rules are equal
        // specificity, so the static one only wins by being declared after it.
        $css = file_get_contents(resource_path('css/3-components/_form.css'));

        $this->assertMatchesRegularExpression(
            '/\.field__control--static:hover \{\s*border-color: var\(--c-border\);/',
            $css
        );

        $this->assertGreaterThan(
            strpos($css, '.field__control:hover'),
            strpos($css, '.field__control--static:hover'),
            'Declared before the rule it overrides, it loses the tie and does nothing.'
        );
    }

    public function test_with_no_current_season_it_says_so_in_the_same_box(): void
    {
        // Same box, different words -- and the words are chosen in PHP, so
        // that the one place deciding what the season field says is the place
        // that knows whether there is a season (spec 5a).
        $this->assertStringContainsString(
            'class="field__control field__control--static"',
            $this->page()
        );

        $this->assertSame('No active season', $this->props(withSeason: false)['season_label']);
    }
}
