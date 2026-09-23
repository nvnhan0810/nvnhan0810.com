<?php

$walletsUrl = rtrim((string) env('SSO_WALLETS_URL', 'https://wallets.nvnhan0810.com'), '/');
$flcUrl = rtrim((string) env('SSO_FLC_URL', 'https://foreign.nvnhan0810.com'), '/');
$todoUrl = rtrim((string) env('SSO_TODO_URL', 'https://todo.nvnhan0810.com'), '/');

return [
    'code_ttl_seconds' => max(30, (int) env('SSO_CODE_TTL_SECONDS', 120)),

    /**
     * Shared secret for all satellite apps (wallets, flc, …).
     * Google OAuth lives only on index; apps exchange codes with this secret.
     */
    'secret' => env('SSO_SECRET'),

    /*
    | Client ids = app identity. Redirect URIs are the satellite domains
    | (wallets.* / flc.*), not path prefixes on the IdP host.
    */
    'clients' => [
        'wallets' => [
            'redirect_uris' => [
                $walletsUrl.'/auth/sso/callback',
            ],
            'redirect_uri_patterns' => [],
        ],
        'todo' => [
            'redirect_uris' => [
                $todoUrl.'/auth/sso/callback',
            ],
            'redirect_uri_patterns' => [],
        ],
        'flc-web' => [
            'redirect_uris' => [
                $flcUrl.'/auth/sso/callback',
            ],
            'redirect_uri_patterns' => [],
        ],
        'flc-admin' => [
            'redirect_uris' => [
                $flcUrl.'/admin/auth/sso/callback',
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
