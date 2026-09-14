<?php

namespace App\Http\Controllers;

use App\Mail\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * What a sender is told, whether or not anything was sent.
     *
     * One constant, because the honeypot's whole trick is that its answer is
     * indistinguishable from a real one. Two copies of the sentence are two
     * chances for them to drift apart, and the day they do is the day the
     * honeypot starts announcing itself.
     */
    private const ACKNOWLEDGEMENT = 'Thanks — your message is on its way.';

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a field no person sees and no person fills in. Answer
        // exactly as we would a real submission so bots learn nothing.
        if ($request->filled('company')) {
            return $this->acknowledge();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'topic' => 'required|in:general,registration,partnership,support,sponsorship',
            'message' => 'required|string|max:5000',
        ]);

        Mail::to(config('mail.league_contact'))->send(new ContactSubmission(
            senderName: $validated['name'],
            senderEmail: $validated['email'],
            topic: $validated['topic'],
            body: $validated['message'],
        ));

        return $this->acknowledge();
    }

    private function acknowledge(): RedirectResponse
    {
        return back()->with('status', __(self::ACKNOWLEDGEMENT));
    }
}
