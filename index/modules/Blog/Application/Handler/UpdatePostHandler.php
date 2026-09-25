<?php

namespace Modules\Blog\Application\Handler;

use Modules\Blog\Application\Command\UpdatePost;
use Modules\Blog\Domain\Enums\PostStatus;
use Modules\Blog\Domain\Exceptions\PostNotFoundException;
use Modules\Blog\Domain\Ports\PostRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class UpdatePostHandler implements CommandHandler
{
    public function __construct(
        private readonly PostRepository $posts,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof UpdatePost);

        $existing = $this->posts->findById($command->id);

        if ($existing === null) {
            throw new PostNotFoundException($command->id);
        }

        $status = PostStatus::from($command->status);

        return $this->posts->updateWithRelations(
            $command->id,
            [
                'title' => $command->title,
                'description' => $command->description,
                'content' => $command->content,
                'source_url' => $command->sourceUrl,
                'status' => $status,
                'published_at' => $command->publishedAt ?? $existing->published_at,
            ],
            $command->tags,
            $command->seriesIds ?? [],
        );
    }
}
