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
        $other = $this->venue('Somewhere Else');

        $response = $this->actingAs($this->admin())
            ->get(route('poker.venue-points.create', ['venue_id' => $venue->id]))->assertOk();

        $response->assertViewHas('venueId', $venue->id);

        // Selected in the MARKUP, not merely passed to the view.
        //
        // @selected renders a BARE attribute, and the template puts it on the
        // next line -- so the pattern has to cross whitespace and must not
        // expect selected="selected", which is what two earlier versions of
        // this assertion looked for and never found.
        preg_match_all(
            '/<option value="([^"]+)"[^>]*\bselected\b/',
            $response->getContent(), $chosen
        );

        $this->assertContains($venue->id, $chosen[1], 'The venue arrived unselected.');
        $this->assertNotContains($other->id, $chosen[1]);
    }

    public function test_the_form_still_opens_without_one(): void
    {
        // A link followed without a venue -- a bookmark, or the route typed --
        // must not be a broken page.
        $this->actingAs($this->admin())
            ->get(route('poker.venue-points.create'))->assertOk()
            ->assertViewHas('venueId', null);
    }

    public function test_back_returns_to_the_venue(): void
    {
        $venue = $this->venue();

        $this->actingAs($this->admin())
            ->get(route('poker.venue-points.create', ['venue_id' => $venue->id]))->assertOk()
            ->assertSee(route('poker.venues.show', $venue), false);
    }

    public function test_back_falls_back_to_the_venue_list_with_no_venue(): void
    {
        $this->actingAs($this->admin())
            ->get(route('poker.venue-points.create'))->assertOk()
            ->assertSee(route('poker.venues.index'), false);
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
