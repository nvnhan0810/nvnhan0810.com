<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Command\ImportDocumentFromUrl;
use Modules\Reader\Application\Service\DocumentIngester;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Ports\RemotePdfFetcher;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class ImportDocumentFromUrlHandler implements CommandHandler
{
    public function __construct(
        private readonly RemotePdfFetcher $fetcher,
        private readonly DocumentIngester $ingester,
    ) {}

    public function handle(Command $command): Document
    {
        assert($command instanceof ImportDocumentFromUrl);

        $fetched = $this->fetcher->fetch($command->url);
        $title = trim($command->title);
        if ($title === '') {
            $title = $fetched->suggestedTitle ?? 'Untitled PDF';
        }

        return $this->ingester->ingest(
            userId: $command->userId,
            title: $title,
            contents: $fetched->contents,
            pageCount: $command->pageCount,
            collectionIds: $command->collectionIds,
            isFavorite: $command->isFavorite,
        );
    }
}
