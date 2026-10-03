<?php

namespace App\Console\Commands\ReadingDigest;

use App\Jobs\ReadingDigest\RebuildUserEmbeddingJob;
use Illuminate\Console\Command;

class RebuildUserEmbeddingsCommand extends Command
{
    protected $signature = 'reading-digest:rebuild-embeddings';

    protected $description = 'Dispatch RebuildUserEmbeddingJob (all users) to the queue';

    public function handle(): int
    {
        RebuildUserEmbeddingJob::dispatch();
        $this->info('Dispatched RebuildUserEmbeddingJob.');

        return self::SUCCESS;
    }
}
