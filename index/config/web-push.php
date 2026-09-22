<?php

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@nvnhan0810.com'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    /** Skip push for a device if it reported Matrix focus within this window (must exceed heartbeat) */
    'focus_ttl_seconds' => (int) env('WEB_PUSH_FOCUS_TTL_SECONDS', 30),

    /** Default deep-link when notification is clicked */
    'matrix_url' => '/matrix',
];
