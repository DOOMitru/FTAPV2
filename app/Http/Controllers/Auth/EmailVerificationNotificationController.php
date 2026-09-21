<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $request->user()->sendEmailVerificationNotification();

        /*
         * A sentence, not a sentinel.
         *
         * This one has to survive a redirect and be read by two different
         * screens -- the profile form and the verification prompt -- so unlike
         * the other two it genuinely is a flash. Written as the message
         * itself, so whatever draws it can just draw it.
         *
         * "your email address" rather than the prompt's old "the email address
         * you provided during registration": resending from the profile page
         * often follows changing that address, where the longer wording names
         * the wrong one.
         */
        return back()->with('status', __('A new verification link has been sent to your email address.'));
    }
}
