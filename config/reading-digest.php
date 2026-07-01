<?php

return [
    'notification_time' => env('DIGEST_NOTIFICATION_TIME', '08:00'),
    'timezone' => env('DIGEST_TIMEZONE', config('app.timezone')),
    'articles_per_subject' => (int) env('DIGEST_ARTICLES_PER_SUBJECT', 5),
    'retrieval_candidates' => 30,
    'interest_decay_factor' => 0.98,
    'embedding_model' => env('DIGEST_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_dimensions' => (int) env('DIGEST_EMBEDDING_DIMENSIONS', 1536),
    'ranking_model' => env('DIGEST_RANKING_MODEL', 'composer-2.5'),
    /** cloud_agents = Cursor Cloud Agents API; proxy = local OpenAI-compatible proxy (e.g. cursor-brain). */
    'llm_driver' => env('DIGEST_LLM_DRIVER', 'cloud_agents'),
    'cursor_api_key' => env('CURSOR_API_KEY'),
    'cursor_api_base_url' => env('CURSOR_API_BASE_URL', 'http://127.0.0.1:3001/v1'),
    /** Public site URL for Telegram button links (must be HTTPS in production, not localhost). */
    'public_url' => env('DIGEST_PUBLIC_URL', env('APP_URL', 'http://localhost')),
    'table_prefix' => 'rd_',
    'queue' => env('DIGEST_QUEUE', 'default'),
    'telegram' => [
        'enabled' => filter_var(env('DIGEST_TELEGRAM_ENABLED', false), FILTER_VALIDATE_BOOL),
        'bot_token' => env('DIGEST_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('DIGEST_TELEGRAM_CHAT_ID'),
        /** Shared secret validated on incoming Telegram webhook calls (vote callbacks). */
        'webhook_secret' => env('DIGEST_TELEGRAM_WEBHOOK_SECRET'),
    ],
    'interaction_weights' => [
        'impression' => -0.5,
        'opened' => 1,
        'finished_reading' => 3,
        'saved' => 5,
        'liked' => 5,
        'disliked' => -5,
        'shared' => 8,
        'dismissed' => -5,
        'rated' => 0,
        'rated_positive' => 4,
        'rated_negative' => -6,
    ],
    'content_retention_days' => (int) env('DIGEST_CONTENT_RETENTION_DAYS', 90),
    'fetch_limit_per_source' => (int) env('DIGEST_FETCH_LIMIT_PER_SOURCE', 50),
    /** Only store articles published/fetched within this window during daily fetch. */
    'fetch_since_hours' => (int) env('DIGEST_FETCH_SINCE_HOURS', 24),
    /** ISO 639-1 codes kept in the digest pipeline (fetch, inbox, ranking). */
    'allowed_languages' => ['en', 'vi'],
];
