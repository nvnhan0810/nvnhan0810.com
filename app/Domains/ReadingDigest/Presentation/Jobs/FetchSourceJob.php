<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\FetchSourceHandler;
use App\Domains\ReadingDigest\Domain\Services\SourceFetchLimitCalculator;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $sourceId) {}

    public function handle(FetchSourceHandler $handler): void
    {
        $source = SourceModel::query()->with('subjects')->findOrFail($this->sourceId);
        $limit = SourceFetchLimitCalculator::forSource($source);
        $since = now()->subHours((int) config('reading-digest.fetch_since_hours', 24));

        Log::info('Reading digest source fetch limit', [
            'source_id' => $source->id,
            'source_name' => $source->name,
            'limit' => $limit,
            'enabled_subjects' => $source->subjects->where('enabled', true)->pluck('name')->values()->all(),
        ]);

        $result = $handler->handle($this->sourceId, $limit, $since);

        if ($result['article_ids'] !== []) {
            BatchEnrichArticleMetadataJob::dispatch($result['article_ids']);
        }
    }
}
