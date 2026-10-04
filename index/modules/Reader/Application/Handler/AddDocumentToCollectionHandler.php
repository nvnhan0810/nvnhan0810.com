<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\AddDocumentToCollection;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class AddDocumentToCollectionHandler implements CommandHandler
{
    public function __construct(
        private readonly CollectionRepository $collections,
        private readonly DocumentRepository $documents,
    ) {}

    public function handle(Command $command): Collection
    {
        assert($command instanceof AddDocumentToCollection);

        $collection = $this->collections->findByIdForUser($command->collectionId, $command->userId);
        if ($collection === null) {
            throw ReaderDomainException::notFound('Collection not found');
        }

        $document = $this->documents->findByIdForUser($command->documentId, $command->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        if (! $this->collections->hasDocument($collection->id, $document->id)) {
            $this->collections->addDocument($collection->id, $document->id);

            return $this->collections->save(new Collection(
                id: $collection->id,
                userId: $collection->userId,
                name: $collection->name,
                revision: $collection->revision + 1,
                documentCount: $collection->documentCount + 1,
                createdAt: $collection->createdAt,
                updatedAt: new DateTimeImmutable('now'),
            ));
        }

        return $collection;
    }
}
