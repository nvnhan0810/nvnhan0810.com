<?php

namespace Modules\Blog\Application\Handler;

use Modules\Blog\Application\Query\ListSeries;
use Modules\Blog\Domain\Ports\SeriesRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class ListSeriesHandler implements QueryHandler
{
    public function __construct(
        private readonly SeriesRepository $series,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof ListSeries);

        return $this->series->allForEditor();
    }
}
