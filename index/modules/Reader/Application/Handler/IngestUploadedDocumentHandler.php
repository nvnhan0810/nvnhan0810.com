<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Command\IngestUploadedDocument;
use Modules\Reader\Application\Service\DocumentIngester;
use Modules\Reader\Domain\Entities\Document;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class IngestUploadedDocumentHandler implements CommandHandler
{
    public function __construct(private readonly DocumentIngester $ingester) {}

    public function handle(Command $command): Document
    {
        assert($command instanceof IngestUploadedDocument);

        return $this->ingester->ingestFromLocalPath(
            userId: $command->userId,
            title: $command->title,
            localPath: $command->localPath,
            byteSize: $command->byteSize,
            pageCount: $command->pageCount,
            collectionIds: $command->collectionIds,
            isFavorite: $command->isFavorite,
        );
    }
}
