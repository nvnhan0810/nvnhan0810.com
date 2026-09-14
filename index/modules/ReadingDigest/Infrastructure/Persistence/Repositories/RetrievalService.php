<?php

namespace Modules\ReadingDigest\Infrastructure\Persistence\Repositories;

use App\Models\RdArticle;
use App\Models\RdArticleInteraction;
use App\Models\RdDigestRunItem;
use App\Models\RdSubject;
use App\Models\RdUserInterestScore;
use App\Models\RdUserReadingProfile;
use Modules\ReadingDigest\Domain\Services\ArticleFreshnessPolicy;
use Modules\ReadingDigest\Domain\Services\ArticleLanguageService;
use Modules\ReadingDigest\Domain\Services\RetrievalScoringService;

class RetrievalService
{
    public function __construct(
        private readonly RetrievalScoringService $scoringService,
    ) {}

    /**
     * @return array<int, array{article: RdArticle, score: float}>
     */
    public function retrieveForSubject(RdSubject $subject, int $userId, int $limit = 30): array
    {
        $profile = RdUserReadingProfile::query()->firstOrCreate(
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

        $interestScores = RdUserInterestScore::query()
            ->where('user_id', $userId)
            ->pluck('score', 'taxonomy_node_id')
            ->all();

        $sourceIds = $subject->sources()->where('enabled', true)->pluck('rd_sources.id');

        if ($sourceIds->isEmpty()) {
            return [];
        }

        $dismissedArticleIds = RdArticleInteraction::query()
            ->where('user_id', $userId)
            ->whereIn('event', ['dismissed', 'disliked'])
            ->pluck('article_id');

        $articlesQuery = RdArticle::query()
            ->with(['source', 'taxonomyNodes', 'embedding'])
            ->whereIn('source_id', $sourceIds)
            ->where('force_exclude', false)
            ->whereIn('language', $preferredLanguages)
            ->whereNotIn('id', $dismissedArticleIds);

        ArticleFreshnessPolicy::applyScope($articlesQuery);

        if (! ArticleFreshnessPolicy::onlyFetchedToday()) {
            $maxAgeDays = $subject->max_age_days ?? 7;
            $cutoff = now()->subDays($maxAgeDays);

            $recentlySentArticleIds = RdDigestRunItem::query()
                ->whereHas('digestRun', fn ($q) => $q->where('user_id', $userId)->where('run_date', '>=', now()->subDays(7)))
                ->pluck('article_id');

            $articlesQuery
                ->where(function ($q) use ($cutoff) {
                    $q->where('published_at', '>=', $cutoff)->orWhereNull('published_at');
                })
                ->whereNotIn('id', $recentlySentArticleIds);
        }

        $articles = $articlesQuery
            ->orderByDesc('published_at')
            ->limit(500)
            ->get();

        $userEmbedding = $profile->user_embedding;

        $scored = $articles->map(function (RdArticle $article) use (
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
                ->map(fn (RdArticle $article) => [
                    'article' => $article,
                    'score' => 0.0,
                ])
                ->values()
                ->all();
        }

        return $scored;
    }
}
