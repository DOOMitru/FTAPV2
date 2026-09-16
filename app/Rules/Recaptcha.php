<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google reCAPTCHA v2, checked with Google.
 *
 * The token the widget puts in the form proves nothing on its own -- anybody
 * can post any string -- so it is only worth having if the server asks Google
 * whether it is real.
 */
class Recaptcha implements ValidationRule
{
    /**
     * Whether the league has keys.
     *
     * One definition, because two places ask: the controller, which only adds
     * the rule when there is a secret to check against, and the form, which
     * only draws the widget when there is a site key to draw it with. Half of
     * either would be a form nobody can submit, or a check nothing can pass.
     *
     * The consequence to be clear-eyed about: with no keys the registration
     * form has no captcha and says nothing about it. That is deliberate --
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
            $fail('Confirm you are not a robot.')->translate();

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
        }
    }
}
