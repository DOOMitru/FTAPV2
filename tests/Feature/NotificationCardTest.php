<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * How a placement looks.
 *
 * The tier is the whole point: a first place should carry the same gold the
 * player just saw on the podium, and a fourth place should not claim a medal it
 * did not win.
 *
 * Read off the props the page is sent, plus the template that draws them. The
 * card is Vue now and PHP decides every value in it -- spec §5a -- so the tier,
 * the ordinal, the date and the confirmation sentence are assertions about the
 * shared prop, and which control appears when is an assertion about the
 * template.
 *
 * The version of this file that rendered `<x-notification-card>` did not fail
 * usefully when that component was deleted: Blade::render returns the view NAME
 * when it cannot resolve one, so ten cases failed with a confusing message and
 * the eleventh -- which asserted only that the output contained "notification"
 * -- passed against the string 'components.notification-card'.
 */
class NotificationCardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One placement, as the page receives it.
     *
     * @return array<string, mixed>
     */
    private function card(int $place, int $points, bool $read = false): array
    {
        return $this->propsFor([
            'tournament_id' => 'abc',
            'tournament_name' => 'Autumn Showdown',
            'played_on' => '2026-09-09',
            'place' => $place,
            'points' => $points,
        ], $read);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function propsFor(array $data, bool $read = false, string $type = 'TournamentPlacement'): array
    {
        $user = User::factory()->create(['approval_status' => 'approved']);

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\'.$type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => $data,
            'read_at' => $read ? now() : null,
        ]);

        return $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->viewData('page')['props']['notifications']['recent'][0];
    }

    /** The template that draws a card. */
    private function template(): string
    {
        return (string) file_get_contents(resource_path('js/Components/NotificationCard.vue'));
    }

    public function test_first_place_wears_gold(): void
    {
        $this->assertSame('gold', $this->card(1, 100)['tier']);
    }

    public function test_second_and_third_wear_silver_and_bronze(): void
    {
        $this->assertSame('silver', $this->card(2, 60)['tier']);
        $this->assertSame('bronze', $this->card(3, 40)['tier']);
    }

    public function test_a_scoring_finish_off_the_podium_is_not_a_medal(): void
    {
        $this->assertSame('scored', $this->card(4, 20)['tier']);
    }

    public function test_a_distant_finish_is_still_a_scored_finish(): void
    {
        // The tier is a match on 1, 2 and 3 with everything else falling
        // through, so eleventh must not land somewhere unstyled.
        $this->assertSame('scored', $this->card(11, 5)['tier']);
    }

    public function test_every_tier_has_a_class_to_wear(): void
    {
        // The tier is semantic in the props and becomes a class in the
        // template, through a written-out map rather than an interpolated
        // name. A tier with no entry renders a card with no medal at all and
        // nothing says so -- which is the failure the map exists to prevent,
        // so the two halves are asserted against each other here.
        $template = $this->template();

        foreach (['gold', 'silver', 'bronze', 'scored'] as $tier) {
            $this->assertMatchesRegularExpression(
                '/\b'.$tier.': \'notification--'.$tier.'\'/',
                $template,
                "The template has no class for the '{$tier}' tier."
            );
        }
    }

    public function test_it_says_the_place_the_tournament_and_the_points(): void
    {
        $card = $this->card(1, 100);

        $this->assertSame('1st', $card['place_label']);
        $this->assertSame('Autumn Showdown', $card['title']);
        $this->assertStringContainsString('100', $card['meta']);
    }

    public function test_the_date_is_written_for_a_reader(): void
    {
        // Stored as 2026-09-09, which is a value rather than a sentence.
        $this->assertStringContainsString('Sep 9, 2026', $this->card(1, 100)['meta']);
    }

    public function test_an_unread_card_is_marked_as_unread(): void
    {
        $this->assertFalse($this->card(1, 100)['is_read']);
        $this->assertTrue($this->card(1, 100, read: true)['is_read']);

        // And the template hangs the marker on exactly that flag. Asserting
        // the class is absent from the response would pass for the wrong
        // reason -- no card is ever IN the response, read or unread.
        $this->assertStringContainsString(
            "props.notification.is_read ? '' : 'notification--unread'",
            $this->template()
        );
    }

    public function test_an_unread_card_offers_read_but_not_delete(): void
    {
        $template = $this->template();

        // One control, labelled by the direction the click goes.
        $this->assertStringContainsString(
            "notification.is_read ? 'Mark as unread' : 'Mark as read'",
            $template
        );

        // Deleting is offered only once read. Deleting something you have not
        // looked at is how a player loses a result they never saw, and the
        // controller refuses it too.
        $this->assertMatchesRegularExpression(
            '/v-if="notification\.is_read".*?label="Delete notification"/s',
            $template,
            'Delete is offered on a card that has not been read.'
        );
    }

    public function test_deleting_is_confirmed_and_names_the_tournament(): void
    {
        // The panel draws a column of these; "delete this notification?" would
        // be whichever one you happened to click.
        $this->assertSame(
            'Delete this notification about Autumn Showdown?',
            $this->withoutEmphasis($this->card(1, 100, read: true)['delete_confirm'])
        );

        // And the control asks rather than acting. A first draft called
        // delete() straight from the button, which quietly turned a confirmed
        // destructive action into an unconfirmed one.
        $this->assertStringContainsString("emit('requestDelete', notification)", $this->template());
    }

    public function test_a_card_survives_data_it_did_not_expect(): void
    {
        // The store is general -- a placement is one type of notification, not
        // the only one it can hold -- and a row written by something else must
        // not take a page down with it. The page is asserted OK inside
        // propsFor(), which is the half that used to be missing: the old
        // version only checked that the output contained "notification", and
        // went on passing after the component it rendered was deleted.
        $card = $this->propsFor(['message' => 'unrelated'], type: 'SomethingElse');

        $this->assertSame('scored', $card['tier']);
        $this->assertSame('A tournament', $card['title']);
        $this->assertFalse($card['is_read']);
    }
}
