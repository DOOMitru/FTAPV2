<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recording venue points from the venue they were earned at.
 *
 * The admin listing is gone. The form is reached from a venue's own page with
 * that venue already chosen, which is the point of moving the action: somebody
 * entering a night's points is standing on the venue in question, and picking
 * it again from a list of every venue in the league is a step that only exists
 * because the form used to be reached from nowhere in particular.
 */
class VenuePointsFromVenuePageTest extends TestCase
{
    use RefreshDatabase;

    private function venue(string $name = 'The Grand Card Room'): Venue
    {
        return Venue::create(['name' => $name, 'address' => '1 St']);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    public function test_the_venue_page_offers_the_action(): void
    {
        $venue = $this->venue();

        $this->actingAs($this->admin())
            ->get(route('poker.venues.show', $venue))->assertOk()
            ->assertSee('Add points')
            // Carrying the venue, which is the whole point of moving it here.
            ->assertSee(route('poker.venue-points.create', ['venue_id' => $venue->id]), false);
    }

    public function test_the_form_opens_with_that_venue_chosen(): void
    {
        $venue = $this->venue();
        $this->venue('Somewhere Else');

        $props = $this->props(['venue_id' => $venue->id]);

        // The id, which the select is bound to. The old version of this read
        // the rendered <option> tags looking for a bare `selected` attribute,
        // and two earlier versions of THAT looked for selected="selected" and
        // never found it. A prop has one spelling.
        $this->assertSame($venue->id, $props['venue_id']);

        // And the select really is bound to it, rather than the prop being
        // sent and ignored.
        $this->assertStringContainsString('v-model="form.venue_id"', $this->page());
    }

    public function test_the_form_still_opens_without_one(): void
    {
        // A link followed without a venue -- a bookmark, or the route typed --
        // must not be a broken page.
        $this->assertNull($this->props()['venue_id']);
    }

    public function test_back_returns_to_the_venue_or_to_the_list_without_one(): void
    {
        // Both branches of one expression, so they are read together. The page
        // builds the href from the venue_id prop; what the two tests above
        // establish is that the prop is the venue when there is one and null
        // when there is not.
        // A nowdoc: ${...} in a double-quoted PHP string is interpolation, and
        // the expression being looked for is full of it.
        $expected = <<<'JS'
props.venue_id ? `/poker/venues/${props.venue_id}` : '/poker/venues'
JS;

        $this->assertStringContainsString($expected, $this->page());
    }

    /** The page component's own source. The markup is Vue's now. */
    private function page(): string
    {
        $path = resource_path('js/Pages/Poker/VenuePoints/Create.vue');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * What the controller sends the form.
     *
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function props(array $query = []): array
    {
        return $this->actingAs($this->admin())
            ->get(route('poker.venue-points.create', $query))->assertOk()
            ->viewData('page')['props'];
    }

    public function test_saving_returns_to_the_form_with_the_venue_and_date_kept(): void
    {
        // A night at a venue is a dozen players entered one after another.
        PokerSeason::create([
            'name' => 'Season 9', 'start_date' => '2026-08-01',
            'end_date' => '2026-12-31', 'is_current' => true,
        ]);

        $venue = $this->venue();
        $player = User::factory()->create(['first_name' => 'Wanda', 'last_name' => 'Reeve']);

        $this->actingAs($this->admin())->post(route('poker.venue-points.store'), [
            'venue_id' => $venue->id,
            'event_date' => '2026-09-11',
            'user_id' => $player->id,
            'user_name' => 'Wanda',
            'amount' => 5,
        ])->assertRedirect(route('poker.venue-points.create', [
            'venue_id' => $venue->id,
            'event_date' => '2026-09-11',
        ]));

        $this->assertSame(5, VenuePoints::firstOrFail()->amount);
    }

    public function test_a_mistake_is_corrected_by_recording_against_it(): void
    {
        // There is no edit and no delete. This is the remedy that replaces
        // them, and it only works because the amount may be negative.
        PokerSeason::create([
            'name' => 'Season 9', 'start_date' => '2026-08-01',
            'end_date' => '2026-12-31', 'is_current' => true,
        ]);

        $venue = $this->venue();
        $player = User::factory()->create(['first_name' => 'Wanda', 'last_name' => 'Reeve']);
        $admin = $this->admin();

        $enter = fn (int $amount) => $this->actingAs($admin)->post(route('poker.venue-points.store'), [
            'venue_id' => $venue->id,
            'event_date' => '2026-09-11',
            'user_id' => $player->id,
            'user_name' => 'Wanda',
            'amount' => $amount,
        ]);

        $enter(50)->assertSessionHasNoErrors();
        $enter(-45)->assertSessionHasNoErrors();

        $this->assertSame(5, (int) VenuePoints::where('user_id', $player->id)->sum('amount'));
    }

    public function test_the_listing_and_its_edit_and_delete_are_gone(): void
    {
        foreach (['index', 'edit', 'update', 'destroy'] as $action) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Route::has('poker.venue-points.'.$action),
                'poker.venue-points.'.$action.' should not exist.'
            );
        }
    }
}
