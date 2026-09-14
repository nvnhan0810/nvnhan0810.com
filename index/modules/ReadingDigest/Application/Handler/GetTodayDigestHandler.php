<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdArticle;
use App\Models\RdDigestRun;
use Modules\ReadingDigest\Application\Query\GetTodayDigest;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetTodayDigestHandler implements QueryHandler
{
    public function handle(Query $query): mixed
    {
        assert($query instanceof GetTodayDigest);

        $run = RdDigestRun::query()
            ->where('user_id', $query->userId)
            ->whereDate('run_date', $query->date)
            ->with(['items.article.source', 'items.subject'])
            ->first();

        $groups = [];

        if ($run) {
            $items = $run->items->sortBy('rank')->values();

            foreach ($items as $item) {
                $article = $item->article;
                if (! $article instanceof RdArticle) {
                    continue;
                }

                $sourceName = $article->source?->name
                    ?? $item->subject?->name
                    ?? 'Other';

                $groups[$sourceName] ??= [];
                $groups[$sourceName][] = [
                    'id' => $item->id,
                    'rank' => $item->rank,
                    'tracking_token' => $item->tracking_token,
                    'subject' => $item->subject?->only(['id', 'name']),
                    'article' => [
                        'id' => $article->id,
                        'title' => $article->title,
                        'url' => $article->url,
                        'summary' => $article->summary,
                        'image_url' => $article->image_url,
                        'published_at' => $article->published_at,
                        'source' => $article->source?->only(['id', 'name']),
                    ],
                ];
            }
        }

        return [
            'run' => $run ? [
                'id' => $run->id,
                'run_date' => $run->run_date,
                'status' => $run->status,
                'telegram_sent_at' => $run->telegram_sent_at,
            ] : null,
            'groups' => $groups,
        ];
    }
}
