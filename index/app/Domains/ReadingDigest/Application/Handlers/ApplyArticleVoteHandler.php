<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Domain\Enums\InteractionEvent;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunItemModel;

class ApplyArticleVoteHandler
{
    public function __construct(
        private readonly RecordInteractionHandler $recordInteractionHandler,
    ) {}

    public function handle(
        DigestRunItemModel $item,
        int $userId,
        InteractionEvent $event,
        array $customTags = [],
        ?string $note = null,
    ): void {
        $metadata = array_filter([
            'custom_tags' => $customTags !== [] ? $customTags : null,
            'note' => $note,
        ]);

        $this->recordInteractionHandler->handle(
            $userId,
            $item->article_id,
            $event,
            $metadata !== [] ? $metadata : null,
            $item->subject_id,
        );

        if ($customTags !== []) {
            $article = DigestArticleModel::query()->findOrFail($item->article_id);
            $existing = $article->metadata ?? [];
            $mergedTags = array_values(array_unique(array_merge($existing['user_tags'] ?? [], $customTags)));

            $article->update([
                'metadata' => array_merge($existing, ['user_tags' => $mergedTags]),
            ]);
        }
    }
}
