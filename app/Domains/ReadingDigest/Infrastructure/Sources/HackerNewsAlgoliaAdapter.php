<?php

namespace App\Domains\ReadingDigest\Infrastructure\Sources;

use App\Domains\ReadingDigest\Application\DTOs\FetchedArticleDTO;
use App\Domains\ReadingDigest\Domain\Repositories\SourceFetcherInterface;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Support\Facades\Http;

class HackerNewsAlgoliaAdapter implements SourceFetcherInterface
{
    public function fetch(SourceModel $source, int $limit = 50): array
    {
        $config = $source->config ?? [];
        $query = $config['query'] ?? '';
        $tags = $config['tags'] ?? 'story';

        $response = Http::timeout(30)->get('https://hn.algolia.com/api/v1/search', [
            'query' => $query,
            'tags' => $tags,
            'hitsPerPage' => min($limit, 50),
        ]);

        $response->throw();
        $hits = $response->json('hits') ?? [];

        $articles = [];

        foreach ($hits as $hit) {
            $objectId = (string) ($hit['objectID'] ?? '');
            $title = trim((string) ($hit['title'] ?? ''));
            $url = (string) ($hit['url'] ?? '');

            if ($url === '' && isset($hit['story_id'])) {
                $url = 'https://news.ycombinator.com/item?id='.$hit['story_id'];
            }

            if ($title === '' || $url === '') {
                continue;
            }

            $published = isset($hit['created_at_i'])
                ? (new \DateTimeImmutable)->setTimestamp((int) $hit['created_at_i'])
                : null;

            $articles[] = new FetchedArticleDTO(
                externalId: $objectId ?: md5($url),
                title: $title,
                url: $url,
                summary: null,
                contentText: null,
                contentHtml: null,
                publishedAt: $published,
                rawTags: array_filter([(string) ($hit['_tags'][0] ?? null)]),
                language: 'en',
            );
        }

        return $articles;
    }
}
