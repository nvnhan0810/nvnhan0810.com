<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Domain\Services\ArticleLanguageService;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Support\Facades\Log;

class FetchAllSourcesHandler
{
    public function __construct(
        private readonly FetchSourceHandler $fetchSourceHandler,
    ) {}

    /**
     * @return array{sources: int, stored: int, purged: int, errors: array<string, string>}
     */
    public function handle(?int $limitPerSource = null, ?\DateTimeInterface $since = null): array
    {
        $limitPerSource ??= (int) config('reading-digest.fetch_limit_per_source', 50);
        $since ??= now()->subHours((int) config('reading-digest.fetch_since_hours', 24));

        Log::warning('[ReadingDigest] FetchAllSourcesHandler: started', [
            'limit_per_source' => $limitPerSource,
            'since' => $since->format('Y-m-d H:i:s'),
        ]);

        try {
            $purged = $this->purgeDisallowedArticles();

            Log::warning('[ReadingDigest] FetchAllSourcesHandler: purge completed', [
                'purged' => $purged,
            ]);

            $sources = SourceModel::query()
                ->where('enabled', true)
                ->orderBy('name')
                ->get();

            Log::warning('[ReadingDigest] FetchAllSourcesHandler: sources loaded', [
                'sources_count' => $sources->count(),
            ]);

            $stored = 0;
            $errors = [];

            foreach ($sources as $source) {
                try {
                    $count = $this->fetchSourceHandler->handle($source->id, $limitPerSource, $since);
                    $stored += $count;

                    Log::warning('[ReadingDigest] FetchAllSourcesHandler: source fetched', [
                        'source_id' => $source->id,
                        'source_name' => $source->name,
                        'stored' => $count,
                    ]);
                } catch (\Throwable $e) {
                    $errors[$source->id] = $e->getMessage();

                    try {
                        Log::warning('[ReadingDigest] FetchAllSourcesHandler: source fetch failed', [
                            'source_id' => $source->id,
                            'source_name' => $source->name,
                            'error' => $e->getMessage(),
                            'exception' => $e::class,
                        ]);
                    } catch (\Throwable) {
                        // Logging must not abort digest when cache/Telegram channel is misconfigured.
                    }
                }
            }

            $result = [
                'sources' => $sources->count(),
                'stored' => $stored,
                'purged' => $purged,
                'errors' => $errors,
            ];

            Log::warning('[ReadingDigest] FetchAllSourcesHandler: completed', $result);

            return $result;
        } catch (\Throwable $e) {
            Log::warning('[ReadingDigest] FetchAllSourcesHandler: failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
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
