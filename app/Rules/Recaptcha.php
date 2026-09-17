<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google reCAPTCHA v3, checked with Google.
 *
 * v3 asks the visitor for nothing. Google watches the session and returns a
 * score from 0.0 to 1.0, and the site decides where to draw the line -- so
 * unlike v2 there is no pass or fail to read off, and a legitimate person CAN
 * be scored low. The threshold is configuration for that reason: it is a
 * judgement about this league's traffic, not a constant.
 *
 * The token proves nothing on its own -- anything can post a string -- so it
 * is only worth having because the server asks Google what the token is worth.
 */
class Recaptcha implements ValidationRule
{
    /**
     * @param  string  $action  the action this form claims to be
     */
    public function __construct(private readonly string $action) {}

    /**
     * Whether the league has keys.
     *
     * One definition, because two places ask: the controller, which only adds
     * the rule when there is a secret to check against, and the form, which
     * only fetches a token when there is a site key to fetch it with. Half of
     * either would be a form nobody can submit, or a check nothing can pass.
     *
     * The consequence to be clear-eyed about: with no keys the registration
     * form asks Google nothing and says nothing about it. That is deliberate --
     * local work and the test suite must not depend on Google -- but it does
     * mean production is protected exactly as far as its env file says.
     */
    public static function configured(): bool
    {
        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! filled($value)) {
            // Reached when the script was blocked, or Google hung, or the
            // browser runs no JavaScript at all: recaptcha.ts submits with an
            // empty token rather than leaving a button that does nothing.
            $fail('We could not check that just now. Please try again.')->translate();

            return;
        }

        try {
            // A short timeout on purpose: this sits between a person and their
            // account, and a hung request is a form that appears to do nothing.
            $response = Http::asForm()->timeout(5)->post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'secret' => config('services.recaptcha.secret'),
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]
            );
        } catch (ConnectionException) {
            // Closed, not open. An unreachable Google means nobody can sign up
            // for a few minutes; failing open would mean anything can, and
            // there would be nothing in the record to say which minutes those
            // were. The message asks for a retry, which is the actual remedy.
            $fail('We could not check that just now. Please try again.')->translate();

            return;
        }

        if ($response->json('success') !== true) {
            $fail('That did not confirm you are a person. Please try again.')->translate();

            return;
        }

        // The action the token was minted for. Without this a token issued by
        // any other form on any other page of the site would be spendable
        // here, which is most of what makes a v3 token worth checking at all.
        if ($response->json('action') !== $this->action) {
            $fail('That did not confirm you are a person. Please try again.')->translate();

            return;
        }

        // Cast, not compared raw: the API returns a JSON number and PHP will
        // happily compare a string to a float, but a null score -- which is
        // what a malformed response gives -- casts to 0.0 and fails, where a
        // loose comparison could let it through.
        if ((float) $response->json('score') < (float) config('services.recaptcha.threshold')) {
            $fail('That did not confirm you are a person. Please try again.')->translate();
        }
    }
}
