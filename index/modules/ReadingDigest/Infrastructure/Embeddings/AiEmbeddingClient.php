<?php

namespace Modules\ReadingDigest\Infrastructure\Embeddings;

use Illuminate\Support\Facades\Log;
use Modules\ReadingDigest\Infrastructure\Ai\AiApiClient;

class AiEmbeddingClient
{
    public function __construct(
        private readonly AiApiClient $ai = new AiApiClient,
    ) {}

    public function embed(string $text): ?array
    {
        $vectors = $this->embedBatch([$text]);

        return $vectors[0] ?? null;
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, ?array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        if ($texts === [] || ! $this->ai->isConfigured()) {
            return array_fill(0, count($texts), null);
        }

        $results = [];

        foreach ($texts as $text) {
            $trimmed = mb_substr(trim($text), 0, 8000);
            if ($trimmed === '') {
                $results[] = null;

                continue;
            }

            try {
                $response = $this->ai->embed($trimmed);

                if (! $response->successful()) {
                    Log::warning('AI embedding failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    $results[] = null;

                    continue;
                }

                $embedding = $response->json('embedding');
                $results[] = is_array($embedding) ? $embedding : null;
            } catch (\Throwable $e) {
                Log::warning('AI embedding error', ['message' => $e->getMessage()]);
                $results[] = null;
            }
        }

        return $results;
    }
}
