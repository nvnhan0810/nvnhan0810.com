<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Command\DeleteCollection;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class DeleteCollectionHandler implements CommandHandler
{
    public function __construct(private readonly CollectionRepository $collections) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof DeleteCollection);

        $collection = $this->collections->findByIdForUser($command->collectionId, $command->userId);
        if ($collection === null) {
            throw ReaderDomainException::notFound('Collection not found');
        }

        // Pivot rows cascade; documents themselves stay.
        $this->collections->delete($command->collectionId, $command->userId);

        return null;
    }
}
