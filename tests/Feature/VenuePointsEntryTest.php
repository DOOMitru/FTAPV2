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

    /**
     * The form's own source.
     *
     * The page is Vue, so the server sends props and an empty shell: what the
     * markup says is here, and what it is filled with is in props() below.
     */
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
    private function props(array $query = [], ?User $as = null): array
    {
        return $this->actingAs($as ?? $this->admin())
            ->get(route('poker.venue-points.create', $query))->assertOk()
            ->viewData('page')['props'];
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

        $props = $this->props(['venue_id' => $venue->id, 'event_date' => '2026-08-14']);

        $this->assertSame($venue->id, $props['venue_id'], 'The venue just used did not come back.');
        $this->assertNotSame($other->id, $props['venue_id']);
        $this->assertSame('2026-08-14', $props['event_date']);

        // Both seed the form rather than being sent and ignored.
        $page = $this->page();

        $this->assertStringContainsString("venue_id: props.venue_id ?? ''", $page);
        $this->assertStringContainsString("event_date: props.event_date ?? ''", $page);
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

        $props = $this->props(as: $admin);

        $this->assertSame(
            '5 venue points recorded for Ada Lovelace.',
            $this->withoutEmphasis($props['flash']['status'])
        );
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

        $page = $this->page();

        $this->assertStringContainsString('id="player-search"', $page, 'No search box above the list.');
        $this->assertStringContainsString('class="picker"', $page, 'The select was not replaced by a picker.');

        // One haystack per row, covering all four fields the box claims to
        // search. It was a data-search attribute Alpine read off the element;
        // it is a prop now, and the filter reads it directly.
        $ada = collect($this->props()['players'])->firstWhere('name', 'Ada Lovelace');

        $this->assertNotNull($ada, 'Ada is missing from the list.');
        $this->assertMatchesRegularExpression(
            '/^ada lovelace.*countess.*ada@example\.test$/',
            $ada['search']
        );

        $this->assertStringContainsString('player.search.includes(needle)', $page);
    }

    public function test_choosing_a_player_moves_on_to_the_amount(): void
    {
        User::factory()->create();
        $this->venue();

        // The id and the call that reaches for it have to agree, and they are
        // written in two different places.
        $page = $this->page();

        $this->assertStringContainsString('name="amount"', $page);
        $this->assertStringContainsString("getElementById('amount')?.focus()", $page);
    }

    public function test_the_chosen_id_reaches_the_handler_as_a_value(): void
    {
        // This guarded a hazard that no longer exists. Ids are ULIDs, and the
        // Blade wrote them into an Alpine expression -- unquoted, a ULID is a
        // bare word, which is valid-looking markup and a ReferenceError the
        // moment anyone clicks a row. Every other assertion in this file
        // passed while the picker silently did nothing.
        //
        // Props are JSON, so there is no expression to land in and nothing to
        // quote. What is worth keeping is that the id arrives as a STRING: a
        // ULID that came through as anything else would fail exists:users,id
        // on submit.
        $player = User::factory()->create();
        $this->venue();

        // Found by id, not by position: the acting admin is in this list too,
        // and it is ordered by first name -- whose the factory picks at
        // random. An index here is a coin toss.
        $sent = collect($this->props()['players'])->firstWhere('id', $player->id);

        $this->assertNotNull($sent, 'The player is missing from the list.');
        $this->assertIsString($sent['id']);
    }

    public function test_the_sitting_is_asked_for_before_the_player(): void
    {
        User::factory()->create();
        $this->venue();

        $page = $this->page();

        $venueAt = strpos($page, 'id="venue_id"');
        $dateAt = strpos($page, 'name="event_date"');
        $playerAt = strpos($page, 'id="player-search"');
        $amountAt = strpos($page, 'name="amount"');

        $this->assertNotFalse($venueAt);
        $this->assertLessThan($dateAt, $venueAt, 'Venue should come before the date.');
        $this->assertLessThan($playerAt, $dateAt, 'The date should come before the player.');
        $this->assertLessThan($amountAt, $playerAt, 'The player should come before the amount.');
    }

    public function test_the_way_out_is_in_the_header(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('>Back</Btn>', $page);

        // Inside the header's actions slot, which is what "in the header"
        // means. The old version measured this as "before the form" in the
        // rendered HTML, anchored on the form's action attribute; the slot is
        // the thing itself.
        $this->assertMatchesRegularExpression(
            '/<template #actions>\s*<Btn variant="ghost" :href="backHref">Back<\/Btn>/',
            $page
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

        $hint = 'Whole dollars only, rounded to the nearest dollar.';

        // Create alone now. It covered the edit form too, which is gone with
        // the venue-points listing -- a mistyped entry is corrected by
        // recording a second one against it.
        //
        // The field asks for the note beside the label; putting it there, in a
        // .field__label-row with a .field__note the control points at through
        // aria-describedby, is Field's job, and ComponentParityTest holds
        // Field and its Blade twin to the same markup. What can drift here is
        // whether this caller opts in, so that is what is checked.
        $page = $this->page();

        $this->assertMatchesRegularExpression(
            '/hint="'.preg_quote($hint, '/').'"\s+hint-inline/',
            $page,
            "The amount's note is not asked for beside the label."
        );
    }

    public function test_a_hint_left_alone_still_sits_under_its_control(): void
    {
        // The inline note is opt-in. Every other hinted field in the app --
        // the finale thresholds, the points-structure minimum -- keeps the
        // placement it had, and this is what says so.
        //
        // Asked of the page rather than of rendered HTML: that page is Vue
        // now, and where a hint lands is Field's decision, made from whether
        // the caller opted in. So what is checked here is that this caller
        // does not -- the placement itself belongs to Field, which its Blade
        // twin is held to by ComponentParityTest.
        $page = (string) file_get_contents(
            resource_path('js/Pages/Poker/PointsStructure/Create.vue')
        );

        $this->assertStringContainsString('hint="Must be at least 1.', $page);
        $this->assertStringNotContainsString('hint-inline', $page);
        $this->assertStringNotContainsString('hintInline', $page);
    }

    public function test_a_first_visit_starts_at_the_venue(): void
    {
        $this->venue();
        User::factory()->create();

        $this->assertFalse($this->props()['returning']);

        // Which end of the form that puts the caret at. Both bindings read the
        // one prop, so they cannot disagree about which visit this is.
        $page = $this->page();

        $this->assertStringContainsString(':autofocus="! returning"', $page);
        $this->assertStringContainsString(':autofocus="returning"', $page);
    }

    public function test_coming_back_starts_at_the_player(): void
    {
        // The venue and the date are already answered on a return trip, so the
        // caret belongs on the one thing left to say.
        $venue = $this->venue();
        User::factory()->create();

        $this->assertTrue(
            $this->props(['venue_id' => $venue->id, 'event_date' => '2026-08-14'])['returning']
        );

        // And it is refocused after each save, which autofocus cannot do: the
        // page that comes back from one is this same component re-rendered.
        $this->assertStringContainsString("getElementById('player-search')?.focus()", $this->page());
    }

    public function test_half_a_sitting_is_not_a_return_trip(): void
    {
        // A venue with no date cannot be submitted, so the date is still owed
        // and the caret has no business skipping past it to the player.
        $venue = $this->venue();
        User::factory()->create();

        $this->assertFalse($this->props(['venue_id' => $venue->id])['returning']);
    }
}
