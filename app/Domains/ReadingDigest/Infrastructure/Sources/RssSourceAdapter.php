<?php

namespace App\Domains\ReadingDigest\Infrastructure\Sources;

use App\Domains\ReadingDigest\Application\DTOs\FetchedArticleDTO;
use App\Domains\ReadingDigest\Domain\Repositories\SourceFetcherInterface;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

class RssSourceAdapter implements SourceFetcherInterface
{
    public function fetch(SourceModel $source, int $limit = 50): array
    {
        $response = Http::timeout(30)->get($source->url);
        $response->throw();

        $xml = new SimpleXMLElement($response->body());
        $items = $xml->channel->item ?? $xml->entry ?? [];

        $articles = [];
        $count = 0;

        foreach ($items as $item) {
            if ($count >= $limit) {
                break;
            }

            $link = (string) ($item->link['href'] ?? $item->link ?? $item->guid ?? '');
            $title = trim((string) ($item->title ?? ''));
            if ($link === '' || $title === '') {
                continue;
            }

            $summary = trim(strip_tags((string) ($item->description ?? $item->summary ?? '')));
            $published = isset($item->pubDate)
                ? new \DateTimeImmutable((string) $item->pubDate)
                : (isset($item->published) ? new \DateTimeImmutable((string) $item->published) : null);

            $externalId = md5($link);
            $categories = [];
            foreach ($item->category ?? [] as $category) {
                $categories[] = strtolower(trim((string) $category));
            }

            $articles[] = new FetchedArticleDTO(
                externalId: $externalId,
                title: $title,
                url: $link,
                summary: $summary ?: null,
                contentText: $summary ?: null,
                contentHtml: null,
                publishedAt: $published,
                rawTags: $categories,
            );

            $count++;
        }

        return $articles;
    }
}
