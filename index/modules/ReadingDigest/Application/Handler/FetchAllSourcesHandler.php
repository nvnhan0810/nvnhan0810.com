<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Jobs\ReadingDigest\BatchEnrichArticleMetadataJob;
use App\Models\RdArticle;
use App\Models\RdSource;
use Illuminate\Support\Facades\Log;
use Modules\ReadingDigest\Domain\Services\ArticleLanguageService;
use Modules\ReadingDigest\Domain\Services\SourceFetchLimitCalculator;

class FetchAllSourcesHandler
{
    public function __construct(
        private readonly FetchSourceHandler $fetchSourceHandler,
    ) {}

    /**
     * @return array{sources: int, stored: int, purged: int, enriched_queued: int, errors: array<string, string>}
     */
    public function handle(?int $limitPerSource = null, ?\DateTimeInterface $since = null): array
    {
        $since ??= now()->subHours((int) config('reading-digest.fetch_since_hours', 24));

        $purged = $this->purgeDisallowedArticles();

        $sources = RdSource::query()
            ->with('subjects')
            ->where('enabled', true)
            ->orderBy('name')
            ->get();

        $stored = 0;
        $allNewArticleIds = [];
        $errors = [];

        foreach ($sources as $source) {
            $limit = $limitPerSource ?? SourceFetchLimitCalculator::forSource($source);

            try {
                Log::info('Reading digest source fetch limit', [
                    'source_id' => $source->id,
                    'source_name' => $source->name,
                    'limit' => $limit,
                    'enabled_subjects' => $source->subjects->where('enabled', true)->pluck('name')->values()->all(),
                ]);

                $result = $this->fetchSourceHandler->handle($source->id, $limit, $since);
                $stored += $result['stored'];
                array_push($allNewArticleIds, ...$result['article_ids']);
            } catch (\Throwable $e) {
                $errors[$source->id] = $e->getMessage();
                Log::warning('Reading digest source fetch failed', [
                    'source_id' => $source->id,
                    'source_name' => $source->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($allNewArticleIds !== []) {
            BatchEnrichArticleMetadataJob::dispatch($allNewArticleIds);
        }

        return [
            'sources' => $sources->count(),
            'stored' => $stored,
            'purged' => $purged,
            'enriched_queued' => count($allNewArticleIds),
            'errors' => $errors,
        ];
    }

    private function purgeDisallowedArticles(): int
    {
        $purged = RdArticle::query()
            ->where('published_at', '>', now())
            ->delete();

        RdArticle::query()
            ->select(['id', 'language', 'title', 'summary'])
            ->orderBy('id')
            ->chunkById(100, function ($articles) use (&$purged) {
                foreach ($articles as $article) {
                    $language = ArticleLanguageService::resolve(
                        $article->language,
                        trim($article->title.' '.($article->summary ?? '')),
                    );

                    if ($language !== $article->language && ArticleLanguageService::isAllowed($language)) {
                        $article->update(['language' => $language]);

                        continue;
                    }

                    if (! ArticleLanguageService::isAllowed($language)) {
                        $article->delete();
                        $purged++;
                    }
                }
            });

        return $purged;
    }
}
