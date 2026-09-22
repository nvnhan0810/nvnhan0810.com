<?php

namespace Modules\Todo\Infrastructure;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Modules\Todo\Domain\Ports\WebPushSender;
use Modules\Todo\Domain\WebPushSubscription;
use Throwable;
use Illuminate\Support\Facades\Log;

final class MinishlinkWebPushSender implements WebPushSender
{
    public function isConfigured(): bool
    {
        $public = config('web-push.vapid.public_key');
        $private = config('web-push.vapid.private_key');

        return is_string($public) && $public !== ''
            && is_string($private) && $private !== '';
    }

    public function send(WebPushSubscription $subscription, array $payload): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        $auth = [
            'VAPID' => [
                'subject' => (string) config('web-push.vapid.subject'),
                'publicKey' => (string) config('web-push.vapid.public_key'),
                'privateKey' => (string) config('web-push.vapid.private_key'),
            ],
        ];

        $webPush = new WebPush($auth);
        $webPush->setReuseVAPIDHeaders(true);

        $sub = Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->publicKey,
            'authToken' => $subscription->authToken,
            'contentEncoding' => $subscription->contentEncoding,
        ]);

        try {
            $report = $webPush->sendOneNotification(
                $sub,
                json_encode($payload, JSON_THROW_ON_ERROR),
            );
        } catch (Throwable $e) {
            Log::warning('web-push.send.exception', [
                'endpoint_host' => parse_url($subscription->endpoint, PHP_URL_HOST),
                'message' => $e->getMessage(),
            ]);

            return true;
        }

        if ($report->isSuccess()) {
            return true;
        }

        $reason = $report->getReason();
        Log::warning('web-push.send.failed', [
            'endpoint_host' => parse_url($subscription->endpoint, PHP_URL_HOST),
            'reason' => $reason,
        ]);

        // Gone / expired subscription
        if (str_contains($reason, '410') || str_contains($reason, '404')) {
            return false;
        }

        return true;
    }
}
