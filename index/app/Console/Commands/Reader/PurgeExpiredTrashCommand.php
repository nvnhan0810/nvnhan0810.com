<?php

declare(strict_types=1);

namespace App\Console\Commands\Reader;

use Illuminate\Console\Command;
use Modules\Reader\Application\Command\PurgeExpiredTrash;
use Modules\Shared\Application\CommandBus;

final class PurgeExpiredTrashCommand extends Command
{
    protected $signature = 'reader:purge-expired-trash';

    protected $description = 'Hard-delete Reader trash items older than retention (default 60 days)';

    public function handle(CommandBus $commandBus): int
    {
        /** @var array{purged: int, retention_days: int, cutoff: string} $result */
        $result = $commandBus->dispatch(new PurgeExpiredTrash);

        $this->info(sprintf(
            'Purged %d trashed document(s) older than %d days (cutoff %s).',
            $result['purged'],
            $result['retention_days'],
            $result['cutoff'],
        ));

        return self::SUCCESS;
    }
}
