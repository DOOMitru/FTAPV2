<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A player's own notifications.
 *
 * Every action reaches its row through the authenticated user's own relation
 * rather than through a route model binding. Binding first and checking
 * ownership afterwards is the same code with a hole in it, and it answers 403
 * where 404 is correct -- a forbidden response confirms the row exists, which
 * is a thing a stranger should not be able to learn.
 */
class NotificationController extends Controller
{
    /**
     * Read becomes unread and unread becomes read: one control, one route.
     *
     * A toggle rather than two endpoints. The button already knows which way it
     * is pointing, because it is drawn from the row it belongs to, and two
     * routes for two halves of one action is two things to keep in step.
     */
    public function update(Request $request, string $notification): RedirectResponse
    {
        $row = $this->own($request, $notification);

        $row->read_at ? $row->markAsUnread() : $row->markAsRead();

        return back()->with('status', $row->fresh()->read_at
            ? __('Marked as read.')
            : __('Marked as unread.'));
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $row = $this->own($request, $notification);

        if (! $row->read_at) {
            return back()->with('error', __('Mark a notification as read before deleting it.'));
        }

        $row->delete();

        return back()->with('status', __('Notification deleted.'));
    }

    /** Everything already read, in one go. */
    public function clearRead(Request $request): RedirectResponse
    {
        $cleared = $request->user()->readNotifications()->delete();

        return back()->with('status', trans_choice(
            '{0}Nothing to clear.|{1}1 notification cleared.|[2,*]:count notifications cleared.',
            $cleared,
            ['count' => $cleared]
        ));
    }

    /**
     * Scoped to the signed-in user, so somebody else's id is simply not found.
     */
    private function own(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }
}
