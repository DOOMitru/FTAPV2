<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * How a placement looks.
 *
 * The tier is the whole point: a first place should carry the same gold the
 * player just saw on the podium, and a fourth place should not claim a medal it
 * did not win.
 */
class NotificationCardTest extends TestCase
{
    use RefreshDatabase;

    private function card(int $place, int $points, bool $read = false): string
    {
        $user = User::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TournamentPlacement',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'tournament_id' => 'abc',
                'tournament_name' => 'Autumn Showdown',
                'played_on' => '2026-09-09',
                'place' => $place,
                'points' => $points,
            ],
            'read_at' => $read ? now() : null,
        ]);

        return Blade::render('<x-notification-card :notification="$n" />', ['n' => $notification]);
    }

    public function test_first_place_wears_gold(): void
    {
        $this->assertStringContainsString('notification--gold', $this->card(1, 100));
    }

    public function test_second_and_third_wear_silver_and_bronze(): void
    {
        $this->assertStringContainsString('notification--silver', $this->card(2, 60));
        $this->assertStringContainsString('notification--bronze', $this->card(3, 40));
    }

    public function test_a_scoring_finish_off_the_podium_is_not_a_medal(): void
    {
        $html = $this->card(4, 20);

        $this->assertStringContainsString('notification--scored', $html);
        $this->assertStringNotContainsString('notification--gold', $html);
        $this->assertStringNotContainsString('notification--bronze', $html);
    }

    public function test_a_distant_finish_is_still_a_scored_finish(): void
    {
        // The tier is a match on 1, 2 and 3 with everything else falling
        // through, so eleventh must not land somewhere unstyled.
        $this->assertStringContainsString('notification--scored', $this->card(11, 5));
    }

    public function test_it_says_the_place_the_tournament_and_the_points(): void
    {
        $html = $this->card(1, 100);

        $this->assertStringContainsString('1st', $html);
        $this->assertStringContainsString('Autumn Showdown', $html);
        $this->assertStringContainsString('100', $html);
    }

    public function test_the_date_is_written_for_a_reader(): void
    {
        // Stored as 2026-09-09, which is a value rather than a sentence.
        $this->assertStringContainsString('Sep 9, 2026', $this->card(1, 100));
    }

    public function test_an_unread_card_is_marked_as_unread(): void
    {
        $this->assertStringContainsString('notification--unread', $this->card(1, 100));
        $this->assertStringNotContainsString('notification--unread', $this->card(1, 100, read: true));
    }

    public function test_an_unread_card_offers_read_but_not_delete(): void
    {
        $html = $this->card(1, 100);

        $this->assertStringContainsString('Mark as read', $html);
        $this->assertStringNotContainsString('Delete notification', $html);
    }

    public function test_a_read_card_offers_unread_and_delete(): void
    {
        $html = $this->card(1, 100, read: true);

        $this->assertStringContainsString('Mark as unread', $html);
        $this->assertStringContainsString('Delete notification', $html);
    }

    public function test_deleting_is_confirmed_and_names_the_tournament(): void
    {
        // The panel draws a column of these; "delete this notification?" would
        // be whichever one you happened to click.
        $this->assertStringContainsString(
            'Delete this notification about Autumn Showdown?',
            $this->withoutEmphasis($this->card(1, 100, read: true))
        );
    }

    public function test_a_card_survives_data_it_did_not_expect(): void
    {
        // The store is general -- a placement is one type of notification, not
        // the only one it can hold -- and a row written by something else must
        // not take a page down with it.
        $user = User::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SomethingElse',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'unrelated'],
            'read_at' => null,
        ]);

        $html = Blade::render('<x-notification-card :notification="$n" />', ['n' => $notification]);

        $this->assertStringContainsString('notification', $html);
    }
}
