<?php

namespace Modules\Blog\Application\Handler;

use Modules\Blog\Application\Query\GetLatestPosts;
use Modules\Blog\Domain\Ports\PostRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetLatestPostsHandler implements QueryHandler
{
    public function __construct(
        private readonly PostRepository $posts,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof GetLatestPosts);

        return $this->posts->latestVisible($query->limit);
    }
}
