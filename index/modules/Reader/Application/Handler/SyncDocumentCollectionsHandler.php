<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\SyncDocumentCollections;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class SyncDocumentCollectionsHandler implements CommandHandler
{
    public function __construct(
        private readonly CollectionRepository $collections,
        private readonly DocumentRepository $documents,
    ) {}

    /** @return list<string> */
    public function handle(Command $command): array
    {
        assert($command instanceof SyncDocumentCollections);

        $document = $this->documents->findByIdForUser($command->documentId, $command->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        $desired = array_values(array_unique(array_filter(
            $command->collectionIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        foreach ($desired as $collectionId) {
            if ($this->collections->findByIdForUser($collectionId, $command->userId) === null) {
                throw ReaderDomainException::notFound('Collection not found', [
                    'collection_id' => $collectionId,
                ]);
            }
        }

        $current = $this->collections->listCollectionIdsForDocument(
            $command->documentId,
            $command->userId,
        );

        foreach (array_diff($desired, $current) as $collectionId) {
            $collection = $this->collections->findByIdForUser($collectionId, $command->userId);
            if ($collection === null) {
                continue;
            }

            $this->collections->addDocument($collection->id, $command->documentId);
            $this->bumpCollection($collection, +1);
        }

        foreach (array_diff($current, $desired) as $collectionId) {
            $collection = $this->collections->findByIdForUser($collectionId, $command->userId);
            if ($collection === null) {
                continue;
            }

            $this->collections->removeDocument($collection->id, $command->documentId);
            $this->bumpCollection($collection, -1);
        }

        return $desired;
    }

    private function bumpCollection(Collection $collection, int $delta): void
    {
        $this->collections->save(new Collection(
            id: $collection->id,
            userId: $collection->userId,
            name: $collection->name,
            revision: $collection->revision + 1,
            documentCount: max(0, $collection->documentCount + $delta),
            createdAt: $collection->createdAt,
            updatedAt: new DateTimeImmutable('now'),
        ));
    }
}
