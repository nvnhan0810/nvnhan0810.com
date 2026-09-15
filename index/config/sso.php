<?php

$publicUrl = rtrim((string) env('APP_URL', 'https://nvnhan0810.com'), '/');

return [
    'code_ttl_seconds' => max(30, (int) env('SSO_CODE_TTL_SECONDS', 120)),

    /**
     * Shared secret for all satellite apps (wallets, flc, …).
     * Google OAuth lives only on index; apps exchange codes with this secret.
     */
    'secret' => env('SSO_SECRET'),

    /*
    | Client ids = app identity. Redirect URIs are derived from APP_URL + path.
    | Override only via config if you need extra URIs (not env lists).
    */
    'clients' => [
        'wallets' => [
            'redirect_uris' => [
                $publicUrl.'/wallets/auth/sso/callback',
            ],
            'redirect_uri_patterns' => [],
        ],
        'flc-web' => [
            'redirect_uris' => [
                $publicUrl.'/flc/auth/sso/callback',
            ],
            'redirect_uri_patterns' => [],
        ],
        'flc-admin' => [
            'redirect_uris' => [
                $publicUrl.'/flc/admin/auth/sso/callback',
            ],
            'redirect_uri_patterns' => [],
        ],
        'flc-mobile' => [
            'redirect_uris' => [],
            'redirect_uri_patterns' => [
                '/^flc:\/\/oauth-callback(\/|\?|$)/',
                '/^https:\/\/[a-z0-9-]+\.chromiumapp\.org(\/|\?|$)/',
            ],
        ],
    ],
];
