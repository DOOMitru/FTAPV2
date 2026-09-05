<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entering venue points for a night at a venue.
 *
 * The shape of the work is one venue, one date, and a dozen players who were
 * there. So the form asks for the sitting first and hands it back to itself
 * afterwards, and only the player and the amount change between entries.
 */
class VenuePointsEntryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    }

    /**
     * Every test here records venue points, and venue points now need a season
     * to belong to -- the controller refuses a date no season covers, because
     * points outside every season count toward nothing.
     */
    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\PokerSeason::create([
            'name' => 'Season 40',
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);
    }

    private function venue(string $name = 'Diamond Club'): Venue
    {
        return Venue::create(['name' => $name, 'address' => '1 Card Street']);
    }

    public function test_saving_returns_to_the_form_carrying_the_venue_and_the_date(): void
    {
        $venue = $this->venue();
        $player = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $this->actingAs($this->admin())
            ->post(route('poker.venue-points.store'), [
                'venue_id' => $venue->id,
                'event_date' => '2026-08-14',
                'user_id' => $player->id,
                'user_name' => 'Ada Lovelace',
                'amount' => 5,
            ])
            ->assertRedirect(route('poker.venue-points.create', [
                'venue_id' => $venue->id,
                'event_date' => '2026-08-14',
            ]));
    }

    public function test_the_returned_form_has_that_venue_and_date_already_filled(): void
    {
        $venue = $this->venue();
        $other = $this->venue('Rail Street Tavern');

        $html = $this->actingAs($this->admin())
            ->get(route('poker.venue-points.create', ['venue_id' => $venue->id, 'event_date' => '2026-08-14']))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="'.$venue->id.'"\s+selected/',
            $html,
            'The venue just used is not selected.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="'.$other->id.'"\s+selected/',
            $html,
            'A venue that was not used is selected.'
        );
        $this->assertStringContainsString('value="2026-08-14"', $html);
    }

    public function test_a_first_visit_preselects_nothing(): void
    {
        $venue = $this->venue();

        $html = $this->actingAs($this->admin())->get(route('poker.venue-points.create'))
            ->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<option value="'.$venue->id.'"\s+selected/', $html);
    }

    public function test_the_confirmation_names_the_player_and_the_amount(): void
    {
        $venue = $this->venue();
        $player = User::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('poker.venue-points.store'), [
            'venue_id' => $venue->id,
            'event_date' => '2026-08-14',
            'user_id' => $player->id,
            'user_name' => 'Ada Lovelace',
            'amount' => 5,
        ]);

        $this->actingAs($admin)
            ->get(route('poker.venue-points.create'))->assertOk()
            ->assertSee('5 venue points recorded for Ada Lovelace.');
    }

    public function test_a_whole_night_can_be_entered_without_leaving_the_form(): void
    {
        $venue = $this->venue();
        $admin = $this->admin();

        foreach (['Ada', 'Grace', 'Katherine'] as $index => $name) {
            $player = User::factory()->create(['first_name' => $name, 'last_name' => 'Player']);

            $this->actingAs($admin)->post(route('poker.venue-points.store'), [
                'venue_id' => $venue->id,
                'event_date' => '2026-08-14',
                'user_id' => $player->id,
                'user_name' => $name.' Player',
                'amount' => 5 - $index,
            ])->assertRedirect(route('poker.venue-points.create', [
                'venue_id' => $venue->id,
                'event_date' => '2026-08-14',
            ]));
        }

        $this->assertSame(3, VenuePoints::count());
        $this->assertSame([$venue->id], VenuePoints::distinct()->pluck('venue_id')->all());
        $this->assertSame(['Ada Player', 'Grace Player', 'Katherine Player'],
            VenuePoints::orderBy('id')->pluck('user_name')->all());
    }

    public function test_the_player_list_is_searchable_by_name_nickname_and_email(): void
    {
        User::factory()->create([
            'first_name' => 'Ada', 'last_name' => 'Lovelace',
            'nickname' => 'Countess', 'email' => 'ada@example.test',
        ]);
        $this->venue();

        $html = $this->actingAs($this->admin())->get(route('poker.venue-points.create'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('id="player-search"', $html, 'No search box above the list.');
        $this->assertStringContainsString('class="picker"', $html, 'The select was not replaced by a picker.');

        // One haystack per row, covering all four fields the box claims to search.
        $this->assertMatchesRegularExpression(
            '/data-search="[^"]*ada lovelace[^"]*countess[^"]*ada@example\.test[^"]*"/',
            $html
        );
    }

    public function test_choosing_a_player_moves_on_to_the_amount(): void
    {
        User::factory()->create();
        $this->venue();

        $html = $this->actingAs($this->admin())->get(route('poker.venue-points.create'))
            ->assertOk()->getContent();

        // The ref and the call that uses it have to agree, and they are written
        // in two different places.
        $this->assertStringContainsString('x-ref="amount"', $html);
        $this->assertStringContainsString('$refs.amount.focus()', $html);
    }

    public function test_the_chosen_id_reaches_the_handler_as_a_value(): void
    {
        // Ids here are ULIDs, so an unquoted {{ $user->id }} renders as a bare
        // word -- valid-looking markup and a ReferenceError the moment anyone
        // clicks a row, with the picker silently doing nothing. Every other
        // assertion in this file passed while that was true.
        $player = User::factory()->create();
        $this->venue();

        $html = $this->actingAs($this->admin())->get(route('poker.venue-points.create'))
            ->assertOk()->getContent();

        $this->assertStringContainsString("id: '".$player->id."'", $html);
        $this->assertStringNotContainsString('id: '.$player->id.',', $html);
    }

    public function test_the_sitting_is_asked_for_before_the_player(): void
    {
        User::factory()->create();
        $this->venue();

        $html = $this->actingAs($this->admin())->get(route('poker.venue-points.create'))
            ->assertOk()->getContent();

        $venueAt = strpos($html, 'id="venue_id"');
        $dateAt = strpos($html, 'id="event_date"');
        $playerAt = strpos($html, 'id="player-search"');
        $amountAt = strpos($html, 'id="amount"');

        $this->assertNotFalse($venueAt);
        $this->assertLessThan($dateAt, $venueAt, 'Venue should come before the date.');
        $this->assertLessThan($playerAt, $dateAt, 'The date should come before the player.');
        $this->assertLessThan($amountAt, $playerAt, 'The player should come before the amount.');
    }

    public function test_the_way_out_is_in_the_header(): void
    {
        $this->venue();

        $html = $this->actingAs($this->admin())->get(route('poker.venue-points.create'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('>Back<', $html);

        // Before the form, which is what "in the header" means in this markup.
        // Anchored on this form's own action: the navigation renders a POST
        // form of its own to sign out, and it comes first on every page.
        $this->assertLessThan(
            strpos($html, 'action="'.route('poker.venue-points.store').'"'),
            strpos($html, '>Back<')
        );
    }

    public function test_the_amount_field_says_what_it_wants(): void
    {
        // The rule is already enforced -- the controller validates an integer,
        // and type="number" steps by one -- but a form that rejects 12.50 on
        // submit without ever having said so is a form that wasted the entry.
        $venue = $this->venue();
        $point = VenuePoints::create([
            'venue_id' => $venue->id,
            'user_id' => User::factory()->create()->id,
            'user_name' => 'Ada Lovelace',
            'event_date' => '2026-08-14',
            'amount' => 5,
        ]);

        $admin = $this->admin();
        $hint = 'Whole dollars only, rounded to the nearest dollar.';

        foreach ([route('poker.venue-points.create'), route('poker.venue-points.edit', $point)] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->assertSee($hint)->getContent();

            // Beside the label, not under the control -- and said once, not in
            // both places.
            $this->assertMatchesRegularExpression(
                '/<div class="field__label-row">\s*<label class="field__label" for="amount">\s*Amount\s*<\/label>\s*'
                .'<span class="field__note" id="amount-note">\s*'.preg_quote($hint, '/').'/',
                $html,
                "The note is not on the label's line on {$url}."
            );

            $this->assertStringNotContainsString('<p class="field__hint">'.$hint, $html);

            // And pointed at, so it is announced with the field rather than
            // being decoration that happens to sit nearby.
            $this->assertMatchesRegularExpression('/id="amount"[^>]*aria-describedby="amount-note"/s', $html);
        }
    }

    public function test_a_hint_left_alone_still_sits_under_its_control(): void
    {
        // The inline note is opt-in. Every other hinted field in the app --
        // the finale thresholds, the points-structure minimum -- keeps the
        // placement it had, and this is what says so.
        $html = $this->actingAs($this->admin())->get(route('poker.points-structure.create'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('<p class="field__hint">', $html);
        $this->assertStringNotContainsString('field__label-row', $html);
    }

    public function test_a_first_visit_starts_at_the_venue(): void
    {
        $this->venue();
        User::factory()->create();

        $html = $this->actingAs($this->admin())->get(route('poker.venue-points.create'))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="venue_id"[^>]*autofocus/s', $html);
        $this->assertDoesNotMatchRegularExpression('/id="player-search"[^>]*autofocus/s', $html);
    }

    public function test_coming_back_starts_at_the_player(): void
    {
        // The venue and the date are already answered on a return trip, so the
        // caret belongs on the one thing left to say.
        $venue = $this->venue();
        User::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('poker.venue-points.create', ['venue_id' => $venue->id, 'event_date' => '2026-08-14']))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="player-search"[^>]*autofocus/s', $html);
        $this->assertDoesNotMatchRegularExpression('/id="venue_id"[^>]*autofocus/s', $html);
    }

    public function test_half_a_sitting_is_not_a_return_trip(): void
    {
        // A venue with no date cannot be submitted, so the date is still owed
        // and the caret has no business skipping past it to the player.
        $venue = $this->venue();
        User::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('poker.venue-points.create', ['venue_id' => $venue->id]))
            ->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/id="player-search"[^>]*autofocus/s', $html);
    }
}
