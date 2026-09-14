<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdDigestRunItem;
use Modules\ReadingDigest\Application\Command\RecordArticleOpened;
use Modules\ReadingDigest\Domain\Enums\InteractionEvent;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RecordArticleOpenedHandler implements CommandHandler
{
    public function __construct(
        private readonly RecordInteractionHandler $recordInteractionHandler,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof RecordArticleOpened);

        $item = RdDigestRunItem::query()
            ->where('tracking_token', $command->trackingToken)
            ->with(['article', 'digestRun'])
            ->firstOrFail();

        if ((int) $item->digestRun?->user_id !== $command->userId) {
            throw new HttpException(403, 'Forbidden');
        }

        $url = $item->article?->url;
        if (! is_string($url) || $url === '') {
            throw new HttpException(404, 'Article URL not found');
        }

        try {
            $this->recordInteractionHandler->handle(
                $command->userId,
                $item->article_id,
                InteractionEvent::Opened,
                null,
                $item->subject_id,
            );
        } catch (\Throwable) {
            // Counting must not block the redirect.
        }

        return $url;
    }
}
