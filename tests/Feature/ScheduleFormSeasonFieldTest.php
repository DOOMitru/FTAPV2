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

    private function form(bool $withSeason = true): string
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
            ->get(route('poker.tournaments.create'))->assertOk()->getContent();
    }

    public function test_the_season_is_shaped_like_the_control_beside_it(): void
    {
        $html = $this->form();

        $this->assertStringContainsString('class="field__control field__control--static"', $html);
        $this->assertStringContainsString('Season 9', $html);
    }

    public function test_it_no_longer_borrows_the_profile_pages_value_class(): void
    {
        // .row__value is text-align: end. That is right for the definition
        // lists it was written for and wrong in a form column, which is the
        // misalignment this fixes.
        $this->assertStringNotContainsString('row__value', $this->form());
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
        $html = $this->form(withSeason: false);

        $this->assertStringContainsString('class="field__control field__control--static"', $html);
        $this->assertStringContainsString('No active season', $html);
    }
}
