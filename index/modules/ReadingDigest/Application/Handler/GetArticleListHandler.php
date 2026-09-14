<?php

namespace Modules\ReadingDigest\Application\Handler;

use App\Models\RdArticle;
use Modules\ReadingDigest\Application\Query\GetArticleList;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetArticleListHandler implements QueryHandler
{
    public function handle(Query $query): mixed
    {
        assert($query instanceof GetArticleList);

        return RdArticle::query()
            ->with('source:id,name')
            ->where('force_exclude', false)
            ->orderByDesc('published_at')
            ->orderByDesc('fetched_at')
            ->paginate($query->perPage)
            ->withPath(route('news.index', absolute: false))
            ->withQueryString();
    }
}
