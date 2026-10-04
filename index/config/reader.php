<?php

declare(strict_types=1);

return [
    // Same-host IdP (mobile → nvnhan0810.com); override only if Reader talks to another IdP.
    'sso_idp_url' => env('SSO_IDP_URL', env('APP_URL')),
    'sso_secret' => env('SSO_SECRET'),
    'sso_client_id' => env('READER_SSO_CLIENT_ID', 'apple-reader'),

    // Laravel filesystems disk (SeaweedFS = S3 API → use "s3")
    'disk' => env('READER_FILESYSTEM_DISK', 's3'),

    'max_pdf_bytes' => (int) env('READER_MAX_PDF_BYTES', 200 * 1024 * 1024),
    'max_drawing_bytes' => (int) env('READER_MAX_DRAWING_BYTES', 5 * 1024 * 1024),
    'signed_url_ttl_seconds' => (int) env('READER_SIGNED_URL_TTL_SECONDS', 900),

    // Soft-deleted docs auto hard-purged after this many days (reader:purge-expired-trash).
    'trash_retention_days' => (int) env('READER_TRASH_RETENTION_DAYS', 60),
];
