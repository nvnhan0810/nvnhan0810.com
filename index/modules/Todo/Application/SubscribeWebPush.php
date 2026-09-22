<?php

namespace Modules\Todo\Application;

use Modules\Todo\Domain\Ports\WebPushSubscriptionRepository;
use Modules\Todo\Domain\WebPushSubscription;

final class SubscribeWebPush
{
    public function __construct(
        private readonly WebPushSubscriptionRepository $repository,
    ) {}

    public function execute(
        int $userId,
        string $endpoint,
        string $publicKey,
        string $authToken,
        string $contentEncoding = 'aesgcm',
        ?string $userAgent = null,
    ): void {
        $this->repository->upsert(new WebPushSubscription(
            userId: $userId,
            endpoint: $endpoint,
            publicKey: $publicKey,
            authToken: $authToken,
            contentEncoding: $contentEncoding !== '' ? $contentEncoding : 'aesgcm',
            userAgent: $userAgent,
            lastFocusedAt: null,
        ));
    }
}
