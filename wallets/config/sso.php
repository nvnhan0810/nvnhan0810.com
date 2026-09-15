<?php

$base = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$prefix = trim((string) env('APP_PATH_PREFIX', 'wallets'), '/');

return [
    'idp_url' => rtrim((string) env('SSO_IDP_URL', $base !== '' ? $base : 'https://nvnhan0810.com'), '/'),
    /** Same as APP_PATH_PREFIX (wallets). */
    'client_id' => $prefix !== '' ? $prefix : 'wallets',
    'secret' => env('SSO_SECRET'),
    'redirect_uri' => $prefix === ''
        ? $base.'/auth/sso/callback'
        : $base.'/'.$prefix.'/auth/sso/callback',
];
