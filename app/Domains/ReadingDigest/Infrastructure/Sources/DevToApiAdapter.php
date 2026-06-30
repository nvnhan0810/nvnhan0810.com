<?php

namespace App\Domains\ReadingDigest\Infrastructure\Sources;

use App\Domains\ReadingDigest\Application\DTOs\FetchedArticleDTO;
use App\Domains\ReadingDigest\Domain\Repositories\SourceFetcherInterface;
use App\Domains\ReadingDigest\Domain\Services\ArticleLanguageService;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DevToApiAdapter implements SourceFetcherInterface
{
    public static function supportsUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        return str_ends_with($host, 'dev.to')
            && str_starts_with($path, '/api/articles');
    }

    public function fetch(SourceModel $source, int $limit = 50): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'ReadingDigest/1.0 (+https://nvnhan0810.com)',
                'Accept' => 'application/vnd.forem.api-v1+json',
            ])
            ->get($source->url, [
                'per_page' => min($limit, 1000),
            ]);

        $response->throw();

        $items = $response->json();
        if (! is_array($items)) {
            throw new RuntimeException('dev.to API did not return a JSON array');
        }

        $articles = [];
        $count = 0;

        foreach ($items as $item) {
            if ($count >= $limit || ! is_array($item)) {
                break;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            if ($title === '' || $url === '') {
                continue;
            }

            $externalId = (string) ($item['id'] ?? md5($url));
            $summary = trim(strip_tags((string) ($item['description'] ?? '')));
            $published = isset($item['published_timestamp'])
                ? new \DateTimeImmutable((string) $item['published_timestamp'])
                : (isset($item['published_at'])
                    ? new \DateTimeImmutable((string) $item['published_at'])
                    : null);

            $rawTags = [];
            if (isset($item['tag_list']) && is_array($item['tag_list'])) {
                foreach ($item['tag_list'] as $tag) {
                    $tag = strtolower(trim((string) $tag));
                    if ($tag !== '') {
                        $rawTags[] = $tag;
                    }
                }
            }

            $language = ArticleLanguageService::resolve(
                isset($item['language']) ? (string) $item['language'] : null,
                $title.' '.$summary,
            );

            $articles[] = new FetchedArticleDTO(
                externalId: $externalId,
                title: $title,
                url: $url,
                summary: $summary ?: null,
                contentText: $summary ?: null,
                contentHtml: null,
                publishedAt: $published,
                rawTags: $rawTags,
                language: $language,
            );

            $count++;
        }

        return $articles;
    }
}
