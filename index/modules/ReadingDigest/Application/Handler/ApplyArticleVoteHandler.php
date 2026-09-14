<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdArticle;
use App\Models\RdDigestRunItem;
use Modules\ReadingDigest\Application\Command\ApplyArticleVote;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ApplyArticleVoteHandler implements CommandHandler
{
    public function __construct(
        private readonly RecordInteractionHandler $recordInteractionHandler,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof ApplyArticleVote);

        $item = $this->findOwnedItem($command->trackingToken, $command->userId);

        $metadata = array_filter([
            'custom_tags' => $command->customTags !== [] ? $command->customTags : null,
            'note' => $command->note,
        ]);

        $this->recordInteractionHandler->handle(
            $command->userId,
            $item->article_id,
            $command->event,
            $metadata !== [] ? $metadata : null,
            $item->subject_id,
        );

        if ($command->customTags !== []) {
            $article = RdArticle::query()->findOrFail($item->article_id);
            $existing = $article->metadata ?? [];
            $mergedTags = array_values(array_unique(array_merge($existing['user_tags'] ?? [], $command->customTags)));

            $article->update([
                'metadata' => array_merge($existing, ['user_tags' => $mergedTags]),
            ]);
        }

        return null;
    }

    private function findOwnedItem(string $token, int $userId): RdDigestRunItem
    {
        $item = RdDigestRunItem::query()
            ->where('tracking_token', $token)
            ->with(['article', 'subject', 'digestRun'])
            ->firstOrFail();

        if ((int) $item->digestRun?->user_id !== $userId) {
            throw new HttpException(403, 'Forbidden');
        }

        return $item;
    }
}
