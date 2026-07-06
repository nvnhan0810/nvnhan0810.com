<?php

return [
    'notification_time' => env('DIGEST_NOTIFICATION_TIME', '08:00'),
    'timezone' => env('DIGEST_TIMEZONE', config('app.timezone')),
    'articles_per_subject' => (int) env('DIGEST_ARTICLES_PER_SUBJECT', 5),
    'retrieval_candidates' => 30,
    'interest_decay_factor' => 0.98,
    'enrichment_model' => env('DIGEST_ENRICHMENT_MODEL', 'gemini-2.5-flash'),
    'ranking_model' => env('DIGEST_RANKING_MODEL', 'gemini-2.5-flash'),
    'embedding_model' => env('DIGEST_EMBEDDING_MODEL', 'text-embedding-004'),
    'embedding_dimensions' => (int) env('DIGEST_EMBEDDING_DIMENSIONS', 768),
    /** Only queue LLM enrichment for articles fetched today (digest timezone). */
    'enrich_only_fetched_today' => filter_var(env('DIGEST_ENRICH_ONLY_FETCHED_TODAY', true), FILTER_VALIDATE_BOOL),
    /** Articles per Gemini enrichment request. */
    'enrich_batch_size' => max(1, (int) env('DIGEST_ENRICH_BATCH_SIZE', 10)),
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => rtrim((string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'), '/'),
    ],
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
