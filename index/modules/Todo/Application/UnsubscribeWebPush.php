<?php

namespace Modules\Todo\Application;

use Modules\Todo\Domain\Ports\WebPushSubscriptionRepository;

final class UnsubscribeWebPush
{
    public function __construct(
        private readonly WebPushSubscriptionRepository $repository,
    ) {}

    public function execute(int $userId, string $endpoint): void
    {
        $this->repository->deleteByEndpoint($userId, $endpoint);
    }
}
