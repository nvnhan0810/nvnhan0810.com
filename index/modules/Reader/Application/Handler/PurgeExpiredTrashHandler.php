<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Reader\Application\Command\PurgeExpiredTrash;
use Modules\Reader\Application\Service\DocumentHardDeleter;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class PurgeExpiredTrashHandler implements CommandHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly DocumentHardDeleter $hardDeleter,
    ) {}

    /** @return array{purged: int, retention_days: int, cutoff: string} */
    public function handle(Command $command): array
    {
        assert($command instanceof PurgeExpiredTrash);

        $retentionDays = max(1, (int) config('reader.trash_retention_days', 60));
        $now = $command->now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $cutoff = $now->setTimezone(new DateTimeZone('UTC'))->modify("-{$retentionDays} days");

        $purged = 0;
        foreach ($this->documents->listTrashDeletedBefore($cutoff) as $document) {
            $this->hardDeleter->purge($document);
            $purged++;
        }

        return [
            'purged' => $purged,
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
