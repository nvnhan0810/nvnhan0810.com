<?php

$base = rtrim((string) env('APP_URL', 'https://wallets.nvnhan0810.com'), '/');

return [
    'idp_url' => rtrim((string) env('SSO_IDP_URL', 'https://nvnhan0810.com'), '/'),
    'client_id' => env('SSO_CLIENT_ID', 'wallets'),
    'secret' => env('SSO_SECRET'),
    'redirect_uri' => env('SSO_REDIRECT_URI', $base.'/auth/sso/callback'),
];
