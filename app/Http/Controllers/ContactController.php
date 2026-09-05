<?php

namespace App\Http\Controllers;

use App\Mail\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a field no person sees and no person fills in. Answer
        // exactly as we would a real submission so bots learn nothing.
        if ($request->filled('company')) {
            return back()->with('status', 'Thanks — your message is on its way.');
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

        return back()->with('status', 'Thanks — your message is on its way.');
    }
}
