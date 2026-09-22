<?php

namespace Modules\Todo\Domain;

final class WebPushSubscription
{
    public function __construct(
        public readonly int $userId,
        public readonly string $endpoint,
        public readonly string $publicKey,
        public readonly string $authToken,
        public readonly string $contentEncoding,
        public readonly ?string $userAgent,
        public readonly ?\DateTimeInterface $lastFocusedAt,
    ) {}
}
