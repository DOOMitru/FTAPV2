<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        // Named fields, not the model. What a page is given is what ends up in
        // the page source, and the model carries a password hash and a
        // remember token that this screen has no use for.
        return Inertia::render('Profile/Edit', [
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'nickname' => $user->nickname,
                'email' => $user->email,
                // The decision, not the ingredients -- spec §5a. Whether a
                // reminder is due depends on the model implementing
                // MustVerifyEmail as well as on the timestamp, and that is a
                // PHP question with no business being asked again in Vue.
                'needs_email_verification' => $user instanceof MustVerifyEmail
                    && ! $user->hasVerifiedEmail(),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Only allow admins to update the is_admin status
        if (!$request->user()->is_admin) {
            unset($data['is_admin']);
        }

        $request->user()->fill($data);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        /*
         * No flash.
         *
         * This used to set the sentinel 'profile-updated' -- a string the form
         * MATCHED on to show "Saved." beside its button, never a sentence
         * anybody read. useForm's recentlySuccessful says the same thing
         * directly and per-form, for the same two seconds, so the page-wide
         * value is not needed and nothing else on the page can trip over it.
         *
         * A banner as well would say a second time what the indicator beside
         * the button already said, about the one of three forms on this screen
         * that the banner could not identify.
         */
        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
