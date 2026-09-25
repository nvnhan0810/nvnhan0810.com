<?php

namespace Modules\Blog\Application\Handler;

use Modules\Blog\Application\Query\ListTags;
use Modules\Blog\Domain\Ports\TagRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class ListTagsHandler implements QueryHandler
{
    public function __construct(
        private readonly TagRepository $tags,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof ListTags);

        return $this->tags->allWithPublicPostCount();
    }
}
