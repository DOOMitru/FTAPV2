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
            : Inertia::render('Auth/VerifyEmail', [
                // A BOOLEAN, not the session value. session('status') holds
                // the sentinel 'verification-link-sent'; handing that to an
                // alert would print the word on the screen.
                'link_sent' => $request->session()->get('status') === 'verification-link-sent',
            ]);
    }
}
