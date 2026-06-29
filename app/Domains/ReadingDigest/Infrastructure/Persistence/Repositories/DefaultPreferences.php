<?php

namespace App\Domains\ReadingDigest\Infrastructure\Persistence\Repositories;

class DefaultPreferences
{
    public static function make(): array
    {
        return [
            'favorite_topics' => [
                'programming.frontend.react' => 10,
                'programming.frontend.typescript' => 9,
                'programming.backend.go' => 8,
                'programming.devops.kubernetes' => 7,
                'ai.llm' => 6,
            ],
            'preferred_sources' => [],
            'ignored_topics' => ['crypto', 'politics'],
            'ignored_taxonomy_ids' => [],
            'preferred_difficulty' => 'advanced',
            'preferred_length' => 'medium',
            'preferred_language' => ['en'],
            'preferred_article_types' => ['tutorial', 'deep_dive', 'architecture', 'performance'],
            'disliked_styles' => ['marketing', 'opinion', 'clickbait'],
            'llm_ranking_enabled' => true,
            'min_retrieval_score' => 0,
        ];
    }
}
