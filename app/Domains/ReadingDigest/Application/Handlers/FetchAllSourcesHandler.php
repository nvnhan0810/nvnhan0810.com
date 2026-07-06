<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Domain\Services\ArticleLanguageService;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Domains\ReadingDigest\Presentation\Jobs\BatchEnrichArticleMetadataJob;
use Illuminate\Support\Facades\Log;

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
        $limitPerSource ??= (int) config('reading-digest.fetch_limit_per_source', 50);
        $since ??= now()->subHours((int) config('reading-digest.fetch_since_hours', 24));

        $purged = $this->purgeDisallowedArticles();

        $sources = SourceModel::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get();

        $stored = 0;
        $allNewArticleIds = [];
        $errors = [];

        foreach ($sources as $source) {
            try {
                $result = $this->fetchSourceHandler->handle($source->id, $limitPerSource, $since);
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
        $purged = 0;

        DigestArticleModel::query()
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
