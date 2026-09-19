<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
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
                    // The letters, not the name to reduce. initials() is one
                    // definition by design -- see the note at its call site in
                    // monogram.blade.php. It takes the FULL name, because a
                    // player nicknamed "Ace" must not become a lone A.
                    'initials' => initials(trim($user->first_name.' '.$user->last_name)),
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
                    ->map(fn ($notification) => $this->notification($notification))
                    ->all(),
            ],
        ];
    }

    /**
     * One notification, ready to render.
     *
     * Display-ready per spec §5a: the ordinal, the date format, the points
     * label and the confirmation sentence are PHP rules, so they are settled
     * here rather than re-derived in TypeScript where they would be free to
     * disagree with the Blade card still serving unconverted pages.
     *
     * @return array<string, mixed>
     */
    private function notification(object $notification): array
    {
        $data = $notification->data;
        $place = (int) ($data['place'] ?? 0);
        $name = $data['tournament_name'] ?? __('A tournament');

        $meta = number_format((int) ($data['points'] ?? 0)).' '.__('pts');

        if (isset($data['played_on'])) {
            $meta .= ' · '.Carbon::parse($data['played_on'])->format('M j, Y');
        }

        return [
            'id' => $notification->id,
            'is_read' => $notification->read_at !== null,

            /*
             * Semantic, not stylistic. "gold", not "notification--gold".
             *
             * The rule is the one the Blade card records: places one to three
             * reuse the podium's medal tokens so the message matches the podium
             * the player just saw, and a lower scoring finish takes the accent
             * instead -- worth telling someone about without claiming a medal
             * it did not win. The default arm also catches a row this card was
             * not written for, since the store is general.
             */
            'tier' => match ($place) {
                1 => 'gold',
                2 => 'silver',
                3 => 'bronze',
                default => 'scored',
            },

            'place_label' => Number::ordinal($place),
            'title' => $name,
            'meta' => $meta,

            // Marked, not bolded. emph() writes U+2063 markers that survive as
            // data; the Vue side renders the marked run, exactly as confirm.ts
            // does for the Blade half.
            'delete_confirm' => __('Delete this notification about :tournament?', [
                'tournament' => emph($name),
            ]),
        ];
    }
}
