<?php

namespace Tests\Unit\Modules\ReadingDigest\Infrastructure\Embeddings;

use Illuminate\Support\Facades\Http;
use Modules\ReadingDigest\Infrastructure\Embeddings\AiEmbeddingClient;
use Tests\TestCase;

final class AiEmbeddingClientTest extends TestCase
{
    public function test_it_should_return_embedding_vector_from_ai_api(): void
    {
        config([
            'reading-digest.ai.base_url' => 'https://ai.nvnhan0810.com',
            'reading-digest.ai.api_key' => 'test-secret',
        ]);

        Http::fake([
            'https://ai.nvnhan0810.com/api/embed' => Http::response([
                'embedding' => [0.5, -0.25, 0.0],
            ], 200),
        ]);

        $client = new AiEmbeddingClient;
        $vector = $client->embed('article text');

        $this->assertSame([0.5, -0.25, 0], $vector);
    }

    public function test_it_should_return_null_when_ai_not_configured(): void
    {
        config([
            'reading-digest.ai.api_key' => '',
            'reading-digest.ai.base_url' => 'https://ai.nvnhan0810.com',
        ]);

        $client = new AiEmbeddingClient;

        $this->assertNull($client->embed('text'));
    }
}
