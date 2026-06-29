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

        $purged = $this->purgeDisallowedArticles();

        $sources = SourceModel::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get();

        $stored = 0;
        $errors = [];

        foreach ($sources as $source) {
            try {
                $stored += $this->fetchSourceHandler->handle($source->id, $limitPerSource, $since);
            } catch (\Throwable $e) {
                $errors[$source->id] = $e->getMessage();

                try {
                    Log::warning('Reading digest source fetch failed', [
                        'source_id' => $source->id,
                        'source_name' => $source->name,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable) {
                    // Logging must not abort digest when cache/Telegram channel is misconfigured.
                }
            }
        }

        return [
            'sources' => $sources->count(),
            'stored' => $stored,
            'purged' => $purged,
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
