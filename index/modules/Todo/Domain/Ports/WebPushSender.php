<?php

namespace Modules\Todo\Domain\Ports;

use Modules\Todo\Domain\WebPushSubscription;

interface WebPushSender
{
    /**
     * @param  array{title: string, body: string, url?: string, tag?: string, icon?: string}  $payload
     * @return bool false when the endpoint is gone (caller should delete subscription)
     */
    public function send(WebPushSubscription $subscription, array $payload): bool;

    public function isConfigured(): bool;
}
