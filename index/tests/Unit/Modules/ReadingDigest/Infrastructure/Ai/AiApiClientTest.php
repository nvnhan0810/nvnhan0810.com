<?php

namespace Tests\Unit\Modules\ReadingDigest\Infrastructure\Ai;

use Illuminate\Support\Facades\Http;
use Modules\ReadingDigest\Infrastructure\Ai\AiApiClient;
use Tests\TestCase;

final class AiApiClientTest extends TestCase
{
    public function test_it_should_post_embed_when_configured(): void
    {
        config([
            'reading-digest.ai.base_url' => 'https://ai.nvnhan0810.com',
            'reading-digest.ai.api_key' => 'test-secret',
            'reading-digest.ai.timeout' => 10,
        ]);

        Http::fake([
            'https://ai.nvnhan0810.com/api/embed' => Http::response([
                'embedding' => [0.1, 0.2, 0.3],
            ], 200),
        ]);

        $client = new AiApiClient;
        $response = $client->embed('hello world');

        $this->assertTrue($response->successful());
        $this->assertSame([0.1, 0.2, 0.3], $response->json('embedding'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://ai.nvnhan0810.com/api/embed'
                && $request->hasHeader('X-Api-Key', 'test-secret')
                && $request['text'] === 'hello world';
        });
    }

    public function test_it_should_post_enrich_when_configured(): void
    {
        config([
            'reading-digest.ai.base_url' => 'https://ai.nvnhan0810.com',
            'reading-digest.ai.api_key' => 'test-secret',
        ]);

        Http::fake([
            'https://ai.nvnhan0810.com/api/enrich' => Http::response([
                'summary' => 'Short summary',
                'tags' => ['laravel', 'ddd'],
            ], 200),
        ]);

        $client = new AiApiClient;
        $response = $client->enrich('long article text');

        $this->assertTrue($response->successful());
        $this->assertSame('Short summary', $response->json('summary'));
        $this->assertSame(['laravel', 'ddd'], $response->json('tags'));
    }

    public function test_it_should_report_not_configured_when_api_key_missing(): void
    {
        config([
            'reading-digest.ai.base_url' => 'https://ai.nvnhan0810.com',
            'reading-digest.ai.api_key' => '',
        ]);

        $client = new AiApiClient;

        $this->assertFalse($client->isConfigured());
    }
}
