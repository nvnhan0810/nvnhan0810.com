<?php

namespace Tests\Unit\Modules\ReadingDigest\Infrastructure\Enrichment;

use Illuminate\Support\Facades\Http;
use Modules\ReadingDigest\Infrastructure\Enrichment\ArticleMetadataClient;
use Tests\TestCase;

final class ArticleMetadataClientTest extends TestCase
{
    public function test_it_should_map_summary_and_tags_from_ai_enrich_api(): void
    {
        config([
            'reading-digest.ai.base_url' => 'https://ai.nvnhan0810.com',
            'reading-digest.ai.api_key' => 'test-secret',
        ]);

        Http::fake([
            'https://ai.nvnhan0810.com/api/enrich' => Http::response([
                'summary' => 'Digest summary',
                'tags' => ['php', 'laravel'],
            ], 200),
        ]);

        $client = new ArticleMetadataClient;
        $result = $client->enrich('Title', 'Old summary', 'Body content here');

        $this->assertSame('Digest summary', $result['summary']);
        $this->assertSame(['php', 'laravel'], $result['topics']);
        $this->assertSame(['php', 'laravel'], $result['style_tags']);
    }

    public function test_it_should_fallback_when_ai_not_configured(): void
    {
        config([
            'reading-digest.ai.base_url' => 'https://ai.nvnhan0810.com',
            'reading-digest.ai.api_key' => null,
        ]);

        $client = new ArticleMetadataClient;
        $result = $client->enrich('Title', null, null);

        $this->assertSame([], $result['topics']);
        $this->assertArrayNotHasKey('summary', $result);
    }
}
