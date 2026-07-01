<?php

namespace App\Domains\ReadingDigest\Infrastructure\Sources;

use App\Domains\ReadingDigest\Application\DTOs\FetchedArticleDTO;
use App\Domains\ReadingDigest\Domain\Repositories\SourceFetcherInterface;
use App\Domains\ReadingDigest\Domain\Services\ArticleLanguageService;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class RssSourceAdapter implements SourceFetcherInterface
{
    public function fetch(SourceModel $source, int $limit = 50): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'ReadingDigest/1.0 (+https://nvnhan0810.com)',
                'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
            ])
            ->get($source->url);
        $response->throw();

        $xml = $this->parseFeedXml($response->body());
        $items = $this->extractItems($xml);
        $channelLanguage = ArticleLanguageService::normalize((string) ($xml->channel->language ?? ''));

        $articles = [];
        $count = 0;

        foreach ($items as $item) {
            if ($count >= $limit) {
                break;
            }

            $link = $this->extractLink($item);
            $title = $this->cleanText((string) ($item->title ?? ''));
            if ($link === '' || $title === '') {
                continue;
            }

            $summary = $this->cleanText((string) ($item->description ?? $item->summary ?? $item->content ?? ''));
            $published = isset($item->pubDate)
                ? new \DateTimeImmutable((string) $item->pubDate)
                : (isset($item->published) ? new \DateTimeImmutable((string) $item->published) : null);

            $externalId = md5($link);
            $categories = [];
            foreach ($item->category ?? [] as $category) {
                $categories[] = strtolower(trim((string) $category));
            }

            $language = ArticleLanguageService::resolve(
                $channelLanguage,
                $title.' '.$summary,
            );

            $articles[] = new FetchedArticleDTO(
                externalId: $externalId,
                title: $title,
                url: $link,
                summary: $summary ?: null,
                contentText: $summary ?: null,
                contentHtml: null,
                publishedAt: $published,
                rawTags: $categories,
                language: $language,
            );

            $count++;
        }

        return $articles;
    }

    private function parseFeedXml(string $body): SimpleXMLElement
    {
        $body = preg_replace('/^\xEF\xBB\xBF/', '', trim($body)) ?? '';

        if ($body === '' || ! str_starts_with($body, '<')) {
            $hint = match (true) {
                str_starts_with($body, '{'), str_starts_with($body, '[') => ' Response looks like JSON — for dev.to use /feed/tag/... (RSS) or /api/articles?... (JSON API).',
                default => '',
            };

            throw new RuntimeException('RSS feed did not return XML content.'.$hint);
        }

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($xml === false) {
            $detail = isset($errors[0]) ? trim($errors[0]->message) : 'unknown XML parse error';

            throw new RuntimeException('RSS feed XML parse failed: '.$detail);
        }

        return $xml;
    }

    /**
     * @return iterable<SimpleXMLElement>
     */
    private function extractItems(SimpleXMLElement $xml): iterable
    {
        if (isset($xml->channel->item)) {
            return $xml->channel->item;
        }

        if (isset($xml->entry)) {
            return $xml->entry;
        }

        $atom = $xml->children('http://www.w3.org/2005/Atom');
        if (isset($atom->entry)) {
            return $atom->entry;
        }

        return [];
    }

    /**
     * Decode HTML entities, strip tags and collapse whitespace.
     *
     * Many feeds (esp. Vietnamese news via Google News) double-encode content,
     * e.g. `c&amp;oacute;` which XML parsing turns into `c&oacute;`. A second
     * decode pass turns the remaining named entities into real characters (`có`).
     */
    private function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('/&(?:[a-zA-Z][a-zA-Z0-9]+|#\d+|#x[0-9a-fA-F]+);/', $value) === 1) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function extractLink(SimpleXMLElement $item): string
    {
        if (isset($item->link['href'])) {
            return trim((string) $item->link['href']);
        }

        if (isset($item->link)) {
            foreach ($item->link as $linkNode) {
                $href = trim((string) ($linkNode['href'] ?? $linkNode));
                $rel = trim((string) ($linkNode['rel'] ?? 'alternate'));

                if ($href !== '' && ($rel === '' || $rel === 'alternate')) {
                    return $href;
                }
            }
        }

        return trim((string) ($item->guid ?? ''));
    }
}
