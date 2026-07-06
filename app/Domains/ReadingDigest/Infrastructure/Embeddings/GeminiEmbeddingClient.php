<?php

namespace App\Domains\ReadingDigest\Infrastructure\Embeddings;

use App\Domains\ReadingDigest\Infrastructure\Llm\GeminiApiClient;
use Illuminate\Support\Facades\Log;

class GeminiEmbeddingClient
{
    public function __construct(
        private readonly GeminiApiClient $gemini,
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
        $inputs = collect($texts)
            ->map(fn (string $text) => mb_substr(trim($text), 0, 8000))
            ->filter(fn (string $text) => $text !== '')
            ->values()
            ->all();

        if ($inputs === [] || ! $this->gemini->isConfigured()) {
            return array_fill(0, count($texts), null);
        }

        try {
            $response = $this->gemini->embeddings([
                'model' => config('reading-digest.embedding_model', 'text-embedding-004'),
                'input' => count($inputs) === 1 ? $inputs[0] : $inputs,
            ]);

            if (! $response->successful()) {
                Log::warning('Gemini embedding failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return array_fill(0, count($texts), null);
            }

            $data = $response->json('data');
            if (! is_array($data)) {
                return array_fill(0, count($texts), null);
            }

            usort($data, fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

            $vectors = collect($data)
                ->map(fn ($row) => is_array($row['embedding'] ?? null) ? $row['embedding'] : null)
                ->all();

            if (count($texts) === 1) {
                return [$vectors[0] ?? null];
            }

            $results = array_fill(0, count($texts), null);
            $vectorIndex = 0;

            foreach ($texts as $index => $text) {
                if (trim($text) === '') {
                    continue;
                }

                $results[$index] = $vectors[$vectorIndex] ?? null;
                $vectorIndex++;
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('Gemini embedding error', ['message' => $e->getMessage()]);

            return array_fill(0, count($texts), null);
        }
    }
}
