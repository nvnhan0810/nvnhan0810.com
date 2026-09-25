<?php

namespace Modules\Blog\Application\Handler;

use Modules\Blog\Application\Command\CreatePost;
use Modules\Blog\Domain\Enums\PostStatus;
use Modules\Blog\Domain\Ports\PostRepository;
use Modules\Blog\Domain\Ports\SlugGenerator;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class CreatePostHandler implements CommandHandler
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly SlugGenerator $slugs,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof CreatePost);

        $status = PostStatus::from($command->status);
        $slug = $this->slugs->uniqueForPost($command->title);

        return $this->posts->createWithRelations(
            [
                'slug' => $slug,
                'title' => $command->title,
                'description' => $command->description,
                'content' => $command->content,
                'source_url' => $command->sourceUrl,
                'status' => $status,
                'published_at' => $command->publishedAt,
            ],
            $command->tags,
            $command->seriesIds ?? [],
        );
    }
}
