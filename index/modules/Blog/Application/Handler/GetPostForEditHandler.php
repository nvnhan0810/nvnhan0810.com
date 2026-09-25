<?php

namespace Modules\Blog\Application\Handler;

use Modules\Blog\Application\Query\GetPostForEdit;
use Modules\Blog\Domain\Ports\PostRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetPostForEditHandler implements QueryHandler
{
    public function __construct(
        private readonly PostRepository $posts,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof GetPostForEdit);

        return $this->posts->findForEdit($query->id);
    }
}
