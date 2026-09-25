<?php

namespace Modules\Blog\Infrastructure\Persistence;

use App\Models\Series;
use Modules\Blog\Domain\Ports\SeriesRepository;

final class EloquentSeriesRepository implements SeriesRepository
{
    public function allForEditor(): mixed
    {
        return Series::query()->orderBy('name')->get(['id', 'name']);
    }

    public function forPostWithVisiblePosts(int $postId, bool $authenticated): mixed
    {
        return Series::with(['posts' => function ($query) use ($authenticated) {
            $query->visibleToViewer($authenticated)
                ->orderByPivot('order');
        }])->whereHas('posts', function ($query) use ($postId) {
            $query->where('post_id', $postId);
        })->get();
    }
}
