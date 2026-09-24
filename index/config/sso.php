<?php

return [
    'code_ttl_seconds' => max(30, (int) env('SSO_CODE_TTL_SECONDS', 120)),

    /**
     * Shared secret for all satellite apps (wallets, flc, todo, …).
     * Kept in env only — never stored in DB. Clients (client_id, domain,
     * redirect URIs) live in the `sso_clients` table.
     */
    'secret' => env('SSO_SECRET'),

    /**
     * Fallback when the `todo` SSO client has no domain in DB yet.
     */
    'todo_url_fallback' => rtrim((string) env(
        'TODO_APP_URL',
        env('SSO_TODO_URL', 'https://todo.nvnhan0810.com'),
    ), '/'),
];
