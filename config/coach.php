<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local auto-login
    |--------------------------------------------------------------------------
    |
    | When enabled, every request is signed in as a single local coach account
    | (created on first use), so the app needs no login. Only enable this for a
    | private, local installation.
    |
    */

    // Never honoured in production: a public site must not sign everyone in as one account.
    'auto_login' => (bool) env('COACH_AUTO_LOGIN', false) && env('APP_ENV') !== 'production',

    'email' => env('COACH_EMAIL', 'coach@localhost'),

    /*
    |--------------------------------------------------------------------------
    | Who can register
    |--------------------------------------------------------------------------
    |
    | "invite": new accounts need an open team invitation sent to their email
    | address (the very first account on a fresh install is always allowed).
    | "open": anyone who can reach the site can create an account.
    |
    */

    'registration' => env('COACH_REGISTRATION', 'invite'),

    /*
    |--------------------------------------------------------------------------
    | Email verification
    |--------------------------------------------------------------------------
    |
    | New accounts are trusted by default, since the app usually runs without
    | outgoing mail. Turn this on once a mailer is configured to require every
    | new user to confirm their address.
    |
    */

    'verify_email' => (bool) env('COACH_VERIFY_EMAIL', false),

    /*
    |--------------------------------------------------------------------------
    | PDF downloads
    |--------------------------------------------------------------------------
    |
    | PDFs are produced by printing the print pages with a headless Chrome or
    | Chromium. Leave this empty to look for one automatically (a system
    | install, or Playwright's browser cache).
    |
    */

    'chrome_path' => env('COACH_CHROME_PATH'),

];
