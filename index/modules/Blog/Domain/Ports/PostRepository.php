<?php

namespace Modules\Blog\Domain\Ports;

use Modules\Blog\Domain\Enums\PostStatus;

interface PostRepository
{
    /**
     * @return mixed LengthAwarePaginator of posts for the public list
     */
    public function paginateVisible(
        bool $authenticated,
        string $search,
        string $tag,
        ?string $statusFilter,
        int $perPage = 50,
    ): mixed;

    /**
     * @return mixed Eloquent post with relations, or null
     */
    public function findVisibleBySlug(string $slug, bool $authenticated): mixed;

    /**
     * @return mixed Eloquent post with tags/series, or null
     */
    public function findForEdit(int $id): mixed;

    /**
     * @return mixed Eloquent post, or null
     */
    public function findById(int $id): mixed;

    /**
     * @return list<mixed> Collection of guest-visible latest posts
     */
    public function latestVisible(int $limit): mixed;

    /**
     * @return list<mixed> Collection of guest-visible posts for sitemap
     */
    public function allVisibleForSitemap(): mixed;

    /**
     * @param  array{
     *     slug: string,
     *     title: string,
     *     description: string|null,
     *     content: string,
     *     source_url: string|null,
     *     status: PostStatus,
     *     published_at: mixed
     * }  $attributes
     * @param  list<string>|null  $tagNames
     * @param  list<int>  $seriesIds
     * @return mixed Created Eloquent post
     */
    public function createWithRelations(array $attributes, ?array $tagNames, array $seriesIds): mixed;

    /**
     * @param  array{
     *     title: string,
     *     description: string|null,
     *     content: string,
     *     source_url: string|null,
     *     status: PostStatus,
     *     published_at: mixed
     * }  $attributes
     * @param  list<string>|null  $tagNames
     * @param  list<int>  $seriesIds
     */
    public function updateWithRelations(int $id, array $attributes, ?array $tagNames, array $seriesIds): mixed;

    public function delete(int $id): void;
}
