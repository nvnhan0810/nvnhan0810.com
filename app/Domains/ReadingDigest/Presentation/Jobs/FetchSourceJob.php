<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\FetchSourceHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $sourceId) {}

    public function handle(FetchSourceHandler $handler): void
    {
        $limit = (int) config('reading-digest.fetch_limit_per_source', 50);
        $since = now()->subHours((int) config('reading-digest.fetch_since_hours', 24));

        $result = $handler->handle($this->sourceId, $limit, $since);

        if ($result['article_ids'] !== []) {
            BatchEnrichArticleMetadataJob::dispatch($result['article_ids']);
        }
    }
}
