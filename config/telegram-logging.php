<?php

return [

    'enabled' => env('TELEGRAM_LOG_ENABLED', true),

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'chat_id' => env('TELEGRAM_CHAT_ID'),

    /** Minimum Monolog level for this channel (warning, error, …). */
    'level' => env('TELEGRAM_LOG_LEVEL', 'warning'),

    /** HTTP timeout (seconds) when calling Telegram Bot API. */
    'timeout' => (int) env('TELEGRAM_LOG_TIMEOUT', 5),

    /** Skip duplicate level+message within this window (seconds). 0 = off. */
    'dedupe_seconds' => (int) env('TELEGRAM_LOG_DEDUPE_SECONDS', 60),

    /** Max length for {%context%} JSON before truncation. */
    'context_max_length' => (int) env('TELEGRAM_LOG_CONTEXT_MAX_LENGTH', 800),

    'queue' => [
        'enabled' => env('TELEGRAM_LOG_QUEUE', false),
        'connection' => env('TELEGRAM_LOG_QUEUE_CONNECTION'),
        'name' => env('TELEGRAM_LOG_QUEUE_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message template (Telegram HTML parse_mode)
    |--------------------------------------------------------------------------
    |
    | Placeholders:
    |   {%emoji%} {%level%} {%level_lower%} {%message%} {%context%}
    |   {%context_block%} — <pre>…</pre> or empty when no context
    |   {%app_name%} {%app_env%} {%channel%} {%datetime%}
    |   {%config:app.url%} — any config('app.url') value (HTML-escaped)
    |
    */
    'template' => <<<'HTML'
{%emoji%} <b>{%level%}</b> — {%app_name%} ({%app_env%})
<code>{%message%}</code>
{%context_block%}
HTML,

];
