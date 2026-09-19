<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * What every Inertia page is handed before it asks for anything.
 *
 * Shared props are on EVERY page, so what goes here is chosen narrowly. The
 * signed-in user is reduced to the five fields the navigation and layout
 * actually use rather than passed as a model: serialising the model would put
 * its password hash, remember token and email into the source of every screen
 * in the app.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                // Null rather than absent when signed out. A template reading
                // auth.user.first_name should fail loudly on a page it was
                // never meant to render, not silently print nothing.
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'nickname' => $user->nickname,
                    // The accessor's answer, not its ingredients. "Nickname,
                    // or first name" is a rule the model owns; re-deriving it
                    // in TypeScript would be a second copy free to disagree.
                    'display_name' => $user->display_name,
                    'is_admin' => (bool) $user->is_admin,
                ],
            ],

            /*
             * Shared, but NOT rendered by the layout.
             *
             * ProfileController flashes the sentinel 'profile-updated',
             * Auth\PasswordController flashes 'password-updated' and
             * Auth\EmailVerificationNotificationController flashes
             * 'verification-link-sent'. Views MATCH those strings to decide
             * whether to show an inline saved indicator; they never display
             * them. Eight other views render their own session('status') as
             * real copy.
             *
             * So a layout-level flash block would show a reader the literal
             * text 'profile-updated', and would double up the eight that
             * render their own. Pages opt in instead, exactly as the Blade
             * views do today -- the same decision, for the same reason, as
             * the note in layouts/app.blade.php.
             *
             * Closures, so the session is read when the response is built
             * rather than when the middleware is constructed.
             */
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],

            /*
             * The shell's notification badge and panel.
             *
             * Shared rather than per-page, for the reason
             * AppServiceProvider::composeNotifications() gives about the View
             * Composer this replaces: thirty-odd controllers should not each
             * remember to supply layout data, and the one that forgot would
             * render a page with no badge and no list, with nothing to say so.
             *
             * Guests get the empty shape rather than a query. This middleware
             * runs on the whole web group, so without the null check every
             * public page would ask the database about notifications for
             * nobody -- the same point the composer makes when it says
             * layouts/public "must not pay for a query it has no user for".
             */
            'notifications' => [
                'unread_count' => fn () => $user?->unreadNotifications()->count() ?? 0,

                // Capped at ten, as the composer caps it. The panel scrolls;
                // three seasons of history should not be serialised into every
                // page the player loads.
                'recent' => fn () => $user === null ? [] : $user->notifications()
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'read_at' => $notification->read_at?->toIso8601String(),
                        'data' => $notification->data,
                    ])
                    ->all(),
            ],
        ];
    }
}
