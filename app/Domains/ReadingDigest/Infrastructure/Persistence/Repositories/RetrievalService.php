<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Repositories;

use App\Domains\ReadingDigest\Domain\Services\ArticleLanguageService;
use App\Domains\ReadingDigest\Domain\Services\RetrievalScoringService;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\ArticleInteractionModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunItemModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\SubjectModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserInterestScoreModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserReadingProfileModel;

class RetrievalService
{
    public function __construct(
        private readonly RetrievalScoringService $scoringService,
    ) {}

    /**
     * @return array<int, array{article: DigestArticleModel, score: float}>
     */
    public function retrieveForSubject(SubjectModel $subject, int $userId, int $limit = 30): array
    {
        $profile = UserReadingProfileModel::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => DefaultPreferences::make()]
        );

        $preferences = $profile->preferences ?? DefaultPreferences::make();
        $favoriteTopics = $preferences['favorite_topics'] ?? [];
        $ignoredTaxonomyIds = $preferences['ignored_taxonomy_ids'] ?? [];
        $preferredSources = $preferences['preferred_sources'] ?? [];
        $preferredDifficulty = $preferences['preferred_difficulty'] ?? null;
        $preferredArticleTypes = $preferences['preferred_article_types'] ?? [];
        $preferredLanguages = array_values(array_intersect(
            $preferences['preferred_language'] ?? ArticleLanguageService::allowed(),
            ArticleLanguageService::allowed(),
        ));

        if ($preferredLanguages === []) {
            $preferredLanguages = ArticleLanguageService::allowed();
        }

        $interestScores = UserInterestScoreModel::query()
            ->where('user_id', $userId)
            ->pluck('score', 'taxonomy_node_id')
            ->all();

        $sourceIds = $subject->sources()->pluck('rd_sources.id');
        $maxAgeDays = $subject->max_age_days ?? 7;
        $cutoff = now()->subDays($maxAgeDays);

        $recentlySentArticleIds = DigestRunItemModel::query()
            ->whereHas('digestRun', fn ($q) => $q->where('user_id', $userId)->where('run_date', '>=', now()->subDays(7)))
            ->pluck('article_id');

        $dismissedArticleIds = ArticleInteractionModel::query()
            ->where('user_id', $userId)
            ->whereIn('event', ['dismissed', 'disliked'])
            ->pluck('article_id');

        $articles = DigestArticleModel::query()
            ->with(['source', 'taxonomyNodes', 'embedding'])
            ->whereIn('source_id', $sourceIds)
            ->where('force_exclude', false)
            ->where(function ($q) use ($cutoff) {
                $q->where('published_at', '>=', $cutoff)->orWhereNull('published_at');
            })
            ->whereIn('language', $preferredLanguages)
            ->whereNotIn('id', $recentlySentArticleIds)
            ->whereNotIn('id', $dismissedArticleIds)
            ->orderByDesc('published_at')
            ->limit(500)
            ->get();

        $userEmbedding = $profile->user_embedding;

        $scored = $articles->map(function (DigestArticleModel $article) use (
            $favoriteTopics,
            $interestScores,
            $ignoredTaxonomyIds,
            $userEmbedding,
            $preferredSources,
            $preferredDifficulty,
            $preferredArticleTypes,
        ) {
            $articleEmbedding = $article->embedding?->vector;
            $score = $this->scoringService->score(
                $article,
                $favoriteTopics,
                $interestScores,
                $ignoredTaxonomyIds,
                $userEmbedding,
                $articleEmbedding,
                $preferredSources,
                $preferredDifficulty,
                $preferredArticleTypes,
            );

            return ['article' => $article, 'score' => $score];
        })
            ->filter(fn ($item) => $item['score'] > -900)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();

        if ($scored === []) {
            return $articles
                ->take($limit)
                ->map(fn (DigestArticleModel $article) => [
                    'article' => $article,
                    'score' => 0.0,
                ])
                ->values()
                ->all();
        }

        return $scored;
    }
}
