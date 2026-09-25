<?php

namespace Modules\Blog\Application\Handler;

use Modules\Blog\Application\Query\ListSitemapPosts;
use Modules\Blog\Domain\Ports\PostRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class ListSitemapPostsHandler implements QueryHandler
{
    public function __construct(
        private readonly PostRepository $posts,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof ListSitemapPosts);

        return $this->posts->allVisibleForSitemap();
    }
}
