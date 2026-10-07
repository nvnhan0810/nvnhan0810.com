<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Command\CreateCollection;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;
use Ramsey\Uuid\Uuid;

final class CreateCollectionHandler implements CommandHandler
{
    public function __construct(private readonly CollectionRepository $collections) {}

    public function handle(Command $command): Collection
    {
        assert($command instanceof CreateCollection);

        $name = trim($command->name);
        if ($name === '') {
            throw ReaderDomainException::validation('name is required');
        }

        $id = $command->id ?? Uuid::uuid4()->toString();
        if (! Uuid::isValid($id)) {
            throw ReaderDomainException::validation('Invalid collection id');
        }

        if ($this->collections->findByIdForUser($id, $command->userId) !== null) {
            throw ReaderDomainException::conflict('Collection already exists', [
                'collection_id' => $id,
            ]);
        }

        return $this->collections->create($id, $command->userId, $name);
    }
}
