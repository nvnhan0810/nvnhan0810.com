<?php

namespace Modules\Todo\Infrastructure;

use App\Models\WebPushSubscription as WebPushSubscriptionModel;
use DateTimeInterface;
use Modules\Todo\Domain\Ports\WebPushSubscriptionRepository;
use Modules\Todo\Domain\WebPushSubscription;

final class EloquentWebPushSubscriptionRepository implements WebPushSubscriptionRepository
{
    public function upsert(WebPushSubscription $subscription): void
    {
        WebPushSubscriptionModel::query()->updateOrCreate(
            [
                'user_id' => $subscription->userId,
                'endpoint_hash' => hash('sha256', $subscription->endpoint),
            ],
            [
                'endpoint' => $subscription->endpoint,
                'public_key' => $subscription->publicKey,
                'auth_token' => $subscription->authToken,
                'content_encoding' => $subscription->contentEncoding,
                'user_agent' => $subscription->userAgent,
                'last_focused_at' => $subscription->lastFocusedAt,
            ],
        );
    }

    public function deleteByEndpoint(int $userId, string $endpoint): void
    {
        WebPushSubscriptionModel::query()
            ->where('user_id', $userId)
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete();
    }

    public function deleteByEndpointOnly(string $endpoint): void
    {
        WebPushSubscriptionModel::query()
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete();
    }

    public function markFocused(int $userId, string $endpoint, bool $focused): void
    {
        $query = WebPushSubscriptionModel::query()
            ->where('user_id', $userId)
            ->where('endpoint_hash', hash('sha256', $endpoint));

        if ($focused) {
            $query->update(['last_focused_at' => now()]);
        } else {
            $query->update(['last_focused_at' => null]);
        }
    }

    public function listByUserId(int $userId): array
    {
        return WebPushSubscriptionModel::query()
            ->where('user_id', $userId)
            ->get()
            ->map(fn (WebPushSubscriptionModel $row): WebPushSubscription => $this->toDomain($row))
            ->all();
    }

    private function toDomain(WebPushSubscriptionModel $row): WebPushSubscription
    {
        /** @var DateTimeInterface|null $focused */
        $focused = $row->last_focused_at;

        return new WebPushSubscription(
            userId: (int) $row->user_id,
            endpoint: (string) $row->endpoint,
            publicKey: (string) $row->public_key,
            authToken: (string) $row->auth_token,
            contentEncoding: (string) $row->content_encoding,
            userAgent: $row->user_agent !== null ? (string) $row->user_agent : null,
            lastFocusedAt: $focused,
        );
    }
}
