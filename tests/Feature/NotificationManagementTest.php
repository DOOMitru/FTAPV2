<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A player's own notifications are theirs to manage.
 *
 * The obvious hole in a feature like this is a route model binding that will
 * happily fetch somebody else's row, so every action here reaches its record
 * through the authenticated user's own relation rather than checking ownership
 * after the fact.
 */
class NotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TournamentPlacement',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'tournament_id' => 'abc', 'tournament_name' => 'Autumn Showdown',
                'played_on' => '2026-09-09', 'place' => 1, 'points' => 100,
            ],
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_a_player_can_mark_one_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->notify($user);

        $this->actingAs($user)->patch(route('notifications.update', $notification))
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_the_same_control_marks_it_unread_again(): void
    {
        // One route, one button whose label flips. Two routes for two halves of
        // one toggle is two things to keep in step.
        $user = User::factory()->create();
        $notification = $this->notify($user, read: true);

        $this->actingAs($user)->patch(route('notifications.update', $notification));

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_a_read_notification_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $notification = $this->notify($user, read: true);

        $this->actingAs($user)->delete(route('notifications.destroy', $notification))
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_an_unread_notification_cannot_be_deleted(): void
    {
        // Deleting something you have not looked at is how a player loses a
        // result they never saw.
        $user = User::factory()->create();
        $notification = $this->notify($user);

        $this->actingAs($user)->delete(route('notifications.destroy', $notification))
            ->assertSessionHas('error');

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_clearing_read_leaves_the_unread_ones(): void
    {
        $user = User::factory()->create();
        $this->notify($user, read: true);
        $this->notify($user, read: true);
        $this->notify($user);

        $this->actingAs($user)->delete(route('notifications.clear-read'))
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertSame(1, $user->fresh()->notifications()->count());
        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_clearing_read_touches_nobody_else(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->notify($user, read: true);
        $this->notify($other, read: true);

        $this->actingAs($user)->delete(route('notifications.clear-read'));

        $this->assertSame(1, $other->fresh()->notifications()->count());
    }

    public function test_a_player_cannot_reach_another_players_notification(): void
    {
        // 404 rather than 403: a forbidden response confirms the row exists.
        $user = User::factory()->create();
        $victim = $this->notify(User::factory()->create(), read: true);

        $this->actingAs($user)->patch(route('notifications.update', $victim))->assertNotFound();
        $this->actingAs($user)->delete(route('notifications.destroy', $victim))->assertNotFound();

        $this->assertSame(1, DatabaseNotification::count());
        $this->assertNotNull($victim->fresh()->read_at, 'Its read state was changed by a stranger.');
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $notification = $this->notify(User::factory()->create());

        $this->patch(route('notifications.update', $notification))->assertRedirect(route('login'));
        $this->delete(route('notifications.clear-read'))->assertRedirect(route('login'));
    }

    public function test_the_literal_clear_route_is_not_swallowed_by_the_parameter(): void
    {
        // /notifications/read must be declared before /notifications/{id} or it
        // is matched as an id and 404s. Ids are UUIDs so a real collision cannot
        // happen, which is exactly why the bug would be silent.
        $user = User::factory()->create();
        $this->notify($user, read: true);

        $this->actingAs($user)->delete('/notifications/read')->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_clearing_when_there_is_nothing_read_says_so(): void
    {
        $user = User::factory()->create();
        $this->notify($user);

        $this->actingAs($user)->delete(route('notifications.clear-read'))
            ->assertSessionHas('status', fn ($message) => filled($message));

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }
}
