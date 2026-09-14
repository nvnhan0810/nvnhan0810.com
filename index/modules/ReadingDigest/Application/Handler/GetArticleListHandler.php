<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdArticle;
use Modules\ReadingDigest\Application\Query\GetArticleList;
use Modules\ReadingDigest\Domain\Services\ArticleFreshnessPolicy;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetArticleListHandler implements QueryHandler
{
    public function handle(Query $query): mixed
    {
        assert($query instanceof GetArticleList);

        $articlesQuery = RdArticle::query()
            ->with('source:id,name')
            ->where('force_exclude', false);

        ArticleFreshnessPolicy::excludeFuturePublished($articlesQuery);

        return $articlesQuery
            ->orderByDesc('published_at')
            ->orderByDesc('fetched_at')
            ->paginate($query->perPage)
            ->withPath(route('news.index', absolute: false))
            ->withQueryString();
    }
}
