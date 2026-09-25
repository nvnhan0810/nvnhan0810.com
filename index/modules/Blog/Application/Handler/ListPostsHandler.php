<?php

namespace Modules\Blog\Application\Handler;

use Modules\Blog\Application\DTOs\PostListResult;
use Modules\Blog\Application\Query\ListPosts;
use Modules\Blog\Domain\Enums\PostStatus;
use Modules\Blog\Domain\Ports\PostRepository;
use Modules\Blog\Domain\Ports\SeriesRepository;
use Modules\Blog\Domain\Ports\TagRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class ListPostsHandler implements QueryHandler
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly TagRepository $tags,
        private readonly SeriesRepository $series,
    ) {}

    public function handle(Query $query): PostListResult
    {
        assert($query instanceof ListPosts);

        $statusFilter = $query->authenticated
            && $query->statusFilter !== ''
            && in_array($query->statusFilter, PostStatus::values(), true)
            ? $query->statusFilter
            : null;

        $paginator = $this->posts->paginateVisible(
            $query->authenticated,
            $query->search,
            $query->tag,
            $statusFilter,
        );

        $editingPost = null;
        $selectedSeriesIds = [];

        if ($query->authenticated && $query->editId !== null && $query->editId > 0) {
            $editingPost = $this->posts->findForEdit($query->editId);
            if ($editingPost !== null) {
                $selectedSeriesIds = $editingPost->series->pluck('id')->values()->all();
            }
        }

        return new PostListResult(
            posts: $paginator,
            tags: $this->tags->allWithPublicPostCount(),
            filters: [
                'search' => $query->search !== '' ? $query->search : null,
                'tag' => $query->tag !== '' ? $query->tag : null,
                'status' => $query->authenticated && $query->statusFilter !== ''
                    ? $query->statusFilter
                    : null,
            ],
            series: $query->authenticated ? $this->series->allForEditor() : [],
            editingPost: $editingPost,
            selectedSeriesIds: $selectedSeriesIds,
            canManage: $query->authenticated,
        );
    }
}
