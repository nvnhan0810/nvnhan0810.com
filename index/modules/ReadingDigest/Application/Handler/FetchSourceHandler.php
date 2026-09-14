<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdArticle;
use App\Models\RdSource;
use Illuminate\Support\Str;
use Modules\ReadingDigest\Domain\Services\ArticleFreshnessPolicy;
use Modules\ReadingDigest\Domain\Services\ArticleLanguageService;
use Modules\ReadingDigest\Infrastructure\Sources\SourceFetcherRegistry;

class FetchSourceHandler
{
    public function __construct(
        private readonly SourceFetcherRegistry $fetcherRegistry,
    ) {}

    /**
     * @return array{stored: int, article_ids: array<int, string>}
     */
    public function handle(string $sourceId, int $limit = 15, ?\DateTimeInterface $since = null): array
    {
        $source = RdSource::query()->findOrFail($sourceId);
        $newArticleIds = [];

        try {
            $fetcher = $this->fetcherRegistry->for($source);
            $items = $fetcher->fetch($source, $limit);

            foreach ($items as $item) {
                if ($since !== null && $item->publishedAt !== null && $item->publishedAt < $since) {
                    continue;
                }

                if (ArticleFreshnessPolicy::isPublishedInTheFuture($item->publishedAt)) {
                    continue;
                }

                $language = ArticleLanguageService::resolve(
                    $item->language,
                    trim($item->title.' '.($item->summary ?? '').' '.($item->contentText ?? '')),
                );

                if (! ArticleLanguageService::isAllowed($language)) {
                    continue;
                }

                $urlHash = hash('sha256', strtolower(trim($item->url)));

                $existing = RdArticle::query()
                    ->where('source_id', $source->id)
                    ->where(function ($q) use ($item, $urlHash) {
                        $q->where('external_id', $item->externalId)->orWhere('url_hash', $urlHash);
                    })
                    ->first();

                if ($existing) {
                    continue;
                }

                $wordCount = str_word_count($item->contentText ?? $item->summary ?? '');
                $readTime = max(1, (int) ceil($wordCount / 200));

                $article = RdArticle::create([
                    'source_id' => $source->id,
                    'external_id' => $item->externalId,
                    'url_hash' => $urlHash,
                    'title' => $item->title,
                    'url' => $item->url,
                    'summary' => $item->summary,
                    'image_url' => $item->imageUrl,
                    'content_text' => $item->contentText,
                    'content_html' => $item->contentHtml,
                    'language' => $language,
                    'estimated_read_time_minutes' => $readTime,
                    'metadata' => ['raw_tags' => $item->rawTags],
                    'published_at' => $item->publishedAt,
                    'fetched_at' => now(),
                ]);

                $newArticleIds[] = $article->id;
            }

            $source->update([
                'last_fetch_status' => 'success',
                'last_fetch_at' => now(),
                'last_fetch_error' => null,
            ]);

            return [
                'stored' => count($newArticleIds),
                'article_ids' => $newArticleIds,
            ];
        } catch (\Throwable $e) {
            $source->update([
                'last_fetch_status' => 'failed',
                'last_fetch_at' => now(),
                'last_fetch_error' => Str::limit($e->getMessage(), 500),
            ]);

            throw $e;
        }
    }
}
