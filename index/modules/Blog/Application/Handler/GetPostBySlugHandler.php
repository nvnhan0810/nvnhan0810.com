<?php

namespace Modules\Blog\Application\Handler;

use Modules\Blog\Application\DTOs\PostShowResult;
use Modules\Blog\Application\Query\GetPostBySlug;
use Modules\Blog\Domain\Ports\PostRepository;
use Modules\Blog\Domain\Ports\SeriesRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetPostBySlugHandler implements QueryHandler
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly SeriesRepository $series,
    ) {}

    public function handle(Query $query): ?PostShowResult
    {
        assert($query instanceof GetPostBySlug);

        $post = $this->posts->findVisibleBySlug($query->slug, $query->authenticated);

        if ($post === null) {
            return null;
        }

        $selectedSeriesIds = $query->authenticated
            ? $post->series->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all()
            : [];

        return new PostShowResult(
            post: $post,
            series: $this->series->forPostWithVisiblePosts((int) $post->id, $query->authenticated),
            editorSeries: $query->authenticated ? $this->series->allForEditor() : [],
            selectedSeriesIds: $selectedSeriesIds,
            canManage: $query->authenticated,
        );
    }
}
