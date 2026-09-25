<?php

namespace Modules\Blog;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Blog\Application\Command\CreatePost;
use Modules\Blog\Application\Command\DeletePost;
use Modules\Blog\Application\Command\UpdatePost;
use Modules\Blog\Application\Handler\CreatePostHandler;
use Modules\Blog\Application\Handler\DeletePostHandler;
use Modules\Blog\Application\Handler\GetLatestPostsHandler;
use Modules\Blog\Application\Handler\GetPostBySlugHandler;
use Modules\Blog\Application\Handler\GetPostForEditHandler;
use Modules\Blog\Application\Handler\ListPostsHandler;
use Modules\Blog\Application\Handler\ListSeriesHandler;
use Modules\Blog\Application\Handler\ListSitemapPostsHandler;
use Modules\Blog\Application\Handler\ListTagsHandler;
use Modules\Blog\Application\Handler\UpdatePostHandler;
use Modules\Blog\Application\Query\GetLatestPosts;
use Modules\Blog\Application\Query\GetPostBySlug;
use Modules\Blog\Application\Query\GetPostForEdit;
use Modules\Blog\Application\Query\ListPosts;
use Modules\Blog\Application\Query\ListSeries;
use Modules\Blog\Application\Query\ListSitemapPosts;
use Modules\Blog\Application\Query\ListTags;
use Modules\Blog\Domain\Ports\PostRepository;
use Modules\Blog\Domain\Ports\SeriesRepository;
use Modules\Blog\Domain\Ports\SlugGenerator;
use Modules\Blog\Domain\Ports\TagRepository;
use Modules\Blog\Infrastructure\Persistence\EloquentPostRepository;
use Modules\Blog\Infrastructure\Persistence\EloquentSeriesRepository;
use Modules\Blog\Infrastructure\Persistence\EloquentTagRepository;
use Modules\Blog\Infrastructure\Slug\LaravelSlugGenerator;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;
use Modules\Shared\Infrastructure\Bus\LaravelCommandBus;
use Modules\Shared\Infrastructure\Bus\LaravelQueryBus;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SlugGenerator::class, LaravelSlugGenerator::class);
        $this->app->bind(TagRepository::class, EloquentTagRepository::class);
        $this->app->bind(SeriesRepository::class, EloquentSeriesRepository::class);
        $this->app->bind(PostRepository::class, EloquentPostRepository::class);

        $this->callAfterResolving(QueryBus::class, function (QueryBus $bus): void {
            if (! $bus instanceof LaravelQueryBus) {
                return;
            }

            $bus->register(ListPosts::class, ListPostsHandler::class);
            $bus->register(GetPostBySlug::class, GetPostBySlugHandler::class);
            $bus->register(GetPostForEdit::class, GetPostForEditHandler::class);
            $bus->register(ListTags::class, ListTagsHandler::class);
            $bus->register(ListSeries::class, ListSeriesHandler::class);
            $bus->register(GetLatestPosts::class, GetLatestPostsHandler::class);
            $bus->register(ListSitemapPosts::class, ListSitemapPostsHandler::class);
        });

        $this->callAfterResolving(CommandBus::class, function (CommandBus $bus): void {
            if (! $bus instanceof LaravelCommandBus) {
                return;
            }

            $bus->register(CreatePost::class, CreatePostHandler::class);
            $bus->register(UpdatePost::class, UpdatePostHandler::class);
            $bus->register(DeletePost::class, DeletePostHandler::class);
        });
    }

    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/blog.php'));
    }
}
