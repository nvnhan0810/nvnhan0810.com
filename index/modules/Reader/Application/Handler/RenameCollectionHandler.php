<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use Modules\Reader\Application\Command\RenameCollection;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class RenameCollectionHandler implements CommandHandler
{
    public function __construct(private readonly CollectionRepository $collections) {}

    public function handle(Command $command): Collection
    {
        assert($command instanceof RenameCollection);

        $name = trim($command->name);
        if ($name === '') {
            throw ReaderDomainException::validation('name is required');
        }

        $collection = $this->collections->findByIdForUser($command->collectionId, $command->userId);
        if ($collection === null) {
            throw ReaderDomainException::notFound('Collection not found');
        }

        return $this->collections->save(new Collection(
            id: $collection->id,
            userId: $collection->userId,
            name: $name,
            revision: $collection->revision + 1,
            documentCount: $collection->documentCount,
            createdAt: $collection->createdAt,
            updatedAt: new DateTimeImmutable('now'),
        ));
    }
}
