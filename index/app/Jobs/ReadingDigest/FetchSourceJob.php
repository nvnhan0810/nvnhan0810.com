<?php

namespace App\Jobs\ReadingDigest;

use App\Models\RdSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\ReadingDigest\Application\Handler\FetchSourceHandler;
use Modules\ReadingDigest\Domain\Services\SourceFetchLimitCalculator;

class FetchSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $sourceId) {}

    public function handle(FetchSourceHandler $handler): void
    {
        $source = RdSource::query()->with('subjects')->findOrFail($this->sourceId);
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
