<?php

namespace App\Domains\ReadingDigest\Infrastructure\Embeddings;

use App\Domains\ReadingDigest\Infrastructure\Llm\CursorApiClient;
use Illuminate\Support\Facades\Log;

class OpenAiEmbeddingClient
{
    public function __construct(
        private readonly CursorApiClient $cursorApi,
    ) {}

    public function embed(string $text): ?array
    {
        if (! $this->cursorApi->isConfigured() || trim($text) === '') {
            return null;
        }

        try {
            $response = $this->cursorApi->embeddings([
                'model' => config('reading-digest.embedding_model', 'text-embedding-3-small'),
                'input' => mb_substr($text, 0, 8000),
            ]);

            if (! $response->successful()) {
                if ($response->status() !== 404) {
                    Log::warning('LLM embedding failed', [
                        'status' => $response->status(),
                        'endpoint' => $this->cursorApi->baseUrl().'/embeddings',
                    ]);
                }

                return null;
            }

            return $response->json('data.0.embedding');
        } catch (\Throwable $e) {
            Log::warning('LLM embedding error', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
