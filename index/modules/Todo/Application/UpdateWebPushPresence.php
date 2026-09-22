<?php

namespace Modules\Todo\Application;

use Modules\Todo\Domain\Ports\WebPushSubscriptionRepository;

final class UpdateWebPushPresence
{
    public function __construct(
        private readonly WebPushSubscriptionRepository $repository,
    ) {}

    public function execute(int $userId, string $endpoint, bool $focused): void
    {
        $this->repository->markFocused($userId, $endpoint, $focused);
    }
}
