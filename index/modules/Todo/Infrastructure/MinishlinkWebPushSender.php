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
    private const TOPIC_MAX_LENGTH = 32;

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
            $bodyPayload = $payload;
            unset($bodyPayload['topic']);

            $options = [
                'urgency' => 'high',
                'TTL' => 60 * 30,
            ];

            $topic = $this->sanitizeTopic($payload['topic'] ?? null);
            if ($topic !== null) {
                $options['topic'] = $topic;
            }

            $report = $webPush->sendOneNotification(
                $sub,
                json_encode($bodyPayload, JSON_THROW_ON_ERROR),
                $options,
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
        $response = $report->getResponse();
        $body = $response !== null ? (string) $response->getBody() : '';

        Log::warning('web-push.send.failed', [
            'endpoint_host' => parse_url($subscription->endpoint, PHP_URL_HOST),
            'reason' => $reason,
            'response_body' => $body !== '' ? mb_substr($body, 0, 500) : null,
            'topic' => $options['topic'] ?? null,
        ]);

        // Gone / expired subscription
        if (str_contains($reason, '410') || str_contains($reason, '404')) {
            return false;
        }

        return true;
    }

    /**
     * Apple rejects Topic outside 1–32 chars of [A-Za-z0-9_-] (BadWebPushTopic → 400).
     */
    private function sanitizeTopic(mixed $topic): ?string
    {
        if (! is_string($topic) || $topic === '') {
            return null;
        }

        $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $topic) ?? '';
        if ($safe === '') {
            return null;
        }

        return substr($safe, 0, self::TOPIC_MAX_LENGTH);
    }
}
