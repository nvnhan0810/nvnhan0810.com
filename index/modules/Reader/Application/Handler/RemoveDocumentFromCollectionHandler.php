<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\RemoveDocumentFromCollection;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class RemoveDocumentFromCollectionHandler implements CommandHandler
{
    public function __construct(private readonly CollectionRepository $collections) {}

    public function handle(Command $command): Collection
    {
        assert($command instanceof RemoveDocumentFromCollection);

        $collection = $this->collections->findByIdForUser($command->collectionId, $command->userId);
        if ($collection === null) {
            throw ReaderDomainException::notFound('Collection not found');
        }

        if (! $this->collections->hasDocument($collection->id, $command->documentId)) {
            return $collection;
        }

        $this->collections->removeDocument($collection->id, $command->documentId);

        return $this->collections->save(new Collection(
            id: $collection->id,
            userId: $collection->userId,
            name: $collection->name,
            revision: $collection->revision + 1,
            documentCount: max(0, $collection->documentCount - 1),
            createdAt: $collection->createdAt,
            updatedAt: new DateTimeImmutable('now'),
        ));
    }
}
