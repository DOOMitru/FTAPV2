<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->intended(route('dashboard', absolute: false))
            // No props. The page used to be sent a link_sent boolean because
            // session('status') held a sentinel it could not print; the flash
            // is the sentence now, and the page draws the shared value.
            : Inertia::render('Auth/VerifyEmail');
    }
}
