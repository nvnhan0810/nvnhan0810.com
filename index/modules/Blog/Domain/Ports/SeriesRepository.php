<?php

namespace Modules\Blog\Domain\Ports;

interface SeriesRepository
{
    /**
     * @return mixed Collection of series id/name for editors
     */
    public function allForEditor(): mixed;

    /**
     * Series that contain the given post, with posts visible to the viewer.
     *
     * @return mixed Collection of series with nested posts
     */
    public function forPostWithVisiblePosts(int $postId, bool $authenticated): mixed;
}
