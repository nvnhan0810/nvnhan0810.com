<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Command\PurgeDocument;
use Modules\Reader\Application\Service\DocumentHardDeleter;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class PurgeDocumentHandler implements CommandHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly DocumentHardDeleter $hardDeleter,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof PurgeDocument);

        $document = $this->documents->findByIdForUser(
            $command->documentId,
            $command->userId,
            includeDeleted: true,
        );

        if ($document === null || $document->deletedAt === null) {
            throw ReaderDomainException::notFound('Trashed document not found');
        }

        $this->hardDeleter->purge($document);

        return null;
    }
}
