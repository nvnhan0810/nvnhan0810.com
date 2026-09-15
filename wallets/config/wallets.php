<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed emails (Google sign-in)
    |--------------------------------------------------------------------------
    |
    | Comma-separated in .env as WALLETS_ALLOWED_EMAILS.
    | Exact: user@gmail.com
    | Domain wildcard: *@company.com
    |
    | When WALLETS_ALLOW_ALL_EMAILS=true, every Google account is accepted (dev only).
    |
    */
    'allowed_emails' => array_values(array_filter(array_map(
        static fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('WALLETS_ALLOWED_EMAILS', ''))
    ))),

    'allow_all_emails' => (bool) env('WALLETS_ALLOW_ALL_EMAILS', false),

];
