<?php

namespace App\Domains\ReadingDigest\Application\Handlers;

use App\Domains\ReadingDigest\Domain\Enums\InteractionEvent;
use App\Domains\ReadingDigest\Domain\Services\InterestScoreService;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\ArticleInteractionModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserInterestScoreModel;

class RecordInteractionHandler
{
    public function __construct(
        private readonly InterestScoreService $interestScoreService,
    ) {}

    public function handle(
        int $userId,
        string $articleId,
        InteractionEvent $event,
        ?array $metadata = null,
        ?string $subjectId = null,
    ): void {
        ArticleInteractionModel::create([
            'user_id' => $userId,
            'article_id' => $articleId,
            'event' => $event->value,
            'metadata' => $metadata,
            'subject_id' => $subjectId,
            'created_at' => now(),
        ]);

        $weight = $this->interestScoreService->weightForEvent($event, $metadata);
        if ($weight === 0.0) {
            return;
        }

        $article = DigestArticleModel::query()->with('taxonomyNodes')->findOrFail($articleId);

        foreach ($article->taxonomyNodes as $node) {
            $confidence = (float) ($node->pivot->confidence ?? 1);
            $delta = $weight * $confidence;

            $score = UserInterestScoreModel::query()->firstOrNew([
                'user_id' => $userId,
                'taxonomy_node_id' => $node->id,
            ]);

            $score->score = ($score->score ?? 0) + $delta;
            $score->updated_at = now();
            $score->save();
        }
    }
}
