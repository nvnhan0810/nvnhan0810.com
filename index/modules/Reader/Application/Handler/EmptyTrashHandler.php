<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Command\EmptyTrash;
use Modules\Reader\Application\Service\DocumentHardDeleter;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class EmptyTrashHandler implements CommandHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly DocumentHardDeleter $hardDeleter,
    ) {}

    /** @return array{purged: int} */
    public function handle(Command $command): array
    {
        assert($command instanceof EmptyTrash);

        $purged = 0;
        do {
            $batch = $this->documents->listTrashForUser($command->userId, 100, null);
            foreach ($batch as $document) {
                $this->hardDeleter->purge($document);
                $purged++;
            }
        } while ($batch !== []);

        return ['purged' => $purged];
    }
}
