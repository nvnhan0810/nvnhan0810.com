<?php

return [
    'cursor_api_key' => env('CURSOR_API_KEY'),
    'api_token' => env('POST_AGENT_API_TOKEN'),
    'model' => env('POST_AGENT_MODEL', 'auto'),
    'timeout' => (int) env('POST_AGENT_TIMEOUT', 120),
];
