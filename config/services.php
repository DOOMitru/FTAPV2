<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /*
     * Google reCAPTCHA v3 on the registration form. Both keys come from the
     * same pair in Google's console: the site key is public and reaches the
     * browser, the secret never leaves here.
     *
     * threshold is where this league draws the line on Google's 0.0-1.0 score.
     * 0.5 is Google's own suggested starting point. Raising it turns away more
     * bots AND more people, and a person turned away by v3 has nothing to
     * click to prove otherwise -- so move it on evidence, not on nerves.
     *
     * Absent keys, the form fetches no token and the rule is not applied --
     * see App\Rules\Recaptcha::configured().
     */
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret' => env('RECAPTCHA_SECRET'),
        'threshold' => env('RECAPTCHA_THRESHOLD', 0.5),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
